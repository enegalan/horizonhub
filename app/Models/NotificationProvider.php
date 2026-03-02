<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class NotificationProvider extends Model {
    public const TYPE_SLACK = 'slack';

    public const TYPE_EMAIL = 'email';

    protected $fillable = [
        'name',
        'type',
        'config',
    ];

    protected $casts = [
        'config' => 'array',
    ];

    public function alerts(): BelongsToMany {
        return $this->belongsToMany(Alert::class, 'alert_notification_provider')
            ->withTimestamps();
    }

    public function getWebhookUrl(): string {
        if ($this->type !== self::TYPE_SLACK) {
            return '';
        }

        return (string) ($this->config['webhook_url'] ?? '');
    }

    /**
     * @return array<int, string>
     */
    public function getToEmails(): array {
        if ($this->type !== self::TYPE_EMAIL) {
            return array();
        }

        $to = $this->config['to'] ?? array();
        if (is_array($to)) {
            return array_values(array_filter(array_map('trim', $to)));
        }
        if (is_string($to) && trim($to) !== '') {
            return array(trim($to));
        }

        return array();
    }
}
