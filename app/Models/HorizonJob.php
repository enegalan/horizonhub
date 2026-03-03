<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class HorizonJob extends Model {
    protected $table = 'horizon_jobs';

    protected $fillable = [
        'service_id',
        'job_uuid',
        'queue',
        'payload',
        'status',
        'attempts',
        'name',
        'queued_at',
        'processed_at',
        'failed_at',
        'runtime_seconds',
        'exception',
    ];

    protected $casts = [
        'payload' => 'array',
        'queued_at' => 'datetime',
        'processed_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    /**
     * Get the runtime seconds of the job.
     *
     * @return float|null
     */
    public function getRuntimeSeconds(): ?float {
        if (isset($this->runtime_seconds) && $this->runtime_seconds >= 0) {
            return round((float) $this->runtime_seconds, 3);
        }
        $start = $this->queued_at ?? $this->created_at;
        $end = $this->processed_at ?? $this->failed_at;
        if ($end === null || $start === null) {
            return null;
        }
        $seconds = $start->diffInMilliseconds($end, false) / 1000;
        return $seconds < 0 ? null : round($seconds, 3);
    }

    /**
     * Human-readable runtime: "85 ms" when &lt; 1s, "1.23 s" otherwise.
     *
     * @return string|null
     */
    public function getFormattedRuntime(): ?string {
        $seconds = $this->getRuntimeSeconds();
        if ($seconds === null) {
            return null;
        }
        if ($seconds < 1 && $seconds >= 0) {
            $ms = (int) round($seconds * 1000);
            return $ms . ' ms';
        }
        return number_format($seconds, 2) . ' s';
    }

    /**
     * Get the service of the job.
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
     * Scope the query for a specific status.
     *
     * @param Builder $query
     * @param string $status
     * @return Builder
     */
    public function scopeForStatus($query, $status) {
        return $query->where('status', $status);
    }

    /**
     * Scope the query for a specific job type.
     *
     * @param Builder $query
     * @param string $name
     * @return Builder
     */
    public function scopeForJobType($query, $name) {
        return $query->where('name', $name);
    }

    /**
     * Scope the query for a time range.
     *
     * @param Builder $query
     * @param Carbon $from
     * @param Carbon $to
     * @return Builder
     */
    public function scopeInTimeRange($query, $from, $to) {
        return $query->whereBetween('created_at', [$from, $to]);
    }
}
