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
    public const TYPE_SLACK = 'slack';

    /**
     * The type of the provider.
     *
     * @var string
     */
    public const TYPE_EMAIL = 'email';

    protected $fillable = [
        'name',
        'type',
        'config',
    ];

    protected $casts = [
        'config' => 'array',
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
     * Get the webhook URL of the provider.
     */
    public function getWebhookUrl(): string
    {
        return (string) ($this->config['webhook_url'] ?? '');
    }

    /**
     * Get the email recipients of the provider.
     *
     * @return array<int, string>
     */
    public function getToEmails(): array
    {
        if ($this->type !== self::TYPE_EMAIL) {
            return [];
        }

        $to = $this->config['to'] ?? [];
        if (\is_array($to)) {
            return \array_values(\array_filter(\array_map('trim', $to)));
        }
        if (\is_string($to) && \trim($to) !== '') {
            return [\trim($to)];
        }

        return [];
    }
}
