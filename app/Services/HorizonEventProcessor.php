<?php

namespace App\Services;

use App\Events\HorizonEventReceived;
use App\Models\Service;
use Carbon\Carbon;

class HorizonEventProcessor {

    /**
     * The alert engine.
     *
     * @var AlertEngine
     */
    private AlertEngine $alertEngine;

    /**
     * Construct the Horizon event processor.
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
            // Heuristic: very large values are treated as milliseconds since epoch,
            // smaller values are treated as seconds (possibly with fractions).
            if ($numeric > 1000000000000) {
                $timestampMs = (int) \round($numeric);
                return Carbon::createFromTimestampMs($timestampMs);
            }

            $timestampMs = (int) \round($numeric * 1000);
            return Carbon::createFromTimestampMs($timestampMs);
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

        $jobUuid = $event['job_uuid'] ?? '';
        $queueRaw = $event['queue'] ?? '';
        $queue = $this->normalizeQueueName($queueRaw);
        $name = $event['name'] ?? null;
        $payload = $event['payload'] ?? null;
        $statusRaw = $event['status'] ?? null;
        $status = (\is_string($statusRaw) && $statusRaw !== '') ? $statusRaw : $eventType;
        $queuedAt = $event['queued_at'] ?? ($event['pushed_at'] ?? null);

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


        $this->alertEngine->evaluateAfterEvent(
            $service->id,
            $eventType,
            $jobUuid
        );

        broadcast(new HorizonEventReceived($eventType, $service->id, $jobUuid, [
            'job_uuid' => $jobUuid,
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
        // Treat supervisor loop as a heartbeat for the service itself.
        $service->forceFill([
            'last_seen_at' => \now(),
            'status' => 'online',
        ])->saveQuietly();
    }

}
