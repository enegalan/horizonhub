<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $horizon_failed_jobs_count
 * @property int $horizon_jobs_count
 * @property string|null $horizon_status
 */
class Service extends Model
{
    /**
     * The casts of the service.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'last_seen_at' => 'datetime',
    ];

    /**
     * The fillable attributes of the service.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'base_url',
        'public_url',
        'status',
        'last_seen_at',
    ];

    /**
     * Get the base URL of the service.
     */
    public function getBaseUrl(): string
    {
        return \rtrim($this->base_url ?? '', '/');
    }

    /**
     * Get the public URL of the service.
     */
    public function getPublicUrl(): string
    {
        $publicUrl = \rtrim($this->public_url ?? '', '/');

        if ($publicUrl !== '') {
            return $publicUrl;
        }

        return $this->getBaseUrl();
    }
}
