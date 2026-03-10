<?php

namespace App\Services;

use App\Models\Service;
use App\Models\HorizonSupervisorState;
use Illuminate\Support\Facades\Log;

class HorizonSyncService {
    private HorizonApiProxyService $horizonApi;
    private HorizonEventProcessor $eventProcessor;

    /**
     * Construct the horizon sync service.
     *
     * @param HorizonApiProxyService $horizonApi
     * @param HorizonEventProcessor $eventProcessor
     */
    public function __construct(HorizonApiProxyService $horizonApi, HorizonEventProcessor $eventProcessor) {
        $this->horizonApi = $horizonApi;
        $this->eventProcessor = $eventProcessor;
    }

    /**
     * Sync recent jobs for all services or a specific service.
     *
     * @param int|null $serviceId
     * @return void
     */
    public function syncRecentJobs(?int $serviceId = null): void {
        $query = Service::query()->whereNotNull('base_url');
        if ($serviceId !== null) {
            $query->where('id', $serviceId);
        }

        /** @var Service $service */
        foreach ($query->get() as $service) {
            $this->syncServiceJobs($service);
        }
    }

    /**
     * Sync recent jobs for a single service from Horizon HTTP API into the Hub database.
     *
     * @param Service $service
     * @return void
     */
    private function syncServiceJobs(Service $service): void {
        if (! $service->base_url) {
            return;
        }

        $masters = $this->horizonApi->getMasters($service);
        if ($masters['success'] ?? false) {
            $this->syncSupervisorsFromMasters($service, $masters);
        }

        $failed = $this->horizonApi->getFailedJobs($service);
        if ($failed['success'] ?? false) {
            $this->upsertJobsFromResponse($service, $failed, 'JobFailed');
        }

        $completed = $this->horizonApi->getCompletedJobs($service);
        if ($completed['success'] ?? false) {
            $this->upsertJobsFromResponse($service, $completed, 'JobProcessed');
        }

        $pending = $this->horizonApi->getPendingJobs($service);
        if ($pending['success'] ?? false) {
            $this->upsertJobsFromResponse($service, $pending, 'JobProcessing');
        }
    }

    /**
     * Sync supervisor states for a service from the Horizon masters API response.
     *
     * @param Service $service
     * @param array{success: bool, data?: array} $response
     * @return void
     */
    private function syncSupervisorsFromMasters(Service $service, array $response): void {
        $data = $response['data'] ?? null;
        if (! \is_array($data)) {
            return;
        }

        $now = now();

        foreach ($data as $master) {
            if (! \is_array($master)) {
                continue;
            }

            $supervisors = $master['supervisors'] ?? null;
            if (! \is_array($supervisors)) {
                continue;
            }

            foreach ($supervisors as $supervisor) {
                if (! \is_array($supervisor)) {
                    continue;
                }

                $name = isset($supervisor['name']) ? (string) $supervisor['name'] : '';
                if ($name === '') {
                    continue;
                }

                HorizonSupervisorState::updateOrCreate(
                    [
                        'service_id' => $service->id,
                        'name' => $name,
                    ],
                    [
                        'last_seen_at' => $now,
                    ]
                );
            }
        }

        // Any successful supervisors sync counts as a heartbeat for the service.
        $service->forceFill([
            'last_seen_at' => $now,
            'status' => 'online',
        ])->saveQuietly();
    }

    /**
     * Upsert jobs from a Horizon API response using the existing HorizonEventProcessor.
     *
     * @param Service $service
     * @param array{success: bool, data?: array} $response
     * @param string $eventType
     * @return void
     */
    private function upsertJobsFromResponse(Service $service, array $response, string $eventType): void {
        $data = $response['data'] ?? null;
        if (! \is_array($data)) {
            return;
        }

        $jobs = [];
        if (isset($data['jobs']) && \is_array($data['jobs'])) {
            $jobs = $data['jobs'];
        } elseif (\array_is_list($data)) {
            $jobs = $data;
        }

        foreach ($jobs as $job) {
            if (! \is_array($job)) {
                continue;
            }

            $jobId = (string) ($job['id'] ?? $job['uuid'] ?? $job['job_uuid'] ?? '');
            if ($jobId === '') {
                continue;
            }

            $queue = (string) ($job['queue'] ?? $job['queue_name'] ?? '');
            $name = isset($job['name']) && (string) $job['name'] !== '' ? (string) $job['name'] : null;
            $payload = $job['payload'] ?? null;

            $attemptsRaw = $job['attempts'] ?? null;
            if ($attemptsRaw === null && \is_array($payload)) {
                $attemptsRaw = $payload['attempts'] ?? null;
            }
            // Fallback: Horizon HTTP API exposes "retried_by" for failed jobs,
            // which we can use to approximate the total attempts as
            // (number of retries + 1 initial attempt).
            if ($attemptsRaw === null && isset($job['retried_by']) && \is_array($job['retried_by'])) {
                $attemptsRaw = \count($job['retried_by']) + 1;
            }
            // As a very last resort, treat known jobs as having at least 1 attempt.
            if ($attemptsRaw === null && $eventType === 'JobFailed') {
                $attemptsRaw = 1;
            }
            $attempts = isset($attemptsRaw) ? (int) $attemptsRaw : 0;

            // Normalize status to the values expected by Horizon Hub:
            // - "failed" for failed jobs
            // - "processed" for completed jobs
            // - "processing" for pending/processing jobs
            $status = match ($eventType) {
                'JobFailed' => 'failed',
                'JobProcessed' => 'processed',
                'JobProcessing' => 'processing',
                default => 'processing',
            };
            $reservedAt = $job['reserved_at'] ?? null;
            $completedAt = $job['completed_at'] ?? $job['processed_at'] ?? null;
            $failedAt = $job['failed_at'] ?? null;
            $exception = $job['exception'] ?? null;

            $event = [
                'event_type' => $eventType,
                'job_id' => $jobId,
                'queue' => $queue,
                'name' => $name,
                'payload' => $payload,
                'attempts' => $attempts,
                'status' => $status,
                'queued_at' => $reservedAt,
                'processed_at' => $completedAt,
                'failed_at' => $failedAt,
                'runtime_seconds' => $job['runtime'] ?? $job['runtime_seconds'] ?? null,
                'exception' => $exception,
            ];

            $this->eventProcessor->process($service, $event);
        }
    }
}

