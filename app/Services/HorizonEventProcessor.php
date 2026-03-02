<?php

namespace App\Services;

use App\Events\HorizonEventReceived;
use App\Models\HorizonFailedJob;
use App\Models\HorizonJob;
use App\Models\HorizonSupervisorState;
use App\Models\Service;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HorizonEventProcessor {
    public function __construct(
        private readonly AlertEngine $alertEngine
    ) {}

    public function process(Service $service, array $event): void {
        $eventType = $event['event_type'] ?? '';

        if ($eventType === 'SupervisorLooped') {
            $this->processSupervisorLooped($service, $event);
            return;
        }

        $jobId = $event['job_id'] ?? '';
        $queue = $event['queue'] ?? '';
        $name = $event['name'] ?? null;
        $payload = $event['payload'] ?? null;
        $attempts = isset($event['attempts']) ? (int) $event['attempts'] : 0;
        $statusRaw = $event['status'] ?? null;
        $status = (is_string($statusRaw) && $statusRaw !== '') ? $statusRaw : $eventType;
        $processedAt = isset($event['processed_at']) ? $event['processed_at'] : null;
        $failedAt = isset($event['failed_at']) ? $event['failed_at'] : null;
        $queuedAt = isset($event['queued_at']) ? $event['queued_at'] : null;
        $runtimeSeconds = isset($event['runtime_seconds']) ? (float) $event['runtime_seconds'] : null;
        $exception = $event['exception'] ?? null;

        DB::transaction(function () use ($service, $eventType, $jobId, $queue, $name, $payload, $attempts, $status, $processedAt, $failedAt, $queuedAt, $runtimeSeconds, $exception) {
            if ($eventType === 'JobFailed') {
                HorizonFailedJob::create([
                    'service_id' => $service->id,
                    'job_uuid' => $jobId,
                    'queue' => $queue,
                    'payload' => $payload,
                    'exception' => $exception,
                    'failed_at' => $failedAt ? now()->parse($failedAt) : now(),
                ]);
            }

            $failedAtParsed = $failedAt ? now()->parse($failedAt) : null;
            $attributes = [
                'queue' => $queue,
                'payload' => $payload,
                'status' => $status,
                'attempts' => $attempts,
                'name' => $name,
                'processed_at' => $processedAt ? now()->parse($processedAt) : null,
                'failed_at' => $eventType === 'JobFailed' ? $failedAtParsed : null,
                'runtime_seconds' => $runtimeSeconds,
            ];
            if ($queuedAt !== null) {
                $attributes['queued_at'] = now()->parse($queuedAt);
            }
            if ($eventType === 'JobFailed' && $exception !== null) {
                $attributes['exception'] = $exception;
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

    private function processSupervisorLooped(Service $service, array $event): void {
        $name = isset($event['queue']) && (string) $event['queue'] !== '' ? (string) $event['queue'] : 'default';
        HorizonSupervisorState::updateOrCreate(
            [
                'service_id' => $service->id,
                'name' => $name,
            ],
            [
                'last_seen_at' => now(),
            ]
        );
    }
}
