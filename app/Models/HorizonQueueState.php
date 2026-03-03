<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HorizonQueueState extends Model {
    protected $table = 'horizon_queue_states';

    protected $fillable = [
        'service_id',
        'queue',
        'is_paused',
    ];

    protected $casts = [
        'is_paused' => 'boolean',
    ];

    /**
     * Get the service of the queue state.
     *
     * @return BelongsTo
     */
    public function service(): BelongsTo {
        return $this->belongsTo(Service::class);
    }
}
