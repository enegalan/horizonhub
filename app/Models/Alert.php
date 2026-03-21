<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Alert extends Model
{
    public const RULE_JOB_SPECIFIC_FAILURE = 'job_specific_failure';

    public const RULE_JOB_TYPE_FAILURE = 'job_type_failure';

    public const RULE_FAILURE_COUNT = 'failure_count';

    public const RULE_AVG_EXECUTION_TIME = 'avg_execution_time';

    public const RULE_QUEUE_BLOCKED = 'queue_blocked';

    public const RULE_WORKER_OFFLINE = 'worker_offline';

    public const RULE_SUPERVISOR_OFFLINE = 'supervisor_offline';

    public const RULE_HORIZON_OFFLINE = 'horizon_offline';

    /**
     * The fillable attributes of the alert.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'service_id',
        'rule_type',
        'threshold',
        'queue',
        'job_type',
        'enabled',
        'email_interval_minutes',
    ];

    /**
     * The casts of the alert.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'threshold' => 'array',
        'enabled' => 'boolean',
        'email_interval_minutes' => 'integer',
    ];

    /**
     * Get the service of the alert.
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * Get the alert logs of the alert.
     */
    public function alertLogs(): HasMany
    {
        return $this->hasMany(AlertLog::class);
    }

    /**
     * Get the notification providers of the alert.
     */
    public function notificationProviders(): BelongsToMany
    {
        return $this->belongsToMany(NotificationProvider::class, 'alert_notification_provider')
            ->withTimestamps();
    }
}
