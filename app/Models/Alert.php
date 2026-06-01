<?php

namespace App\Models;

use App\Services\Alerts\Rules\Strategies\AvgExecutionTime;
use App\Services\Alerts\Rules\Strategies\FailureCount;
use App\Services\Alerts\Rules\Strategies\HorizonOffline;
use App\Services\Alerts\Rules\Strategies\QueueBlocked;
use App\Services\Alerts\Rules\Strategies\SupervisorOffline;
use App\Services\Alerts\Rules\Strategies\WorkerOffline;
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
        'enabled',
        'email_interval_minutes',
    ];

    /**
     * Get the rules.
     *
     * @return array<string, class-string>
     */
    public static function getProviders(): array
    {
        return [
            FailureCount::type() => FailureCount::class,
            AvgExecutionTime::type() => AvgExecutionTime::class,
            QueueBlocked::type() => QueueBlocked::class,
            WorkerOffline::type() => WorkerOffline::class,
            SupervisorOffline::type() => SupervisorOffline::class,
            HorizonOffline::type() => HorizonOffline::class,
        ];
    }

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
     * Get the job patterns.
     *
     * @return array The job patterns.
     */
    public function getJobPatterns(): array
    {
        return $this->threshold['job_patterns'] ?? [];
    }

    /**
     * Get the queue patterns.
     *
     * @return array The queue patterns.
     */
    public function getQueuePatterns(): array
    {
        return $this->threshold['queue_patterns'] ?? [];
    }

    /**
     * Get the threshold count.
     *
     * @return int The threshold count.
     */
    public function getThresholdCount(): int
    {
        return (int) ($this->threshold['count'] ?? config('horizonhub.alerts.default_count'));
    }

    /**
     * Get the threshold minutes.
     *
     * @return int The threshold minutes.
     */
    public function getThresholdMinutes(): int
    {
        return (int) ($this->threshold['minutes'] ?? config('horizonhub.alerts.default_minutes'));
    }

    /**
     * Get the threshold seconds.
     *
     * @return float The threshold seconds.
     */
    public function getThresholdSeconds(): float
    {
        return (float) ($this->threshold['seconds'] ?? config('horizonhub.alerts.default_seconds'));
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

    /**
     * The name attribute.
     *
     * @return Attribute<string, null>
     */
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
