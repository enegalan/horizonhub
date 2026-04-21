<?php

namespace App\Services\Notifiers;

use App\Contracts\AlertNotifier;
use App\Models\Alert;
use App\Models\Service;
use App\Services\Horizon\HorizonApiProxyService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

abstract class AbstractAlertNotifier implements AlertNotifier
{
    /**
     * The Horizon API proxy service.
     */
    protected HorizonApiProxyService $horizonApi;

    /**
     * Construct the alert notifier.
     */
    public function __construct(HorizonApiProxyService $horizonApi)
    {
        $this->horizonApi = $horizonApi;
    }

    /**
     * Send an alert for a single event.
     */
    public function send(Alert $alert, int $serviceId, ?string $jobUuid, array $config): void
    {
        $this->sendBatched($alert, [
            [
                'service_id' => $serviceId,
                'job_uuid' => $jobUuid,
                'triggered_at' => \now()->toIso8601String(),
            ],
        ], $config);
    }

    /**
     * Enrich events with job details from the Horizon API.
     *
     * @param array<int, array{service_id: int, job_uuid: string|null, triggered_at: string}> $events The events.
     * @param int $maxEvents The max events.
     * @param int $exceptionMaxLength The exception max length.
     *
     * @return array<int, array{service_id: int, job_uuid: string|null, triggered_at: string, job_class: string|null, queue: string|null, failed_at: string|null, exception: string|null, attempts: int|null}>
     */
    protected function enrichEvents(array $events, int $maxEvents, int $exceptionMaxLength): array
    {
        $eventsToEnrich = \count($events) > $maxEvents
            ? \array_slice($events, 0, $maxEvents)
            : $events;

        $enriched = [];
        $jobUuids = \array_values(\array_filter(\array_column($eventsToEnrich, 'job_uuid')));
        $service = null;

        if (! empty($eventsToEnrich)) {
            $serviceId = (int) ($eventsToEnrich[0]['service_id'] ?? 0);
            $service = Service::find($serviceId);
        }
        $jobs = (empty($jobUuids) || ! $service) ? \collect() : $this->getJobs($service, $jobUuids);

        foreach ($eventsToEnrich as $event) {
            $jobUuid = isset($event['job_uuid']) ? (string) $event['job_uuid'] : null;
            $job = $jobUuid ? $jobs->get($jobUuid) : null;

            $jobClass = null;
            $queue = null;
            $failedAt = null;
            $exception = null;
            $attempts = null;

            if ($job) {
                $jobClass = $job->name ?? 'Unknown';
                $queue = $job->queue ?? null;
                $failedAt = $job?->failed_at->format('Y-m-d H:i:s T');
                $rawException = $job->exception ?? '';
                $exception = $rawException !== '' ? $this->truncateException($rawException, $exceptionMaxLength) : null;
                $attempts = $job->attempts ?? null;
            }

            $enriched[] = [
                'service_id' => (int) ($event['service_id'] ?? 0),
                'job_uuid' => $jobUuid,
                'triggered_at' => $event['triggered_at'] ?? '',
                'job_class' => $jobClass,
                'queue' => $queue,
                'failed_at' => $failedAt,
                'exception' => $exception,
                'attempts' => $attempts,
            ];
        }

        return $enriched;
    }

    /**
     * Load failed job details from the Horizon API for the given service and UUIDs.
     *
     * @param Service $service The service.
     * @param array<int, string> $jobUuids The job UUIDs.
     *
     * @return Collection<string, object{payload: array, name: string|null, queue: string|null, failed_at: Carbon|null, exception: string, attempts: int|null}>
     */
    protected function getJobs(Service $service, array $jobUuids): Collection
    {
        $jobs = \collect();

        foreach ($jobUuids as $jobUuid) {
            if ($jobUuid === '') {
                continue;
            }
            $response = $this->horizonApi->getJob($service, $jobUuid);
            $data = $response['data'] ?? null;

            if (! ($response['success'] ?? false) || ! \is_array($data)) {
                continue;
            }
            $failedAt = null;

            if (isset($data['failed_at']) && (string) $data['failed_at'] !== '') {
                try {
                    $failedAt = Carbon::parse((string) $data['failed_at']);
                } catch (\Throwable $e) {
                    // leave null
                }
            }
            $job = (object) [
                'payload' => isset($data['payload']) ? $data['payload'] : [],
                'name' => isset($data['name']) ? $data['name'] : null,
                'queue' => isset($data['queue']) ? (string) $data['queue'] : null,
                'failed_at' => $failedAt,
                'exception' => isset($data['exception']) ? (string) $data['exception'] : '',
                'attempts' => isset($data['attempts']) ? (int) $data['attempts'] : null,
            ];
            $jobs->put($jobUuid, $job);
        }

        return $jobs;
    }

    /**
     * Truncate exception text to the given maximum length.
     *
     * @param string $text The text.
     * @param int $maxLength The max length.
     */
    protected function truncateException(string $text, int $maxLength): string
    {
        if (\strlen($text) <= $maxLength) {
            return $text;
        }

        $truncated = \substr($text, 0, $maxLength);
        $lastNewLine = \strrpos($truncated, "\n");

        if ($lastNewLine !== false) {
            $truncated = \substr($truncated, 0, $lastNewLine);
        }

        return \rtrim($truncated) . "\n\n...";
    }
}
