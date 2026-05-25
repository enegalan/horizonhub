<?php

namespace App\Support\Alerts;

use App\Models\Alert;

trait AlertRuleStrategy
{
    /**
     * Get the threshold minutes.
     *
     * @param Alert $alert The alert.
     * @param int|null $default The default value.
     *
     * @return int The threshold minutes.
     */
    private function private__thresholdMinutes(Alert $alert, ?int $default = null): int
    {
        $threshold = $alert->threshold ?? [];
        $fallback = $default ?? (int) config('horizonhub.alerts.default_minutes');

        return (int) ($threshold['minutes'] ?? $fallback);
    }
}
