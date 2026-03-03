<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlertLog extends Model {
    protected $fillable = [
        'alert_id',
        'job_id',
        'service_id',
        'trigger_count',
        'job_ids',
        'status',
        'failure_message',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'job_ids' => 'array',
    ];
    /**
     * Get the alert of the alert log.
     *
     * @return BelongsTo
     */
    public function alert(): BelongsTo {
        return $this->belongsTo(Alert::class);
    }

    /**
     * Get the service of the alert log.
     *
     * @return BelongsTo
     */
    public function service(): BelongsTo {
        return $this->belongsTo(Service::class);
    }
}
