<?php

namespace App\Services\Notifiers;

use App\Models\Alert;
use App\Models\Service;
use App\Services\Horizon\HorizonClientService;
use App\Services\Notifiers\Contracts\AlertNotifier;
use App\Support\Alerts\AlertRuleCatalog;
use Carbon\Carbon;
use Illuminate\Support\Collection;

abstract class AbstractAlertNotifier implements AlertNotifier
{
    /**
     * The Horizon API proxy service.
     */
    protected HorizonClientService $horizonApi;

    /**
     * The constructor.
     *
     * @param HorizonClientService $horizonApi The horizon API client.
     */
    public function __construct(HorizonClientService $horizonApi)
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
     * @param array<int, array{service_id: int, job_uuid: string|null, triggered_at: string}> $events
     *
     * @return array<string, mixed>
     */
    protected function buildNotification(Alert $alert, array $events): array
    {
        $service = Service::find((int) ($events[0]['service_id']));

        $enrichedEvents = $this->enrichEvents($events);

        $serviceId = (int) $enrichedEvents[0]['service_id'];
        $serviceName = (string) $serviceId;

        if ($service !== null) {
            $serviceId = $service->id;
            $serviceName = $service->name;
        }

        $events = [];
        $previewLines = (int) config('horizonhub.failed_job_exception_preview_lines');

        foreach ($enrichedEvents as $index => $event) {
            $eventServiceId = (int) $event['service_id'];
            $jobUuid = ! empty($event['job_uuid']) ? (string) $event['job_uuid'] : null;
            $full = $event['exception'];
            $preview = $full;
            $expandable = false;

            if (! empty($full)) {
                $lines = \preg_split("/\r\n|\n|\r/", $full) ?: [];

                if (\count($lines) > $previewLines) {
                    $preview = \implode("\n", \array_slice($lines, 0, $previewLines));
                    $expandable = true;
                }
            } else {
                $full = null;
                $preview = null;
            }
            $events[] = [
                'index' => $index + 1,
                'job_uuid' => $jobUuid,
                'triggered_at' => (string) $event['triggered_at'],
                'job_class' => $event['job_class'] ?? null,
                'queue' => $event['queue'] ?? null,
                'failed_at' => $event['failed_at'] ?? null,
                'attempts' => $event['attempts'] ?? null,
                'exception' => $full,
                'exceptionPreview' => $preview,
                'exceptionExpandable' => $expandable,
                'jobUrl' => $jobUuid !== null
                    ? \route('horizon.jobs.show', ['job' => $jobUuid], absolute: true)
                    : null,
            ];
        }
        $hasJobDetails = false;

        foreach ($events as $event) {
            if (! empty($event['job_uuid']) || ! empty($event['job_class']) || ! empty($event['queue']) || ! empty($event['failed_at']) || ! empty($event['exceptionPreview'])) {
                $hasJobDetails = true;
                break;
            }
        }
        $detectedAt = $enrichedEvents[0]['triggered_at'] ?? null;

        return [
            'alertName' => $alert->name,
            'ruleLabel' => AlertRuleCatalog::ruleTypeLabels()[$alert->rule_type] ?? $alert->rule_type,
            'condition' => AlertRuleCatalog::conditionSummary($alert, $detectedAt),
            'serviceName' => $serviceName,
            'serviceUrl' => $serviceId > 0 ? \route('horizon.services.show', ['service' => $serviceId], absolute: true) : null,
            'alertUrl' => \route('horizon.alerts.show', ['alert' => $alert], absolute: true),
            'appName' => (string) config('app.name'),
            'totalEventCount' => \count($events),
            'hasJobDetails' => $hasJobDetails,
            'detectedAt' => $detectedAt,
            'sentAt' => \now()->format('Y-m-d H:i:s T'),
            'events' => $events,
        ];
    }

    /**
     * Enrich events with job details from the Horizon API.
     *
     * @param array<int, array{service_id: int, job_uuid: string|null, triggered_at: string}> $events The events.
     *
     * @return array<int, array{service_id: int, job_uuid: string|null, triggered_at: string, job_class: string|null, queue: string|null, failed_at: string|null, exception: string|null, attempts: int|null}>
     */
    protected function enrichEvents(array $events): array
    {
        $enriched = [];
        $jobUuids = \array_values(\array_filter(\array_column($events, 'job_uuid')));
        $service = null;

        if (! empty($events)) {
            $serviceId = (int) ($events[0]['service_id'] ?? 0);
            $service = Service::find($serviceId);
        }
        $jobs = (empty($jobUuids) || ! $service) ? \collect() : $this->getJobs($service, $jobUuids);

        foreach ($events as $event) {
            $jobUuid = ! empty($event['job_uuid']) ? (string) $event['job_uuid'] : null;
            $job = $jobUuid ? $jobs->get($jobUuid) : null;

            $enriched[] = [
                'service_id' => (int) $event['service_id'],
                'job_uuid' => $jobUuid,
                'triggered_at' => $event['triggered_at'],
                'job_class' => $job?->name,
                'queue' => $job?->queue,
                'failed_at' => $job?->failed_at?->format('Y-m-d H:i:s T'),
                'exception' => $job?->exception,
                'attempts' => $job?->attempts,
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
            if (blank($jobUuid)) {
                continue;
            }
            $response = $this->horizonApi->getJob($service, $jobUuid);
            $data = $response['data'] ?? null;

            if (! $response['success'] || ! \is_array($data)) {
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
}
