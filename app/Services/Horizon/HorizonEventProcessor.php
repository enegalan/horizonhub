<?php

namespace App\Services\Horizon;

use App\Events\HorizonEventReceived;
use App\Models\Service;
use App\Services\Alerts\AlertEngine;
use App\Support\Horizon\QueueNameNormalizer;
use Carbon\Carbon;

// TO-EVALUATE: Only used by Tests
class HorizonEventProcessor
{
    /**
     * The alert engine.
     */
    private AlertEngine $alertEngine;

    /**
     * Construct the Horizon event processor.
     */
    public function __construct(
        AlertEngine $alertEngine
    ) {
        $this->alertEngine = $alertEngine;
    }

    /**
     * Process an event.
     *
     * @param  array<string, mixed>  $event
     */
    public function process(Service $service, array $event): void
    {
        $eventType = (string) ($event['event_type'] ?? '');

        if ($eventType === 'SupervisorLooped') {
            $this->private__processSupervisorLooped($service, $event);

            return;
        }

        $jobUuid = $event['job_uuid'] ?? null;

        $queueRaw = isset($event['queue']) ? (string) $event['queue'] : '';
        $queue = QueueNameNormalizer::normalize($queueRaw);
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
     * @param  array<string, mixed>  $event
     */
    private function private__processSupervisorLooped(Service $service, array $event): void
    {
        $service->forceFill([
            'last_seen_at' => \now(),
            'status' => 'online',
        ])->saveQuietly();
    }
}
