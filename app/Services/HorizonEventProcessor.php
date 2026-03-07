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
        $queue = $event['queue'] ?? '';
        $name = $event['name'] ?? null;
        $payload = $event['payload'] ?? null;
        $attempts = isset($event['attempts']) ? (int) $event['attempts'] : 0;
        $statusRaw = $event['status'] ?? null;
        $status = (\is_string($statusRaw) && $statusRaw !== '') ? $statusRaw : $eventType;
        $processedAt = $event['processed_at'] ?? null;
        $failedAt = $event['failed_at'] ?? null;
        $queuedAt = $event['queued_at'] ?? null;
        $runtimeSeconds = isset($event['runtime_seconds']) ? (float) $event['runtime_seconds'] : null;
        $exception = $event['exception'] ?? null;

        if ($queuedAt === null && isset($payload) && \is_array($payload)) {
            $pushedAtRaw = $payload['pushedAt'] ?? null;
            if ($pushedAtRaw !== null && \is_numeric($pushedAtRaw)) {
                $pushedAtFloat = (float) $pushedAtRaw;
                if ($pushedAtFloat > 0) {
                    $queuedAt = Carbon::createFromTimestamp((int) $pushedAtFloat)->toIso8601String();
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
                    'failed_at' => $failedAt ? \now()->parse($failedAt) : \now(),
                ]);
            }

            $failedAtParsed = $failedAt ? \now()->parse($failedAt) : null;
            $existing = HorizonJob::where('service_id', $service->id)->where('job_uuid', $jobId)->lockForUpdate()->first();

            $processedAtParsed = $processedAt ? \now()->parse($processedAt) : null;

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
                'queued_at' => $queuedAt !== null ? \now()->parse($queuedAt) : ($existing ? $existing->queued_at : null),
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
    }

    /**
     * Process queue paused or resumed event and update HorizonQueueState.
     *
     * @param Service $service
     * @param array $event
     * @return void
     */
    private function processQueuePauseResume(Service $service, array $event): void {
        $queue = isset($event['queue']) && (string) $event['queue'] !== '' ? (string) $event['queue'] : 'redis.default';
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
