<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlertLog extends Model
{
    /**
     * The casts of the alert log.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'sent_at' => 'datetime',
        'job_uuids' => 'array',
    ];

    /**
     * The fillable attributes of the alert log.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'alert_id',
        'service_id',
        'trigger_count',
        'job_uuids',
        'status',
        'failure_message',
        'sent_at',
    ];

    /**
     * Get the alert of the alert log.
     */
    public function alert(): BelongsTo
    {
        return $this->belongsTo(Alert::class);
    }

    /**
     * Get the service of the alert log.
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
