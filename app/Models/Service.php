<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class Service extends Model {

    /**
     * The fillable attributes of the service.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'api_key',
        'base_url',
        'public_url',
        'status',
        'last_seen_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'api_key',
    ];

    /**
     * The casts of the service.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'api_key' => 'encrypted',
        'last_seen_at' => 'datetime',
    ];

    /**
     * Boot the service model.
     *
     * @return void
     */
    protected static function booted(): void {
        static::creating(function (Service $service): void {
            if ($service->api_key_hash !== null && $service->api_key_hash !== '') {
                return;
            }
            $plain = static::private__plainApiKeyFromStoredAttribute($service);
            if (\is_string($plain) && $plain !== '') {
                $service->api_key_hash = static::hashApiKey($plain);
            }
        });

        static::updating(function (Service $service): void {
            if (! $service->isDirty('api_key')) {
                return;
            }
            $plain = static::private__plainApiKeyFromStoredAttribute($service);
            if (\is_string($plain) && $plain !== '') {
                $service->api_key_hash = static::hashApiKey($plain);
            }
        });
    }

    /**
     * Resolve plaintext API key from the encrypted value in attributes (used during save events).
     *
     * @param Service $service
     * @return string|null
     */
    private static function private__plainApiKeyFromStoredAttribute(Service $service): ?string {
        $raw = $service->getAttributes()['api_key'] ?? null;
        if (! \is_string($raw) || $raw === '') {
            return null;
        }
        try {
            return Crypt::decryptString($raw);
        } catch (\Throwable $e) {
            return $raw;
        }
    }

    /**
     * SHA-256 hash of the plaintext API key for uniqueness and lookup (not reversible).
     *
     * @param string $apiKey
     * @return string
     */
    public static function hashApiKey(string $apiKey): string {
        return hash('sha256', $apiKey);
    }

    /**
     * Get the alerts of the service.
     *
     * @return HasMany
     */
    public function alerts(): HasMany {
        return $this->hasMany(Alert::class);
    }

    /**
     * Scope the query for online services.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeOnline($query): Builder {
        return $query->where('status', 'online');
    }

    /**
     * Scope the query for stand-by services.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeStandBy($query): Builder {
        return $query->where('status', 'stand_by');
    }

    /**
     * Scope the query for offline services.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeOffline($query): Builder {
        return $query->where('status', 'offline');
    }
}
