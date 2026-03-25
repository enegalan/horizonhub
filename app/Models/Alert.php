<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Alert extends Model
{
    /**
     * Cached service names grouped by service id.
     *
     * @var array<int, string>|null
     */
    private static ?array $cachedServiceNamesById = null;

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
        'service_ids',
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
        'service_ids' => 'array',
        'enabled' => 'boolean',
        'email_interval_minutes' => 'integer',
    ];

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

    /**
     * Get explicit scoped service ids.
     *
     * @return array<int, int>
     */
    public function scopedServiceIds(): array
    {
        $ids = [];
        if (\is_array($this->service_ids)) {
            foreach ($this->service_ids as $id) {
                if (! \is_numeric($id) || (int) $id <= 0) {
                    continue;
                }
                $id = (int) $id;
                $ids[$id] = $id;
            }
        }

        return \array_values($ids);
    }

    /**
     * Get scoped service names preserving scoped service id order.
     *
     * @return array<int, string>
     */
    public function scopedServiceNames(): array
    {
        $scopedIds = $this->scopedServiceIds();
        if ($scopedIds === []) {
            return [];
        }

        if (self::$cachedServiceNamesById === null) {
            $namesById = Service::query()
                ->orderBy('name')
                ->pluck('name', 'id')
                ->all();
            self::$cachedServiceNamesById = $namesById;
        }

        $labels = [];
        foreach ($scopedIds as $serviceId) {
            $name = self::$cachedServiceNamesById[$serviceId] ?? null;
            if (! empty($name)) {
                $labels[] = $name;
            }
        }

        return $labels;
    }

    /**
     * Check whether this alert applies to the given service id.
     */
    public function appliesToServiceId(int $serviceId): bool
    {
        $scopedIds = $this->scopedServiceIds();
        if ($scopedIds === []) {
            return true;
        }

        return \in_array($serviceId, $scopedIds, true);
    }
}
