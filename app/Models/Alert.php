<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Alert extends Model {
    protected $fillable = [
        'name',
        'service_id',
        'rule_type',
        'threshold',
        'queue',
        'job_type',
        'notification_channels',
        'enabled',
    ];

    protected $casts = [
        'threshold' => 'array',
        'notification_channels' => 'array',
        'enabled' => 'boolean',
    ];

    /**
     * Get the service of the alert.
     *
     * @return BelongsTo
     */
    public function service(): BelongsTo {
        return $this->belongsTo(Service::class);
    }

    /**
     * Get the alert logs of the alert.
     *
     * @return HasMany
     */
    public function alertLogs(): HasMany {
        return $this->hasMany(AlertLog::class);
    }

    /**
     * Get the notification providers of the alert.
     *
     * @return BelongsToMany
     */
    public function notificationProviders(): BelongsToMany {
        return $this->belongsToMany(NotificationProvider::class, 'alert_notification_provider')
            ->withTimestamps();
    }
}
