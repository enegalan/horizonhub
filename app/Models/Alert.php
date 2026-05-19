<?php

namespace App\Models;

use Database\Factories\AlertFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property list<int> $service_ids
 * @property list<string> $service_tags
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
        'service_tags' => '[]',
    ];

    /**
     * The casts of the alert.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'threshold' => 'array',
        'service_ids' => 'array',
        'service_tags' => 'array',
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
        'service_tags',
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
        if ($this->service_ids === [] && $this->service_tags === []) {
            return true;
        }

        if ($this->service_ids !== [] && \in_array($serviceId, $this->service_ids, true)) {
            return true;
        }

        if ($this->service_tags === []) {
            return false;
        }

        $service = Service::query()->whereKey($serviceId)->first(['id', 'tags', 'enabled']);

        if ($service === null || ! $service->enabled) {
            return false;
        }

        return [] === \array_diff($this->service_tags, $service->tags);
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
        if ($this->service_ids === [] && $this->service_tags === []) {
            return Service::query()->enabled()->pluck('id')->map(static fn ($id): int => (int) $id)->all();
        }

        $ids = [];

        if ($this->service_ids !== []) {
            $explicit = Service::query()
                ->enabled()
                ->whereIn('id', $this->service_ids)
                ->pluck('id')
                ->map(static fn ($id): int => (int) $id)
                ->all();
            $ids = \array_merge($ids, $explicit);
        }

        if ($this->service_tags !== []) {
            $tagQuery = Service::query()->enabled();

            foreach ($this->service_tags as $tag) {
                $tagQuery->whereJsonContains('tags', $tag);
            }

            $ids = \array_merge($ids, $tagQuery->pluck('id')->map(static fn ($id): int => (int) $id)->all());
        }

        $ids = \array_values(\array_unique($ids));
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
}
