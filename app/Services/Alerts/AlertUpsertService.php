<?php

namespace App\Services\Alerts;

use App\Models\Alert;
use App\Models\NotificationProvider;
use App\Models\Service;
use App\Support\Alerts\AlertRuleCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AlertUpsertService
{
    /**
     * Build the form view variables.
     *
     * @param Alert $alert The alert.
     *
     * @return array<string, mixed>
     */
    public function buildFormViewVariables(Alert $alert): array
    {
        $selectedIds = $alert->exists ? $alert->service_ids : [];
        $services = Service::query()
            ->where(function (Builder $query) use ($selectedIds): void {
                $query->enabled();

                if ($selectedIds !== []) {
                    $query->orWhere(fn (Builder $q) => $q->where('enabled', false)->whereIn('id', $selectedIds));
                }
            })
            ->orderBy('name')
            ->get();
        $providers = NotificationProvider::orderBy('type')->orderBy('name')->get();
        $ruleTypes = AlertRuleCatalog::ruleTypeLabels();
        $header = $alert->exists ? 'Edit alert' : 'New alert';

        return [
            'alert' => $alert,
            'services' => $services,
            'providers' => $providers,
            'ruleTypes' => $ruleTypes,
            'formRuleMetadata' => AlertRuleCatalog::formRuleMetadata(),
            'selectedProviderIds' => $alert->exists ? $alert->notificationProviders()->pluck('notification_providers.id')->all() : [],
            'selectedServiceIds' => $alert->service_ids,
            'header' => $header,
        ];
    }

    /**
     * Sanitize the pattern array.
     *
     * @param mixed $raw The raw value to sanitize.
     *
     * @return list<string>
     */
    public function sanitizePatternArray(mixed $raw): array
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

        return \array_values(\array_unique($out));
    }

    /**
     * Validate the alert.
     *
     * @param Request $request The request.
     *
     * @return array{alert: array<string, mixed>, provider_ids: array<int>}
     */
    public function validateAlert(Request $request): array
    {
        $ruleTypes = \array_keys(AlertRuleCatalog::ruleTypeLabels());
        $baseRules = [
            'rule_type' => 'required|in:' . implode(',', $ruleTypes),
            'service_ids' => 'required|array|min:1',
            'service_ids.*' => 'integer|exists:services,id',
            'job_patterns' => 'nullable|array',
            'job_patterns.*' => 'nullable|string|max:255',
            'queue_patterns' => 'nullable|array',
            'queue_patterns.*' => 'nullable|string|max:255',
            'thresholdCount' => 'nullable|integer|min:1',
            'thresholdMinutes' => 'nullable|integer|min:1',
            'thresholdSeconds' => 'nullable|numeric|min:0.1',
            'provider_ids' => 'required|array|min:1',
            'provider_ids.*' => 'integer|exists:notification_providers,id',
            'email_interval_minutes' => 'required|integer|min:0|max:1440',
            'enabled' => 'required|boolean',
            'name' => 'nullable|string|max:255',
        ];

        $ruleType = (string) $request->input('rule_type', Alert::RULE_FAILURE_COUNT);

        if (\in_array($ruleType, AlertRuleCatalog::ruleTypesRequiringMinutes(), true)) {
            $baseRules['thresholdMinutes'] = 'required|integer|min:1';
        }

        if (\in_array($ruleType, AlertRuleCatalog::ruleTypesRequiringCount(), true)) {
            $baseRules['thresholdCount'] = 'required|integer|min:1';
        }

        if (\in_array($ruleType, AlertRuleCatalog::ruleTypesRequiringSeconds(), true)) {
            $baseRules['thresholdSeconds'] = 'required|numeric|min:0.1';
        }

        $upsert = $this;
        $validator = Validator::make($request->all(), $baseRules);
        $validator->after(function (\Illuminate\Validation\Validator $v) use ($request, $upsert): void {
            $jobPatterns = $upsert->sanitizePatternArray($request->input('job_patterns'));
            $queuePatterns = $upsert->sanitizePatternArray($request->input('queue_patterns'));

            foreach ($jobPatterns as $p) {
                if (\strlen($p) > 255) {
                    $v->errors()->add('job_patterns', 'Each job pattern must be at most 255 characters.');

                    break;
                }
            }

            foreach ($queuePatterns as $p) {
                if (\strlen($p) > 255) {
                    $v->errors()->add('queue_patterns', 'Each queue name must be at most 255 characters.');

                    break;
                }
            }
        });

        $validated = $validator->validate();

        $jobPatterns = $this->sanitizePatternArray($request->input('job_patterns'));
        $queuePatterns = $this->sanitizePatternArray($request->input('queue_patterns'));

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

        if (\in_array($ruleType, AlertRuleCatalog::ruleTypesWithJobPatterns(), true) && ! empty($jobPatterns)) {
            $threshold['job_patterns'] = $jobPatterns;
        }

        if (\in_array($ruleType, AlertRuleCatalog::ruleTypesWithQueuePatterns(), true) && ! empty($queuePatterns)) {
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
}
