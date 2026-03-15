<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HorizonSupervisorState extends Model {
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'horizon_supervisor_states';

    /**
     * The fillable attributes of the supervisor state.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'service_id',
        'name',
        'last_seen_at',
    ];

    /**
     * The casts of the supervisor state.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'last_seen_at' => 'datetime',
    ];

    /**
     * Get the service of the supervisor state.
     *
     * @return BelongsTo
     */
    public function service(): BelongsTo {
        return $this->belongsTo(Service::class);
    }
}
