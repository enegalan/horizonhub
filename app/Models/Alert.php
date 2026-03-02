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

    public function service(): BelongsTo {
        return $this->belongsTo(Service::class);
    }

    public function alertLogs(): HasMany {
        return $this->hasMany(AlertLog::class);
    }

    public function notificationProviders(): BelongsToMany {
        return $this->belongsToMany(NotificationProvider::class, 'alert_notification_provider')
            ->withTimestamps();
    }
}
