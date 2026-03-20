<?php

namespace App\Services;

use App\Models\Service;
use App\Models\HorizonSupervisorState;

class HorizonSyncService {

    /**
     * The Horizon API proxy service.
     *
     * @var HorizonApiProxyService
     */
    private HorizonApiProxyService $horizonApi;

    /**
     * The Horizon event processor.
     *
     * @var HorizonEventProcessor
     */
    private HorizonEventProcessor $eventProcessor;

    /**
     * Construct the Horizon sync service.
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
            $this->private__syncServiceJobs($service);
        }
    }

    /**
     * Sync recent jobs for a single service from Horizon HTTP API into the Hub database.
     *
     * @param Service $service
     * @return void
     */
    private function private__syncServiceJobs(Service $service): void {
        if (! $service->base_url) {
            return;
        }

        $masters = $this->horizonApi->getMasters($service);
        if ($masters['success'] ?? false) {
            $this->private__syncSupervisorsFromMasters($service, $masters);
        }

        $failed = $this->horizonApi->getFailedJobs($service);
        if ($failed['success'] ?? false) {
            $this->private__upsertJobsFromResponse($service, $failed, 'JobFailed');
        }

        $completed = $this->horizonApi->getCompletedJobs($service);
        if ($completed['success'] ?? false) {
            $this->private__upsertJobsFromResponse($service, $completed, 'JobProcessed');
        }

        $pending = $this->horizonApi->getPendingJobs($service);
        if ($pending['success'] ?? false) {
            $this->private__upsertJobsFromResponse($service, $pending, 'JobProcessing');
        }
    }

    /**
     * Sync supervisor states for a service from the Horizon masters API response.
     *
     * @param Service $service
     * @param array{success: bool, data?: array} $response
     * @return void
     */
    private function private__syncSupervisorsFromMasters(Service $service, array $response): void {
        $now = now();
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
    private function private__upsertJobsFromResponse(Service $service, array $response, string $eventType): void {
        $data = $response['data'] ?? null;
        if (! \is_array($data)) {
            return;
        }

        $jobs = $data['jobs'];
        foreach ($jobs as $job) {
            if (! \is_array($job)) {
                continue;
            }

            $jobUuid = (string) $job['id'];
            if (empty($jobUuid)) {
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

            $runtimeSecondsRaw = null;
            $reservedNumeric = \is_numeric($reservedAt) ? (float) $reservedAt : null;
            $completedNumeric = \is_numeric($completedAt) ? (float) $completedAt : null;
            $failedNumeric = \is_numeric($failedAt) ? (float) $failedAt : null;

            if ($reservedNumeric !== null && $completedNumeric !== null) {
                $runtimeSecondsRaw = $completedNumeric - $reservedNumeric;
            } elseif ($reservedNumeric !== null && $failedNumeric !== null) {
                $runtimeSecondsRaw = $failedNumeric - $reservedNumeric;
            }

            if ($runtimeSecondsRaw !== null) {
                if ($runtimeSecondsRaw < 0 || $runtimeSecondsRaw > 9999999) {
                    $runtimeSecondsRaw = null;
                }
            }

            $event = [
                'event_type' => $eventType,
                'job_uuid' => $jobUuid,
                'queue' => $queue,
                'name' => $name,
                'payload' => $payload,
                'attempts' => $attempts,
                'status' => $status,
                'queued_at' => $reservedAt,
                'processed_at' => $completedAt,
                'failed_at' => $failedAt,
                'runtime_seconds' => $runtimeSecondsRaw,
                'exception' => $exception,
            ];

            $this->eventProcessor->process($service, $event);
        }
    }
}
