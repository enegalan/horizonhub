<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model {
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    private const CACHE_PREFIX = 'horizon_hub_setting_';

    private const CACHE_TTL_SECONDS = 3600;

    /**
     * Get a setting value by key. Returns null if not found.
     *
     * @param  mixed  $default
     */
    public static function get(string $key, mixed $default = null): mixed {
        $cacheKey = self::CACHE_PREFIX . $key;

        $value = Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($key) {
            $row = static::query()->where('key', $key)->first();

            return $row?->value;
        });

        if ($value === null) {
            return $default;
        }

        $decoded = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        return $value;
    }

    /**
     * Set a setting value. Arrays and scalars are JSON-encoded when appropriate.
     */
    public static function set(string $key, mixed $value): void {
        $serialized = is_array($value) || is_bool($value) ? json_encode($value) : (string) $value;

        static::query()->updateOrInsert(
            ['key' => $key],
            ['value' => $serialized]
        );

        Cache::forget(self::CACHE_PREFIX . $key);
    }
}
