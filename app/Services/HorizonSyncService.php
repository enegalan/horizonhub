<?php

namespace App\Services;

use App\Models\Service;

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
            $attempts = isset($job['attempts']) ? (int) $job['attempts'] : 0;
            // Normalize status to the values expected by the Hub:
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
                // Let HorizonEventProcessor infer queued_at from payload->pushedAt
                // or leave it null so we do not insert invalid or synthetic dates.
                'queued_at' => null,
                'processed_at' => $completedAt,
                'failed_at' => $failedAt,
                'runtime_seconds' => $job['runtime'] ?? $job['runtime_seconds'] ?? null,
                'exception' => $exception,
            ];

            $this->eventProcessor->process($service, $event);
        }
    }
}

