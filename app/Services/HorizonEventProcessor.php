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
     * Process an event.
     *
     * @param Service $service
     * @param array<string, mixed> $event
     * @return void
     */
    public function process(Service $service, array $event): void {
        $eventType = (string) ($event['event_type'] ?? '');

        if ($eventType === 'SupervisorLooped') {
            $this->private__processSupervisorLooped($service, $event);

            return;
        }

        $jobUuid = $this->private__resolveJobUuid($event);

        $queueRaw = isset($event['queue']) ? (string) $event['queue'] : '';
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
                    }
                }
            }
        }

        $this->alertEngine->evaluateAfterEvent(
            $service->id,
            $eventType,
            $jobUuid !== '' ? $jobUuid : null
        );

        $broadcastJobUuid = $jobUuid !== '' ? $jobUuid : null;

        \broadcast(new HorizonEventReceived($eventType, (int) $service->id, $broadcastJobUuid, [
            'job_uuid' => $broadcastJobUuid,
            'queue' => $queue,
            'name' => $name,
            'status' => $status,
        ]))->toOthers();
    }

    /**
     * Process a supervisor looped event.
     *
     * @param Service $service
     * @param array<string, mixed> $event
     * @return void
     */
    private function private__processSupervisorLooped(Service $service, array $event): void {
        $service->forceFill([
            'last_seen_at' => \now(),
            'status' => 'online',
        ])->saveQuietly();
    }


    /**
     * Normalize a queue name to avoid duplicates caused by different connection prefixes.
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
     * Resolve the job UUID from the event.
     *
     * @param array<string, mixed> $event
     * @return string
     */
    private function private__resolveJobUuid(array $event): string {
        foreach (['job_uuid', 'job_id', 'id'] as $key) {
            if (isset($event[$key]) && (string) $event[$key] !== '') {
                return (string) $event[$key];
            }
        }

        return '';
    }

}
