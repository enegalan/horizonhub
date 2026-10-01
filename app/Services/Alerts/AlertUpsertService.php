<?php

namespace App\Services\Alerts;

use App\Http\Requests\Horizon\UpsertAlertRequest;
use App\Support\Alerts\AlertRuleCatalog;

class AlertUpsertService
{
    /**
     * Build the persistence payload from a validated request.
     *
     * @param UpsertAlertRequest $request The validated request.
     *
     * @return array{alert: array<string, mixed>, provider_ids: array<int>}
     */
    public function buildUpsertData(UpsertAlertRequest $request): array
    {
        $validated = $request->validated();
        $ruleType = $request->resolvedRuleType();

        $threshold = [];

        if (\in_array($ruleType, AlertRuleCatalog::ruleTypesRequiringMinutes(), true)) {
            $threshold['minutes'] = (int) ($validated['thresholdMinutes'] ?? 0);
        }

        if (\in_array($ruleType, AlertRuleCatalog::ruleTypesRequiringCount(), true)) {
            $threshold['count'] = (int) ($validated['thresholdCount'] ?? 0);
        }

        if (\in_array($ruleType, AlertRuleCatalog::ruleTypesRequiringSeconds(), true)) {
            $threshold['seconds'] = (float) ($validated['thresholdSeconds'] ?? 0.0);
        }

        $jobPatterns = $this->private__sanitizePatternArray($validated['job_patterns'] ?? null);

        if (\in_array($ruleType, AlertRuleCatalog::ruleTypesWithJobPatterns(), true) && $jobPatterns !== []) {
            $threshold['job_patterns'] = $jobPatterns;
        }

        $queuePatterns = $this->private__sanitizePatternArray($validated['queue_patterns'] ?? null);

        if (\in_array($ruleType, AlertRuleCatalog::ruleTypesWithQueuePatterns(), true) && $queuePatterns !== []) {
            $threshold['queue_patterns'] = $queuePatterns;
        }

        $serviceIds = \array_values(\array_unique(\array_map('intval', $validated['service_ids'] ?? [])));
        $serviceIds = \array_values(\array_filter($serviceIds, static fn (int $serviceId): bool => $serviceId > 0));
        \sort($serviceIds);

        return [
            'alert' => [
                'name' => $validated['name'] ?? null,
                'service_ids' => $serviceIds,
                'rule_type' => $ruleType,
                'threshold' => $threshold,
                'enabled' => (bool) $validated['enabled'],
                'email_interval_minutes' => (int) $validated['email_interval_minutes'],
            ],
            'provider_ids' => $validated['provider_ids'],
        ];
    }

    /**
     * Sanitize the pattern array.
     *
     * @param mixed $raw The raw value to sanitize.
     *
     * @return list<string>
     */
    private function private__sanitizePatternArray(mixed $raw): array
    {
        if (! \is_array($raw)) {
            return [];
        }
        $out = [];

        foreach ($raw as $v) {
            if (! \is_string($v)) {
                continue;
            }
            $t = \trim($v);

            if ($t !== '') {
                $out[] = $t;
            }
        }

        return $out;
    }
}
