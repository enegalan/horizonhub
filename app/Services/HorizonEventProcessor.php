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
     * Normalize a queue name to avoid duplicates caused by different connection prefixes.
     *
     * For example, "redis.default" becomes "default", "redis.notifications"
     * becomes "notifications", etc. Queues without a dot or with a different
     * scheme are returned unchanged.
     *
     * @param string|null $queue
     * @return string|null
     */
    private function private__normalizeQueueName(?string $queue): ?string {
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
            $this->private__processSupervisorLooped($service, $event);
            return;
        }

        $jobUuid = $event['job_uuid'] ?? '';
        $queueRaw = $event['queue'] ?? '';
        $queue = $this->private__normalizeQueueName($queueRaw);
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
    private function private__processSupervisorLooped(Service $service, array $event): void {
        // Treat supervisor loop as a heartbeat for the service itself.
        $service->forceFill([
            'last_seen_at' => \now(),
            'status' => 'online',
        ])->saveQuietly();
    }

}
