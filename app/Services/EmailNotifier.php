<?php

namespace App\Services;

use App\Contracts\EmailAlertNotifier;
use App\Mail\AlertBatchedMail;
use App\Models\Alert;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailNotifier implements EmailAlertNotifier {

    /**
     * Maximum length of exception text in the email body (keeps MIME encoding within memory limits).
     *
     * @var int
     */
    private const EMAIL_EXCEPTION_MAX_LENGTH = 500;

    /**
     * The Horizon API proxy service.
     *
     * @var HorizonApiProxyService
     */
    private HorizonApiProxyService $horizonApi;

    /**
     * Construct the email notifier.
     *
     * @param HorizonApiProxyService $horizonApi
     */
    public function __construct(HorizonApiProxyService $horizonApi) {
        $this->horizonApi = $horizonApi;
    }

    /**
     * Maximum number of events to include in the email body (avoids memory exhaustion).
     *
     * @var int
     */
    private const MAX_EVENTS_IN_EMAIL = 20;

    /**
     * Send an alert.
     *
     * @param Alert $alert
     * @param int $serviceId
     * @param string|null $jobUuid
     * @param array $config
     * @return void
     */
    public function send(Alert $alert, int $serviceId, ?string $jobUuid, array $config): void {
        $this->sendBatched($alert, [
            ['service_id' => $serviceId, 'job_uuid' => $jobUuid, 'triggered_at' => \now()->toIso8601String()],
        ], $config);
    }

    /**
     * Send a batched alert.
     *
     * @param Alert $alert
     * @param array<int, array{service_id: int, job_uuid: string|null, triggered_at: string}> $events
     * @param array $config
     * @return void
     */
    public function sendBatched(Alert $alert, array $events, array $config): void {
        $to = $config['to'] ?? [];
        $to = \is_array($to) ? \array_values(\array_filter(\array_map('trim', $to))) : [];
        if (empty($to)) {
            return;
        }

        $count = \count($events);
        $eventsToEnrich = $count > self::MAX_EVENTS_IN_EMAIL
            ? \array_slice($events, 0, self::MAX_EVENTS_IN_EMAIL)
            : $events;
        $enrichedEvents = $this->enrichEvents($eventsToEnrich);

        $first = $events[0];
        $serviceId = (int) $first['service_id'];
        $service = Service::find($serviceId);

        $subject = '[Horizon Hub] Alert: ' . $alert->rule_type . ($service ? " - {$service->name}" : '');
        if ($count > 1) {
            $subject .= " ($count events)";
        }

        Log::info('Horizon Hub: sending alert email', ['alert_id' => $alert->id, 'to' => $to, 'event_count' => $count]);

        Mail::to($to)->send(new AlertBatchedMail($alert, $enrichedEvents, $service, $subject, $count));
    }

    /**
     * Enrich the events.
     *
     * @param array<int, array{service_id: int, job_uuid: string|null, triggered_at: string}> $events
     * @return array<int, array{service_id: int, job_uuid: string|null, triggered_at: string, job_class: string|null, queue: string|null, failed_at: string|null, exception: string|null, attempts: int|null}>
     */
    private function enrichEvents(array $events): array {
        $enriched = [];
        $jobUuids = \array_values(\array_filter(\array_column($events, 'job_uuid')));
        $service = null;
        if (! empty($events)) {
            $serviceId = (int) ($events[0]['service_id'] ?? 0);
            $service = Service::find($serviceId);
        }
        $jobs = (empty($jobUuids) || ! $service) ? \collect() : $this->getJobsForEmail($service, $jobUuids);

        foreach ($events as $event) {
            $jobUuid = isset($event['job_uuid']) ? (string) $event['job_uuid'] : null;
            $job = $jobUuid ? $jobs->get($jobUuid) : null;

            $jobClass = null;
            $queue = null;
            $failedAt = null;
            $exception = null;
            $attempts = null;

            if ($job) {
                $payload = $job->payload ?? [];
                $jobClass = $job->name ?? (isset($payload['displayName']) ? $payload['displayName'] : null);
                if ($jobClass === null && isset($payload['job'])) {
                    $jobClass = \is_string($payload['job']) ? $payload['job'] : 'Unknown';
                }
                $jobClass ??= 'Unknown';
                $queue = $job->queue ?? null;
                $failedAt = $job->failed_at ? $job->failed_at->format('Y-m-d H:i:s T') : null;
                $rawException = $job->exception ?? '';
                $exception = $rawException !== '' ? $this->truncateException($rawException) : null;
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
     * @param Service $service
     * @param array<int, string> $jobUuids
     * @return \Illuminate\Support\Collection<string, object{payload: array, name: string|null, queue: string|null, failed_at: Carbon|null, exception: string, attempts: int|null}>
     */
    private function getJobsForEmail(Service $service, array $jobUuids): \Illuminate\Support\Collection {
        $jobs = \collect();
        foreach ($jobUuids as $jobUuid) {
            if ($jobUuid === '') {
                continue;
            }
            $response = $this->horizonApi->getFailedJob($service, $jobUuid);
            $data = $response['data'] ?? null;
            if (! ($response['success'] ?? false) || ! \is_array($data)) {
                continue;
            }
            $payload = $data['payload'] ?? [];
            $name = $data['name'] ?? ($payload['displayName'] ?? null);
            if ($name === null && isset($payload['job'])) {
                $name = \is_string($payload['job']) ? $payload['job'] : 'Unknown';
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
                'payload' => $payload,
                'name' => $name,
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
     * Truncate the exception.
     *
     * @param string $text
     * @return string
     */
    private function truncateException(string $text): string {
        if (\strlen($text) <= self::EMAIL_EXCEPTION_MAX_LENGTH) {
            return $text;
        }

        $truncated = \substr($text, 0, self::EMAIL_EXCEPTION_MAX_LENGTH);
        $lastNewLine = \strrpos($truncated, "\n");
        if ($lastNewLine !== false) {
            $truncated = \substr($truncated, 0, $lastNewLine);
        }

        return \rtrim($truncated) . "\n\n…";
    }
}
