<?php

namespace App\Services\Notifiers;

use App\Models\Alert;
use App\Services\Horizon\HorizonApiProxyService;
use Illuminate\Support\Facades\Http;

class SlackNotifier extends AbstractAlertNotifier
{
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

        if (blank($webhookUrl) || empty($events)) {
            return;
        }

        $notification = $this->buildNotification($alert, $events);

        Http::post($webhookUrl, $this->private__slackPayload($notification));
    }

    /**
     * Build the Slack button.
     *
     * @param string $label The label.
     * @param string $url The URL.
     * @param string $actionId The action ID.
     *
     * @return array The Slack button.
     */
    private static function private__slackButton(string $label, string $url, string $actionId): array
    {
        return [
            'type' => 'button',
            'text' => ['type' => 'plain_text', 'text' => $label, 'emoji' => true],
            'url' => $url,
            'action_id' => $actionId,
        ];
    }

    /**
     * Build the Slack event.
     *
     * @param array $event The event.
     * @param bool $multi Whether the event is multi.
     *
     * @return array The Slack event.
     */
    private static function private__slackEvent(array $event, bool $multi): array
    {
        $title = $event['job_class'] ?? $event['job_uuid'] ?? 'Unknown job';
        $lines = ['*' . ($multi ? "Event {$event['index']}" : 'Failed job') . ":* `{$title}`"];

        foreach (['Queue' => $event['queue'], 'Failed at' => $event['failed_at'], 'Attempts' => $event['attempts'], 'Triggered at' => $event['triggered_at'] ?: null] as $label => $value) {
            if (empty($value)) {
                continue;
            }
            $lines[] = "*{$label}:* {$value}";
        }

        if (! empty($event['exceptionPreview'])) {
            $lines[] = "*Exception:*\n```{$event['exceptionPreview']}```";

            if (! empty($event['exceptionExpandable']) && ! empty($event['jobUrl'])) {
                $lines[] = "<{$event['jobUrl']}|Show more>";
            }
        }

        $section = ['type' => 'section', 'text' => ['type' => 'mrkdwn', 'text' => \implode("\n", $lines)]];

        if (! empty($event['jobUrl'])) {
            $section['accessory'] = self::private__slackButton('View job', $event['jobUrl'], 'view_job_' . $event['index']);
        }

        return $section;
    }

    /**
     * Build the Slack payload.
     *
     * @param array $notification The notification.
     *
     * @return array The Slack payload.
     */
    private function private__slackPayload(array $notification): array
    {
        $fields = [
            ['type' => 'mrkdwn', 'text' => "*Rule:*\n{$notification['ruleLabel']}"],
            ['type' => 'mrkdwn', 'text' => "*Service:*\n{$notification['serviceName']}"],
            ['type' => 'mrkdwn', 'text' => "*Condition:*\n{$notification['condition']}"],
        ];

        if ($notification['totalEventCount'] > 1) {
            $fields[] = ['type' => 'mrkdwn', 'text' => "*Events:*\n{$notification['totalEventCount']}"];
        }

        $blocks = [
            [
                'type' => 'header',
                'text' => ['type' => 'plain_text', 'text' => "{$notification['appName']} – {$notification['alertName']}", 'emoji' => true],
            ],
            ['type' => 'section', 'fields' => $fields],
        ];

        if ($notification['hasJobDetails']) {
            $blocks[] = ['type' => 'divider'];
            $multi = $notification['totalEventCount'] > 1;

            foreach ($notification['events'] as $event) {
                $blocks[] = self::private__slackEvent($event, $multi);
            }
        } elseif ($notification['detectedAt'] !== null) {
            $blocks[] = ['type' => 'context', 'elements' => [['type' => 'mrkdwn', 'text' => "Detected at {$notification['detectedAt']}"]]];
        }

        $actions = [self::private__slackButton('View alert', $notification['alertUrl'], 'view_alert')];

        if (! empty($notification['serviceUrl'])) {
            $actions[] = self::private__slackButton('View service', $notification['serviceUrl'], 'view_service');
        }

        $blocks[] = ['type' => 'divider'];
        $blocks[] = ['type' => 'actions', 'elements' => $actions];
        $blocks[] = ['type' => 'context', 'elements' => [['type' => 'mrkdwn', 'text' => "Sent at {$notification['sentAt']}"]]];

        $text = [
            "{$notification['appName']} alert: {$notification['alertName']}",
            $notification['ruleLabel'],
            $notification['serviceName'],
            $notification['condition'],
        ];

        if ($notification['totalEventCount'] > 1) {
            $text[] = "{$notification['totalEventCount']} events";
        }

        $text[] = $notification['alertUrl'];

        return ['blocks' => $blocks, 'text' => \implode(' | ', $text)];
    }
}
