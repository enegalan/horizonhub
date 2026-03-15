<?php

namespace App\Services;

use App\Contracts\EmailAlertNotifier;
use App\Mail\AlertBatchedMail;
use App\Models\Alert;
use App\Models\HorizonJob;
use App\Models\Service;
use Illuminate\Support\Facades\DB;
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
     * @param int|null $jobId
     * @param array $config
     * @return void
     */
    public function send(Alert $alert, int $serviceId, ?int $jobId, array $config): void {
        $this->sendBatched($alert, [
            ['service_id' => $serviceId, 'job_id' => $jobId, 'triggered_at' => \now()->toIso8601String()],
        ], $config);
    }

    /**
     * Send a batched alert.
     *
     * @param Alert $alert
     * @param array<int, array{service_id: int, job_id: int|null, triggered_at: string}> $events
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
            $subject .= " ({$count} events)";
        }

        Log::info('Horizon Hub: sending alert email', ['alert_id' => $alert->id, 'to' => $to, 'event_count' => $count]);

        Mail::to($to)->send(new AlertBatchedMail($alert, $enrichedEvents, $service, $subject, $count));
    }

    /**
     * Enrich the events.
     *
     * @param array<int, array{service_id: int, job_id: int|null, triggered_at: string}> $events
     * @return array<int, array{service_id: int, job_id: int|null, triggered_at: string, job_class: string|null, queue: string|null, failed_at: string|null, exception: string|null, attempts: int|null}>
     */
    private function enrichEvents(array $events): array {
        $enriched = [];
        $jobIds = \array_values(\array_filter(\array_column($events, 'job_id')));
        $jobs = empty($jobIds) ? [] : $this->getJobsForEmail($jobIds);

        foreach ($events as $event) {
            $jobId = isset($event['job_id']) ? (int) $event['job_id'] : null;
            $job = $jobId ? $jobs->get($jobId) : null;

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
                'job_id' => $jobId,
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
     * Load jobs for email with exception truncated at DB level to avoid loading huge blobs.
     *
     * @param array<int, int> $jobIds
     * @return \Illuminate\Support\Collection<int, HorizonJob>
     */
    private function getJobsForEmail(array $jobIds): \Illuminate\Support\Collection {
        $max = self::EMAIL_EXCEPTION_MAX_LENGTH;
        $driver = DB::connection()->getDriverName();
        $exceptionExpr = ($driver === 'mysql' || $driver === 'pgsql')
            ? "LEFT(exception, {$max})"
            : "SUBSTR(exception, 1, {$max})";

        return HorizonJob::whereIn('id', $jobIds)
            ->selectRaw("id, name, queue, failed_at, attempts, {$exceptionExpr} as exception, payload")
            ->get()
            ->keyBy('id');
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
