<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

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

    /**
     * Get the service of the failed job.
     *
     * @return BelongsTo
     */
    public function service(): BelongsTo {
        return $this->belongsTo(Service::class);
    }

    /**
     * Scope the query for a specific service.
     *
     * @param Builder $query
     * @param int $serviceId
     * @return Builder
     */
    public function scopeForService($query, $serviceId) {
        return $query->where('service_id', $serviceId);
    }

    /**
     * Scope the query for a specific queue.
     *
     * @param Builder $query
     * @param string $queue
     * @return Builder
     */
    public function scopeForQueue($query, $queue) {
        return $query->where('queue', $queue);
    }

    /**
     * Get the formatted runtime of the failed job.
     *
     * @return string|null
     */
    public function getFormattedRuntime(): ?string {
        return null;
    }

    /**
     * Get the status of the failed job.
     *
     * @return string
     */
    public function getStatusAttribute(): string {
        return 'failed';
    }

    /**
     * Get the name of the failed job.
     *
     * @return string|null
     */
    public function getNameAttribute(): ?string {
        $p = $this->payload;
        return (\is_array($p) && isset($p['displayName'])) ? $p['displayName'] : null;
    }
}
