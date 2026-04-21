<?php

namespace App\Services\Notifiers;

use App\Contracts\SlackAlertNotifier;
use App\Models\Alert;
use App\Models\Service;
use App\Services\Horizon\HorizonApiProxyService;
use Illuminate\Support\Facades\Http;

class SlackNotifier extends AbstractAlertNotifier implements SlackAlertNotifier
{
    /**
     * Maximum number of events to include in the Slack message.
     *
     * @var int
     */
    private const MAX_EVENTS_IN_SLACK = 10;

    /**
     * Maximum length of exception text in the Slack message.
     *
     * @var int
     */
    private const SLACK_EXCEPTION_MAX_LENGTH = 500;

    /**
     * Construct the Slack notifier.
     */
    public function __construct(HorizonApiProxyService $horizonApi)
    {
        parent::__construct($horizonApi);
    }

    /**
     * Send a batched alert.
     *
     * @param Alert $alert The alert.
     * @param array<int, array{service_id: int, job_uuid: string|null, triggered_at: string}> $events The events.
     * @param array<string, mixed> $config The config.
     */
    public function sendBatched(Alert $alert, array $events, array $config): void
    {
        $webhookUrl = $config['webhook_url'] ?? '';

        if ($webhookUrl === '' || empty($events)) {
            return;
        }

        $first = $events[0];
        $serviceId = (int) $first['service_id'];
        $service = Service::find($serviceId);
        $count = \count($events);

        $lines = [
            \sprintf('🚨 *[Horizon Hub]* *Alert:* %s | *Service:* %s', $alert->rule_type, $service ? $service->name : (string) $serviceId),
        ];

        if ($alert->rule_type === 'failure_count') {
            $threshold = $alert->threshold ?? [];
            $thresholdCount = (int) ($threshold['count'] ?? config('horizonhub.alerts.default_count'));
            $thresholdMinutes = (int) ($threshold['minutes'] ?? config('horizonhub.alerts.default_minutes'));
            $queueName = $alert->queue ?? null;

            $condition = \sprintf(
                '📌 *Condition:* >= %d failures in last %d minutes',
                $thresholdCount,
                $thresholdMinutes,
            );

            if ($queueName !== null && $queueName !== '') {
                $condition .= " (_queue:_ $queueName)";
            }

            $lines[] = $condition;
        }

        if ($alert->rule_type === 'horizon_offline') {
            $lines[] = '📌 *Condition:* Horizon is not running for this service.';
            $lines[] = '🕐 *Detected at:* ' . $first['triggered_at'];
        } else {
            $lines[] = "📊 *Events:* $count";

            $enrichedEvents = $this->enrichEvents($events, self::MAX_EVENTS_IN_SLACK, self::SLACK_EXCEPTION_MAX_LENGTH);

            foreach ($enrichedEvents as $event) {
                $jobUuid = isset($event['job_uuid']) && $event['job_uuid'] !== '' ? $event['job_uuid'] : null;
                $triggeredAt = $event['triggered_at'] ?? '';
                $jobClass = $event['job_class'] ?? null;
                $queue = $event['queue'] ?? null;
                $failedAt = $event['failed_at'] ?? null;
                $attempts = $event['attempts'] ?? null;
                $exception = $event['exception'] ?? null;

                if ($jobClass !== null) {
                    $line = "❌ *Job:* $jobClass";
                } elseif ($jobUuid !== null) {
                    $line = "❌ *Job UUID:* $jobUuid";
                } else {
                    $line = '❌ *Job:* unknown';
                }

                if ($queue !== null) {
                    $line .= " (_queue:_ $queue)";
                }

                if ($triggeredAt !== '') {
                    $line .= " _at_ $triggeredAt";
                }

                $lines[] = $line;

                if ($failedAt !== null) {
                    $lines[] = "   🕐 *failed_at:* $failedAt";
                }

                if ($attempts !== null) {
                    $lines[] = "   🔄 *attempts:* $attempts";
                }

                if ($exception !== null && $exception !== '') {
                    $lines[] = "   ⚠️ *exception:* $exception";
                }
            }

            if ($count > self::MAX_EVENTS_IN_SLACK) {
                $lines[] = '📎 ... and *' . ($count - self::MAX_EVENTS_IN_SLACK) . '* more';
            }
        }
        $text = \implode("\n", $lines);

        Http::post($webhookUrl, [
            'text' => $text,
        ]);
    }
}
