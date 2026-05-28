<?php

namespace App\Models;

use Database\Factories\AlertFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $name
 * @property list<int> $service_ids
 */
class Alert extends Model
{
    /** @use HasFactory<AlertFactory> */
    use HasFactory;

    public const RULE_AVG_EXECUTION_TIME = 'avg_execution_time';

    public const RULE_FAILURE_COUNT = 'failure_count';

    public const RULE_HORIZON_OFFLINE = 'horizon_offline';

    public const RULE_QUEUE_BLOCKED = 'queue_blocked';

    public const RULE_SUPERVISOR_OFFLINE = 'supervisor_offline';

    public const RULE_WORKER_OFFLINE = 'worker_offline';

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'service_ids' => '[]',
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
     * The fillable attributes of the alert.
     *
     * @var list<string>
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
     * Get the alert logs of the alert.
     */
    public function alertLogs(): HasMany
    {
        return $this->hasMany(AlertLog::class);
    }

    /**
     * Check whether this alert applies to the given service id.
     */
    public function appliesToServiceId(int $serviceId): bool
    {
        return \in_array($serviceId, $this->service_ids, true);
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
     * Service ids this alert should evaluate against.
     *
     * @return list<int>
     */
    public function resolvedServiceIds(): array
    {
        if (empty($this->service_ids)) {
            return [];
        }

        $ids = Service::query()
            ->enabled()
            ->whereIn('id', $this->service_ids)
            ->pluck('id')
            ->all();

        \sort($ids);

        return $ids;
    }

    /**
     * Get the job patterns.
     *
     * @param array|null $default The default value.
     *
     * @return array The job patterns.
     */
    public function getJobPatterns(?array $default = null): array
    {
        return $this->threshold['job_patterns'] ?? $default ?? [];
    }

    /**
     * Get the queue patterns.
     *
     * @param array|null $default The default value.
     *
     * @return array The queue patterns.
     */
    public function getQueuePatterns(?array $default = null): array
    {
        return $this->threshold['queue_patterns'] ?? $default ?? [];
    }

    /**
     * Get the threshold count.
     *
     * @param int|null $default The default value.
     *
     * @return int The threshold count.
     */
    public function getThresholdCount(?int $default = null): int
    {
        return (int) ($this->threshold['count'] ?? $default ?? config('horizonhub.alerts.default_count'));
    }

    /**
     * Get the threshold seconds.
     *
     * @param float|null $default The default value.
     *
     * @return float The threshold seconds.
     */
    public function getThresholdSeconds(?int $default = null): float
    {
        return (float) ($this->threshold['seconds'] ?? $default ?? config('horizonhub.alerts.default_seconds'));
    }

    /**
     * Get the threshold minutes.
     *
     * @param int|null $default The default value.
     *
     * @return int The threshold minutes.
     */
    public function getThresholdMinutes(?int $default = null): int
    {
        return (int) ($this->threshold['minutes'] ?? $default ?? config('horizonhub.alerts.default_minutes'));
    }

    /**
     * Scope to enabled alerts only.
     *
     * @param Builder<Alert> $query
     *
     * @return Builder<Alert>
     */
    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    protected function name(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value): string {
                $name = \is_string($value) ? \trim($value) : '';

                if ($name !== '') {
                    return $name;
                }

                if (! $this->exists) {
                    return '';
                }

                return "Alert #{$this->id}";
            },
            set: static function (mixed $value): ?string {
                if (! \is_string($value)) {
                    return null;
                }

                $name = \trim($value);

                return $name !== '' ? $name : null;
            },
        );
    }
}
