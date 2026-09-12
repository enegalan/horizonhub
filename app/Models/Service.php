<?php

namespace App\Models;

use App\Enums\ServiceStatus;
use App\Services\Horizon\HorizonClientApiService;
use App\Services\Horizon\HorizonClientCacheService;
use App\Support\Horizon\ClientResponse;
use App\Support\Horizon\StatsReader;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * @property bool $enabled
 * @property int $horizon_failed_jobs_count
 * @property int $horizon_jobs_count
 * @property list<string> $tags
 * @property string $base_url
 * @property string $public_url
 * @property string|null $horizon_status
 */
class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory;

    /**
     * Default attribute values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'enabled' => true,
        'tags' => '[]',
    ];

    /**
     * The casts of the service.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'enabled' => 'boolean',
        'last_seen_at' => 'datetime',
        'tags' => 'array',
        'status' => ServiceStatus::class,
    ];

    /**
     * The fillable attributes of the service.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'base_url',
        'public_url',
        'status',
        'enabled',
        'last_seen_at',
        'tags',
    ];

    /**
     * Get services by IDs.
     *
     * @param list<int|string> $serviceIds The service IDs.
     * @param bool $enabledOnly Whether to only return enabled services.
     * @param bool $orderByName Whether to order by name.
     * @param list<string> $selectColumns The columns to select.
     *
     * @return Collection<int, Service>
     */
    public static function getServices(
        array $serviceIds = [],
        bool $enabledOnly = true,
        bool $orderByName = false,
        array $selectColumns = [],
    ): Collection {
        $servicesQuery = $enabledOnly ? static::enabled() : static::query();

        if (! empty($serviceIds)) {
            $ids = [];

            foreach ($serviceIds as $serviceId) {
                if (! \is_numeric($serviceId) || \intval($serviceId) <= 0) {
                    continue;
                }
                $ids[] = (int) $serviceId;
            }

            if (empty($ids)) {
                return new Collection;
            }

            $servicesQuery->whereIn('id', $ids);
        }

        if ($orderByName) {
            $servicesQuery->orderBy('name');
        }

        if (! empty($selectColumns)) {
            return $servicesQuery->get($selectColumns);
        }

        return $servicesQuery->get();
    }

    /**
     * Check whether the upstream API recently timed out and the timeout
     * configuration should be reviewed.
     *
     * @return bool Whether the service has timeout advice.
     */
    public function hasTimeoutAdvice(): bool
    {
        return Cache::has(HorizonClientCacheService::timeoutAdviceCacheKey($this));
    }

    /**
     * HTTP headers sent on outbound requests to this service.
     *
     * @return HasMany<ServiceHeader, $this>
     */
    public function headers(): HasMany
    {
        return $this->hasMany(ServiceHeader::class)->orderBy('id');
    }

    /**
     * Scope to disabled services only.
     *
     * @param Builder<Service> $query
     *
     * @return Builder<Service>
     */
    public function scopeDisabled(Builder $query): Builder
    {
        return $query->where('enabled', false);
    }

    /**
     * Scope to enabled services only.
     *
     * @param Builder<Service> $query
     *
     * @return Builder<Service>
     */
    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('enabled', true);
    }

    /**
     * Scope to services matching tag filters.
     *
     * @param Builder<Service> $query
     * @param list<string> $tags
     *
     * @return Builder<Service>
     */
    public function scopeMatchingTags(Builder $query, array $tags): Builder
    {
        return $query->where(function (Builder $inner) use ($tags): void {
            foreach ($tags as $tag) {
                $inner->orWhereJsonContains('tags', $tag);
            }
        });
    }

    /**
     * Attach Horizon stats to the service.
     */
    public function withHorizonStats(): void
    {
        if (! $this->enabled) {
            $this->horizon_failed_jobs_count = 0;
            $this->horizon_jobs_count = 0;
            $this->horizon_status = null;

            return;
        }

        $stats = StatsReader::summary(ClientResponse::data(HorizonClientApiService::getStats($this)));

        $this->horizon_failed_jobs_count = $stats['failedJobs'];
        $this->horizon_jobs_count = $stats['recentJobs'];
        $this->horizon_status = $stats['status'];
    }

    /**
     * Normalize and validate base_url before every save.
     */
    protected static function booted(): void
    {
        static::saving(static function (Service $service): void {
            if (blank($service->base_url)) {
                throw ValidationException::withMessages([
                    'base_url' => ['The base URL is required.'],
                ]);
            }
        });
    }

    /**
     * Get the base URL of the service.
     *
     * @return Attribute<string, mixed>
     */
    protected function baseUrl(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => \rtrim((string) $value, '/'),
            set: fn ($value) => ! empty($value) ? \rtrim($value, '/') : null,
        );
    }

    /**
     * Get the horizon failed jobs count of the service.
     *
     * @return Attribute<int, mixed>
     */
    protected function horizonFailedJobsCount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ?? 0,
        );
    }

    /**
     * Get the horizon jobs count of the service.
     *
     * @return Attribute<int, mixed>
     */
    protected function horizonJobsCount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ?? 0,
        );
    }

    /**
     * Get the horizon status of the service.
     *
     * @return Attribute<string, mixed>
     */
    protected function horizonStatus(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? \strtolower($value) : 'offline',
        );
    }

    /**
     * Get the public URL of the service.
     *
     * @return Attribute<string, mixed>
     */
    protected function publicUrl(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                $value = \rtrim($value ?? '', '/');

                if (! blank($value)) {
                    return $value;
                }

                return $this->base_url;
            },
            set: fn ($value) => ! empty($value) ? \rtrim($value, '/') : null,
        );
    }

    /**
     * Get the tags of the service.
     *
     * @return Attribute<list<string>, mixed>
     */
    protected function tags(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                $value = json_decode($value, true) ?: [];

                sort($value);

                return $value;
            },
        );
    }
}
