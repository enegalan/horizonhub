<?php

namespace App\Services\Notifiers;

use App\Models\Alert;
use Illuminate\Support\Facades\Http;

class DiscordNotifierService extends AbstractAlertNotifier
{
    /**
     * Discord embed color (blurple).
     */
    private const EMBED_COLOR = 0x5865F2;

    /**
     * Maximum embeds Discord allows per message.
     */
    private const MAX_EMBEDS = 10;

    /**
     * @return array{label: string, icon: string, description: string, color: string}
     */
    public static function meta(): array
    {
        return [
            'label' => 'Discord',
            'icon' => 'discord',
            'description' => 'Send alerts to a channel using a Discord webhook.',
            'color' => 'indigo',
        ];
    }

    /**
     * Normalize the config.
     *
     * @param array<string, mixed> $validated
     *
     * @return array<string, mixed>
     */
    public static function normalizedConfig(array $validated): array
    {
        return ['webhook_url' => (string) ($validated['webhook_url'] ?? '')];
    }

    public static function type(): string
    {
        return 'discord';
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

        Http::post($webhookUrl, $this->private__discordPayload($notification));
    }

    /**
     * Build a Discord event embed.
     *
     * @param array<string, mixed> $event The event.
     * @param bool $multi Whether the alert has multiple events.
     *
     * @return array<string, mixed>
     */
    private static function private__eventEmbed(array $event, bool $multi): array
    {
        $title = $event['job_class'] ?? $event['job_uuid'] ?? 'Unknown job';
        $lines = ['**' . ($multi ? "Event {$event['index']}" : 'Failed job') . ":** `{$title}`"];

        foreach (['Queue' => $event['queue'], 'Failed at' => $event['failed_at'], 'Attempts' => $event['attempts'], 'Triggered at' => $event['triggered_at'] ?: null] as $label => $value) {
            if (empty($value)) {
                continue;
            }
            $lines[] = "**{$label}:** {$value}";
        }

        if (! empty($event['exceptionPreview'])) {
            $lines[] = "**Exception:**\n```\n{$event['exceptionPreview']}\n```";

            if (! empty($event['exceptionExpandable']) && ! empty($event['jobUrl'])) {
                $lines[] = "[Show more]({$event['jobUrl']})";
            }
        }

        $embed = [
            'description' => self::private__truncate(\implode("\n", $lines), 4096),
            'color' => self::EMBED_COLOR,
        ];

        if (! empty($event['jobUrl'])) {
            $embed['url'] = $event['jobUrl'];
        }

        return $embed;
    }

    /**
     * Build a Discord embed field.
     *
     * @return array{name: string, value: string, inline: bool}
     */
    private static function private__field(string $name, string $value, bool $inline = true): array
    {
        return [
            'name' => $name,
            'value' => self::private__truncate($value, 1024),
            'inline' => $inline,
        ];
    }

    /**
     * Truncate text to Discord limits.
     */
    private static function private__truncate(string $value, int $maxLength): string
    {
        if (\strlen($value) <= $maxLength) {
            return $value;
        }

        return \substr($value, 0, $maxLength - 3) . '...';
    }

    /**
     * Build the Discord payload.
     *
     * @param array<string, mixed> $notification The notification.
     *
     * @return array<string, mixed>
     */
    private function private__discordPayload(array $notification): array
    {
        $fields = [
            self::private__field('Rule', (string) $notification['ruleLabel']),
            self::private__field('Service', (string) $notification['serviceName']),
            self::private__field('Condition', (string) $notification['condition'], false),
        ];

        if ($notification['totalEventCount'] > 1) {
            $fields[] = self::private__field('Events', (string) $notification['totalEventCount']);
        }

        $embeds = [[
            'title' => self::private__truncate("{$notification['appName']} – {$notification['alertName']}", 256),
            'url' => $notification['alertUrl'],
            'color' => self::EMBED_COLOR,
            'fields' => $fields,
        ]];

        if ($notification['hasJobDetails']) {
            $multi = $notification['totalEventCount'] > 1;
            $maxEventEmbeds = self::MAX_EMBEDS - 2;
            $events = $notification['events'];
            $truncated = \count($events) > $maxEventEmbeds;

            foreach (\array_slice($events, 0, $maxEventEmbeds) as $event) {
                $embeds[] = self::private__eventEmbed($event, $multi);
            }

            if ($truncated) {
                $remaining = \count($events) - $maxEventEmbeds;
                $embeds[] = [
                    'description' => "… and {$remaining} more event(s). [View alert]({$notification['alertUrl']})",
                    'color' => self::EMBED_COLOR,
                ];
            }
        } elseif ($notification['detectedAt'] !== null) {
            $embeds[0]['description'] = "Detected at {$notification['detectedAt']}";
        }

        $links = ["[View alert]({$notification['alertUrl']})"];

        if (! empty($notification['serviceUrl'])) {
            $links[] = "[View service]({$notification['serviceUrl']})";
        }

        $embeds[] = [
            'description' => \implode(' · ', $links),
            'footer' => ['text' => "Sent at {$notification['sentAt']}"],
            'color' => self::EMBED_COLOR,
        ];

        $content = [
            "{$notification['appName']} alert: {$notification['alertName']}",
            $notification['ruleLabel'],
            $notification['serviceName'],
        ];

        if ($notification['totalEventCount'] > 1) {
            $content[] = "{$notification['totalEventCount']} events";
        }

        return [
            'content' => \implode(' | ', $content),
            'embeds' => $embeds,
        ];
    }
}
