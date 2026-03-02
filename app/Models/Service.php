<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function horizonJobs(): HasMany {
        return $this->hasMany(HorizonJob::class);
    }

    public function horizonFailedJobs(): HasMany {
        return $this->hasMany(HorizonFailedJob::class);
    }

    public function horizonSupervisorStates(): HasMany {
        return $this->hasMany(HorizonSupervisorState::class);
    }

    public function alerts(): HasMany {
        return $this->hasMany(Alert::class);
    }

    public function scopeOnline($query): HasMany {
        return $query->where('status', 'online');
    }

    public function scopeOffline($query): HasMany {
        return $query->where('status', 'offline');
    }
}
