<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
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
     * The casts of the service.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'last_seen_at' => 'datetime',
    ];

    /**
     * Get the alerts of the service.
     */
    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    /**
     * Scope the query for online services.
     *
     * @param  Builder  $query
     */
    public function scopeOnline($query): Builder
    {
        return $query->where('status', 'online');
    }

    /**
     * Scope the query for stand-by services.
     *
     * @param  Builder  $query
     */
    public function scopeStandBy($query): Builder
    {
        return $query->where('status', 'stand_by');
    }

    /**
     * Scope the query for offline services.
     *
     * @param  Builder  $query
     */
    public function scopeOffline($query): Builder
    {
        return $query->where('status', 'offline');
    }
}
