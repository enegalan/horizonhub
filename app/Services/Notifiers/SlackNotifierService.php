<?php

namespace App\Services\Notifiers;

use App\Enums\NotificationProviderType;
use App\Models\Alert;

class SlackNotifierService extends AbstractAlertNotifier
{
    /**
     * Get the metadata.
     *
     * @return array{label: string, icon: string, description: string, color: string}
     */
    public static function meta(): array
    {
        return [
            'label' => 'Slack',
            'icon' => 'slack',
            'description' => 'Send alerts to a channel using an incoming webhook.',
            'color' => 'violet',
        ];
    }

    /**
     * Get the type.
     */
    public static function type(): NotificationProviderType
    {
        return NotificationProviderType::Slack;
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
        $this->sendWebhook($alert, $events, $config, fn (array $notification): array => $this->private__slackPayload($notification));
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
        $detail = self::buildEventDetail($event, $multi);
        $lines = ['*' . $detail['heading'] . ":* `{$detail['title']}`"];

        foreach ($detail['rows'] as [$label, $value]) {
            $lines[] = "*{$label}:* {$value}";
        }

        if (! empty($detail['exceptionPreview'])) {
            $lines[] = "*Exception:*\n```{$detail['exceptionPreview']}```";

            if (! empty($detail['exceptionExpandable']) && ! empty($detail['jobUrl'])) {
                $lines[] = "<{$detail['jobUrl']}|Show more>";
            }
        }

        $section = ['type' => 'section', 'text' => ['type' => 'mrkdwn', 'text' => \implode("\n", $lines)]];

        if (! empty($detail['jobUrl'])) {
            $section['accessory'] = self::private__slackButton('View job', $detail['jobUrl'], 'view_job_' . $event['index']);
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
