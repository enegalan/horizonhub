<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    /**
     * The casts of the service.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'last_seen_at' => 'datetime',
    ];

    /**
     * The fillable attributes of the service.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'base_url',
        'public_url',
        'status',
        'last_seen_at',
    ];

    /**
     * Get the alerts of the service.
     */
    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

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
}
