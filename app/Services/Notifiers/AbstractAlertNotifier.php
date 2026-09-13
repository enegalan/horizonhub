<?php

namespace App\Services\Notifiers;

use App\Enums\NotificationProviderType;
use App\Models\Alert;
use App\Models\Service;
use App\Services\Horizon\HorizonClientApiService;
use App\Services\Notifiers\Contracts\AlertNotifier;
use App\Services\Notifiers\Contracts\AlertNotifierMetadata;
use App\Support\Alerts\AlertRuleCatalog;
use App\Support\Horizon\ClientResponse;
use App\Support\Jobs\JobRuntime;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

abstract class AbstractAlertNotifier implements AlertNotifier, AlertNotifierMetadata
{
    /**
     * Normalize the config.
     *
     * Webhook-based notifiers share a single `webhook_url` setting; notifiers
     * with a different shape override this method.
     *
     * @param array<string, mixed> $validated
     *
     * @return array<string, mixed>
     */
    public static function normalizedConfig(array $validated): array
    {
        return ['webhook_url' => (string) ($validated['webhook_url'] ?? '')];
    }

    /**
     * Get the metadata.
     *
     * @return array{label: string, icon: string, description: string, color: string}
     */
    abstract public static function meta(): array;

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
     * Get the type.
     */
    abstract public static function type(): NotificationProviderType;

    /**
     * Build the shared event details used by event renderers.
     *
     * @param array<string, mixed> $event The event.
     *
     * @return array{title: string, heading: string, rows: array<int, array{0: string, 1: string}>, exceptionPreview: string|null, exceptionExpandable: bool, jobUrl: string|null}
     */
    protected static function buildEventDetail(array $event, bool $multi): array
    {
        return [
            'title' => $event['job_class'] ?? $event['job_uuid'] ?? 'Unknown job',
            'heading' => $multi ? "Event {$event['index']}" : 'Failed job',
            'rows' => self::eventFieldRows($event),
            'exceptionPreview' => $event['exceptionPreview'] ?? null,
            'exceptionExpandable' => (bool) ($event['exceptionExpandable'] ?? false),
            'jobUrl' => $event['jobUrl'] ?? null,
        ];
    }

    /**
     * Build the label/value rows of an event.
     *
     * @param array<string, mixed> $event The event.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    protected static function eventFieldRows(array $event): array
    {
        $rows = [];

        foreach (['Queue' => $event['queue'], 'Failed at' => $event['failed_at'], 'Attempts' => $event['attempts'], 'Triggered at' => $event['triggered_at'] ?: null] as $label => $value) {
            if (empty($value)) {
                continue;
            }
            $rows[] = [$label, (string) $value];
        }

        return $rows;
    }

    /**
     * @param array<int, array{service_id: int, job_uuid: string|null, triggered_at: string}> $events
     *
     * @return array<string, mixed>
     */
    protected function buildNotification(Alert $alert, array $events): array
    {
        $firstEvent = ! empty($events) ? ($events[\array_key_first($events)] ?? null) : null;
        $service = $firstEvent !== null
            ? Service::find((int) $firstEvent['service_id'])
            : null;
        $enrichedEvents = $this->enrichEvents($events, $service);

        $serviceId = (int) ($enrichedEvents[0]['service_id'] ?? 0);
        $serviceName = (string) $serviceId;

        if ($service !== null) {
            $serviceId = $service->id;
            $serviceName = $service->name;
        }

        $events = [];
        $previewLines = (int) config('horizonhub.failed_job_exception_preview_lines');

        foreach ($enrichedEvents as $index => $event) {
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
            'ruleLabel' => $alert->rule_type->label(),
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
    protected function enrichEvents(array $events, ?Service $service = null): array
    {
        $enriched = [];
        $jobUuids = \array_values(\array_filter(\array_column($events, 'job_uuid')));

        if ($service === null && ! empty($events)) {
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
            $data = ClientResponse::data(HorizonClientApiService::getJob($service, $jobUuid));

            if ($data === null) {
                continue;
            }
            $failedAt = JobRuntime::parseJobTimestamp($data['failed_at'] ?? null);
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
     * Send a batched webhook alert to the configured webhook URL.
     *
     * @param Alert $alert The alert.
     * @param array<int, array{service_id: int, job_uuid: string|null, triggered_at: string}> $events The events.
     * @param array<string, mixed> $config The config.
     * @param callable(array<string, mixed>): array<string, mixed> $payloadBuilder The payload builder.
     */
    protected function sendWebhook(Alert $alert, array $events, array $config, callable $payloadBuilder): void
    {
        $webhookUrl = $config['webhook_url'] ?? '';

        if (blank($webhookUrl) || empty($events)) {
            return;
        }

        $payload = $payloadBuilder($this->buildNotification($alert, $events));

        Http::post($webhookUrl, $payload);
    }
}
