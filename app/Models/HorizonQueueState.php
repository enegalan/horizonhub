<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HorizonQueueState extends Model {
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'horizon_queue_states';

    /**
     * The fillable attributes of the queue state.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'service_id',
        'queue',
        'is_paused',
    ];

    /**
     * The casts of the queue state.
     *
     * @var array<string, string>
     */
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
