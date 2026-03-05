<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Service extends Model {
    protected $fillable = [
        'name',
        'api_key',
        'base_url',
        'status',
        'last_seen_at',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
    ];

    /**
     * Get the jobs of the service.
     *
     * @return HasMany
     */
    public function horizonJobs(): HasMany {
        return $this->hasMany(HorizonJob::class);
    }

    /**
     * Get the failed jobs of the service.
     *
     * @return HasMany
     */
    public function horizonFailedJobs(): HasMany {
        return $this->hasMany(HorizonFailedJob::class);
    }

    /**
     * Get the supervisor states of the service.
     *
     * @return HasMany
     */
    public function horizonSupervisorStates(): HasMany {
        return $this->hasMany(HorizonSupervisorState::class);
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
