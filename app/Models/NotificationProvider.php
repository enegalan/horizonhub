<?php

namespace App\Models;

use App\Services\Notifiers\DiscordNotifierService;
use App\Services\Notifiers\EmailNotifierService;
use App\Services\Notifiers\SlackNotifierService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class NotificationProvider extends Model
{
    protected $casts = [
        'config' => 'array',
    ];

    protected $fillable = [
        'name',
        'type',
        'config',
    ];

    /**
     * Get the providers.
     *
     * @return array<string, class-string>
     */
    public static function getProviders(): array
    {
        return [
            SlackNotifierService::type() => SlackNotifierService::class,
            DiscordNotifierService::type() => DiscordNotifierService::class,
            EmailNotifierService::type() => EmailNotifierService::class,
        ];
    }

    /**
     * Get the alerts of the provider.
     */
    public function alerts(): BelongsToMany
    {
        return $this->belongsToMany(Alert::class, 'alert_notification_provider')
            ->withTimestamps();
    }

    /**
     * Get the deliverable config for the provider.
     *
     * @return array<string, mixed>|null
     */
    public function deliverableConfig(): ?array
    {
        if ($this->usesWebhook()) {
            $webhookUrl = $this->getWebhookUrl();

            if ($webhookUrl === '') {
                return null;
            }

            return ['webhook_url' => $webhookUrl];
        }

        $to = $this->getToEmails();

        if ($to === []) {
            return null;
        }

        return ['to' => $to];
    }

    /**
     * Get the email recipients of the provider.
     *
     * @return array<int, string>
     */
    public function getToEmails(): array
    {
        $to = $this->config['to'] ?? [];

        if (! \is_array($to)) {
            return [];
        }

        return \array_values(\array_filter(\array_map('trim', $to)));
    }

    /**
     * Get the webhook URL of the provider.
     */
    public function getWebhookUrl(): string
    {
        return (string) ($this->config['webhook_url'] ?? '');
    }

    /**
     * Get provider metadata.
     *
     * @return array{label: string, icon: string, description: string, color: string}
     */
    public function meta(): array
    {
        $class = $this->notifierClass();

        if ($class === null) {
            throw new \RuntimeException('Unknown notifier class for type: ' . $this->type);
        }

        return $class::meta();
    }

    /**
     * Get the notifier class for the provider.
     *
     * @return class-string|null
     */
    public function notifierClass(): ?string
    {
        return self::getProviders()[$this->type] ?? null;
    }

    /**
     * Whether the provider delivers via mailing.
     */
    public function usesMailing(): bool
    {
        return \in_array($this->type, [EmailNotifierService::type()], true);
    }

    /**
     * Whether the provider delivers via webhook URL.
     */
    public function usesWebhook(): bool
    {
        return \in_array($this->type, [SlackNotifierService::type(), DiscordNotifierService::type()], true);
    }
}
