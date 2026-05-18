<?php

namespace App\Models;

use App\Services\Horizon\ServiceTagNormalizer;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property bool $enabled
 * @property int $horizon_failed_jobs_count
 * @property int $horizon_jobs_count
 * @property list<string> $tags
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
     * Get the base URL of the service.
     */
    public function getBaseUrl(): string
    {
        return \rtrim($this->base_url ?? '', '/');
    }

    /**
     * Get the public URL of the service.
     */
    public function getPublicUrl(): string
    {
        $publicUrl = \rtrim($this->public_url ?? '', '/');

        if ($publicUrl !== '') {
            return $publicUrl;
        }

        return $this->getBaseUrl();
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
    public function scopeDisabled($query)
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
    public function scopeEnabled($query)
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
        if ($tags === []) {
            return $query;
        }

        return $query->where(function (Builder $inner) use ($tags): void {
            foreach ($tags as $tag) {
                $inner->orWhereJsonContains('tags', $tag);
            }
        });
    }

    /**
     * Normalize tags before persistence.
     */
    protected static function booted(): void
    {
        static::saving(function (Service $service): void {
            $service->tags = ServiceTagNormalizer::normalizeList($service->tags ?? []);
        });
    }
}
