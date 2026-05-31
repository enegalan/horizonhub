<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class NotificationProvider extends Model
{
    /**
     * The type of the provider.
     *
     * @var string
     */
    public const TYPE_DISCORD = 'discord';

    /**
     * The type of the provider.
     *
     * @var string
     */
    public const TYPE_EMAIL = 'email';

    /**
     * The type of the provider.
     *
     * @var string
     */
    public const TYPE_SLACK = 'slack';

    protected $casts = [
        'config' => 'array',
    ];

    protected $fillable = [
        'name',
        'type',
        'config',
    ];

    /**
     * Get the alerts of the provider.
     */
    public function alerts(): BelongsToMany
    {
        return $this->belongsToMany(Alert::class, 'alert_notification_provider')
            ->withTimestamps();
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
     * Whether the provider delivers via webhook URL.
     */
    public function usesWebhook(): bool
    {
        return \in_array($this->type, [self::TYPE_SLACK, self::TYPE_DISCORD], true);
    }
}
