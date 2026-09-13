<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Scopes for models that carry an `enabled` boolean flag.
 */
trait Enablable
{
    /**
     * Scope to disabled records only.
     *
     * @param Builder $query The query.
     *
     * @return Builder The query.
     */
    public function scopeDisabled(Builder $query): Builder
    {
        return $query->where('enabled', false);
    }

    /**
     * Scope to enabled records only.
     *
     * @param Builder $query The query.
     *
     * @return Builder The query.
     */
    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('enabled', true);
    }
}
