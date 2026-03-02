<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HorizonFailedJob extends Model {
    protected $table = 'horizon_failed_jobs';

    protected $fillable = [
        'service_id',
        'job_uuid',
        'queue',
        'payload',
        'exception',
        'failed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'failed_at' => 'datetime',
    ];

    public function service(): BelongsTo {
        return $this->belongsTo(Service::class);
    }

    public function scopeForService($query, $serviceId) {
        return $query->where('service_id', $serviceId);
    }

    public function scopeForQueue($query, $queue) {
        return $query->where('queue', $queue);
    }

    public function getFormattedRuntime(): ?string {
        return null;
    }

    public function getStatusAttribute(): string {
        return 'failed';
    }

    public function getNameAttribute(): ?string {
        $p = $this->payload;
        return (is_array($p) && isset($p['displayName'])) ? $p['displayName'] : null;
    }
}
