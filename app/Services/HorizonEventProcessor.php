<?php

namespace App\Services;

use App\Events\HorizonEventReceived;
use App\Models\HorizonFailedJob;
use App\Models\HorizonJob;
use App\Models\HorizonQueueState;
use App\Models\HorizonSupervisorState;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HorizonEventProcessor {
    /**
     * The alert engine.
     *
     * @var AlertEngine
     */
    private AlertEngine $alertEngine;
    /**
     * Construct the horizon event processor.
     *
     * @param AlertEngine $alertEngine
     */
    public function __construct(
        AlertEngine $alertEngine
    ) {
        $this->alertEngine = $alertEngine;
    }

    /**
     * Parse a timestamp value coming from Horizon events.
     *
     * Accepts ISO8601 strings, database datetime strings or numeric Unix timestamps
     * (seconds or milliseconds). Returns a Carbon instance or null on failure.
     *
     * @param mixed $value
     * @return Carbon|null
     */
    private function parseEventTime(mixed $value): ?Carbon {
        if ($value === null || $value === '') {
            return null;
        }

        if (\is_numeric($value)) {
            $numeric = (float) $value;
            // Heuristic: timestamps larger than current time * 100 likely in milliseconds
            $nowSeconds = (float) \time();
            if ($numeric > $nowSeconds * 100) {
                $timestampMs = (int) \round($numeric);
                return Carbon::createFromTimestampMs($timestampMs);
            }

            return Carbon::createFromTimestamp((int) \round($numeric));
        }

        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Normalize a queue name to avoid duplicates caused by different connection prefixes.
     *
     * For example, "redis.default" becomes "default", "redis.notifications"
     * becomes "notifications", etc. Queues without a dot or with a different
     * scheme are returned unchanged.
     *
     * @param string|null $queue
     * @return string|null
     */
    private function normalizeQueueName(?string $queue): ?string {
        if ($queue === null || $queue === '') {
            return $queue;
        }

        if (\str_starts_with($queue, 'redis.')) {
            $suffix = \substr($queue, \strlen('redis.'));
            return $suffix !== '' ? $suffix : $queue;
        }

        return $queue;
    }

    /**
     * Process an event.
     *
     * @param Service $service
     * @param array $event
     * @return void
     */
    public function process(Service $service, array $event): void {
        $eventType = $event['event_type'] ?? '';

        if ($eventType === 'SupervisorLooped') {
            $this->processSupervisorLooped($service, $event);
            return;
        }

        if ($eventType === 'QueuePaused' || $eventType === 'QueueResumed') {
            $this->processQueuePauseResume($service, $event);
            return;
        }

        $jobId = $event['job_id'] ?? '';
        $queueRaw = $event['queue'] ?? '';
        $queue = $this->normalizeQueueName($queueRaw);
        $name = $event['name'] ?? null;
        $payload = $event['payload'] ?? null;
        $attemptsRaw = $event['attempts'] ?? null;
        $attempts = isset($attemptsRaw) ? (int) $attemptsRaw : 0;
        $statusRaw = $event['status'] ?? null;
        $status = (\is_string($statusRaw) && $statusRaw !== '') ? $statusRaw : $eventType;
        $processedAt = $event['processed_at'] ?? null;
        $failedAt = $event['failed_at'] ?? null;
        $queuedAt = $event['queued_at'] ?? ($event['pushed_at'] ?? null);
        $runtimeSeconds = isset($event['runtime_seconds']) ? (float) $event['runtime_seconds'] : null;
        $exception = $event['exception'] ?? null;

        if ($queuedAt === null && isset($payload) && \is_array($payload)) {
            $pushedAtRaw = $payload['pushedAt'] ?? null;
            if ($pushedAtRaw !== null) {
                if (\is_numeric($pushedAtRaw)) {
                    $pushedAtFloat = (float) $pushedAtRaw;
                    if ($pushedAtFloat > 0) {
                        $queuedAt = Carbon::createFromTimestampMs((int) \round($pushedAtFloat * 1000))->toIso8601String();
                    }
                } elseif (\is_string($pushedAtRaw) && $pushedAtRaw !== '') {
                    try {
                        $queuedAt = Carbon::parse($pushedAtRaw)->toIso8601String();
                    } catch (\Throwable $e) {
                        // ignore parse errors and leave queuedAt as null
                    }
                }
            }
        }

        DB::transaction(function () use ($service, $eventType, $jobId, $queue, $name, $payload, $attempts, $status, $processedAt, $failedAt, $queuedAt, $runtimeSeconds, $exception) {
            if ($eventType === 'JobFailed') {
                HorizonFailedJob::create([
                    'service_id' => $service->id,
                    'job_uuid' => $jobId,
                    'queue' => $queue,
                    'payload' => $payload,
                    'exception' => $exception,
                    'failed_at' => $this->parseEventTime($failedAt) ?? \now(),
                ]);
            }

            $failedAtParsed = $this->parseEventTime($failedAt);
            $existing = HorizonJob::where('service_id', $service->id)->where('job_uuid', $jobId)->lockForUpdate()->first();

            $processedAtParsed = $this->parseEventTime($processedAt);

            $existingStatus = $existing ? $existing->status : null;
            $statusForAttributes = $status !== null ? $status : $existingStatus;

            if ($existing && $existing->failed_at !== null && $eventType !== 'JobFailed') {
                $statusForAttributes = $existingStatus;
            }

            $attributes = [
                'queue' => $queue !== '' ? $queue : ($existing ? $existing->queue : null),
                'payload' => $payload !== null ? $payload : ($existing ? $existing->payload : null),
                'status' => $statusForAttributes,
                'attempts' => $attempts !== 0 ? $attempts : ($existing ? $existing->attempts : 0),
                'name' => $name !== null ? $name : ($existing ? $existing->name : null),
                'processed_at' => ($existing && $existing->failed_at !== null && $eventType !== 'JobFailed')
                    ? $existing->processed_at
                    : ($processedAtParsed !== null ? $processedAtParsed : ($existing ? $existing->processed_at : null)),
                'failed_at' => $existing ? $existing->failed_at : null,
                'runtime_seconds' => $runtimeSeconds !== null ? $runtimeSeconds : ($existing ? $existing->runtime_seconds : null),
                'queued_at' => $queuedAt !== null ? $this->parseEventTime($queuedAt) : ($existing ? $existing->queued_at : null),
                'exception' => $existing ? $existing->exception : null,
            ];

            if ($eventType === 'JobFailed') {
                $attributes['processed_at'] = null;
                $attributes['failed_at'] = $failedAtParsed !== null ? $failedAtParsed : $attributes['failed_at'];
                if ($exception !== null) {
                    $attributes['exception'] = $exception;
                }
            }

            HorizonJob::updateOrCreate(
                [
                    'service_id' => $service->id,
                    'job_uuid' => $jobId,
                ],
                $attributes
            );
        });

        $horizonJob = HorizonJob::where('service_id', $service->id)->where('job_uuid', $jobId)->first();

        $this->alertEngine->evaluateAfterEvent(
                $service->id,
                $eventType,
                $horizonJob?->id
            );

        broadcast(new HorizonEventReceived($eventType, $service->id, $horizonJob?->id, [
            'job_id' => $jobId,
            'queue' => $queue,
            'name' => $name,
            'status' => $status,
        ]))->toOthers();
    }

    /**
     * Process a supervisor looped event.
     *
     * @param Service $service
     * @param array $event
     * @return void
     */
    private function processSupervisorLooped(Service $service, array $event): void {
        $name = isset($event['queue']) && (string) $event['queue'] !== '' ? (string) $event['queue'] : 'default';
        HorizonSupervisorState::updateOrCreate(
            [
                'service_id' => $service->id,
                'name' => $name,
            ],
            [
                'last_seen_at' => \now(),
            ]
        );

        // Treat supervisor loop as a heartbeat for the service itself.
        $service->forceFill([
            'last_seen_at' => \now(),
            'status' => 'online',
        ])->saveQuietly();
    }

    /**
     * Process queue paused or resumed event and update HorizonQueueState.
     *
     * @param Service $service
     * @param array $event
     * @return void
     */
    private function processQueuePauseResume(Service $service, array $event): void {
        $queueRaw = isset($event['queue']) && (string) $event['queue'] !== '' ? (string) $event['queue'] : 'redis.default';
        $queue = $this->normalizeQueueName($queueRaw);
        $isPaused = ($event['event_type'] ?? '') === 'QueuePaused';

        HorizonQueueState::updateOrCreate(
            [
                'service_id' => $service->id,
                'queue' => $queue,
            ],
            [
                'is_paused' => $isPaused,
            ]
        );
    }
}
