<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function service(): BelongsTo {
        return $this->belongsTo(Service::class);
    }

    public function scopeForService($query, $serviceId) {
        return $query->where('service_id', $serviceId);
    }

    public function scopeForQueue($query, $queue) {
        return $query->where('queue', $queue);
    }

    public function scopeForStatus($query, $status) {
        return $query->where('status', $status);
    }

    public function scopeForJobType($query, $name) {
        return $query->where('name', $name);
    }

    public function scopeInTimeRange($query, $from, $to) {
        return $query->whereBetween('created_at', [$from, $to]);
    }
}
