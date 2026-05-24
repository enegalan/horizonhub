<?php

namespace App\Support\Alerts;

use App\Models\Alert;
use App\Models\Service;

trait AlertRuleStrategySupport
{
    private function private__resolveServiceForEvaluation(int $serviceId): ?Service
    {
        $service = Service::find($serviceId);

        if (empty($service?->getBaseUrl())) {
            return null;
        }

        return $service;
    }

    private function private__thresholdMinutes(Alert $alert, ?int $default = null): int
    {
        $threshold = $alert->threshold ?? [];
        $fallback = $default ?? (int) config('horizonhub.alerts.default_minutes');

        return (int) ($threshold['minutes'] ?? $fallback);
    }
}
