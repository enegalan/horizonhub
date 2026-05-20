<?php

namespace App\Models;

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
     * Stable fingerprint for the services list card (SSE merge skip). Keep aligned with service-tbody fields.
     */
    public function getTurboStreamSig(): string
    {
        $tags = $this->tags ?? [];

        if (! \is_array($tags)) {
            $tags = [];
        }
        $tags = \array_values($tags);
        \sort($tags);

        $lastSeenKey = null;

        if ($this->last_seen_at !== null) {
            $lastSeenKey = $this->last_seen_at->copy()->startOfMinute()->toIso8601String();
        }

        $payload = [
            'id' => (int) $this->id,
            'name' => (string) ($this->name ?? ''),
            'base_url' => (string) ($this->base_url ?? ''),
            'public_url' => (string) ($this->public_url ?? ''),
            'status' => \strtolower((string) ($this->status ?? '')),
            'horizon_status' => (string) ($this->horizon_status ?? ''),
            'enabled' => (bool) ($this->enabled ?? true),
            'horizon_jobs_count' => (int) ($this->horizon_jobs_count ?? 0),
            'horizon_failed_jobs_count' => (int) ($this->horizon_failed_jobs_count ?? 0),
            'last_seen_minute' => $lastSeenKey,
            'tags' => $tags,
        ];

        return \hash('sha256', \json_encode($payload, \JSON_THROW_ON_ERROR));
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
        return $query->where(function (Builder $inner) use ($tags): void {
            foreach ($tags as $tag) {
                $inner->orWhereJsonContains('tags', $tag);
            }
        });
    }
}
