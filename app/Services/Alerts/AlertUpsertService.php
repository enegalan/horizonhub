<?php

namespace App\Services\Alerts;

use App\Models\Alert;
use App\Models\NotificationProvider;
use App\Models\Service;
use App\Support\Alerts\AlertRuleCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

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
        $services = Service::query()->enabled()->orderBy('name')->get();
        $providers = NotificationProvider::orderBy('type')->orderBy('name')->get();
        $ruleTypes = AlertRuleCatalog::ruleTypeLabels();
        $header = $alert->exists ? 'Edit alert' : 'New alert';

        return [
            'alert' => $alert,
            'services' => $services,
            'providers' => $providers,
            'ruleTypes' => $ruleTypes,
            'selectedProviderIds' => $alert->exists ? $alert->notificationProviders()->pluck('notification_providers.id')->all() : [],
            'selectedServiceIds' => $alert->service_ids,
            'header' => $header,
        ];
    }

    /**
     * Merge the job type into the patterns.
     *
     * @param list<string> $patterns The patterns.
     * @param string|null $jobType The job type.
     *
     * @return list<string>
     */
    public function mergeJobTypeIntoPatterns(array $patterns, ?string $jobType): array
    {
        $jt = ! empty($jobType) ? \trim($jobType) : '';

        if (blank($jt) || \in_array($jt, $patterns, true)) {
            return $patterns;
        }
        $merged = $patterns;
        $merged[] = $jt;

        return \array_values(\array_unique($merged));
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
            'queue' => 'nullable|string|max:255',
            'job_type' => 'nullable|string|max:255',
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

        if (\in_array($ruleType, [Alert::RULE_FAILURE_COUNT, Alert::RULE_AVG_EXECUTION_TIME, Alert::RULE_QUEUE_BLOCKED, Alert::RULE_WORKER_OFFLINE, Alert::RULE_SUPERVISOR_OFFLINE, Alert::RULE_HORIZON_OFFLINE], true)) {
            $baseRules['thresholdMinutes'] = 'required|integer|min:1';
        }

        if ($ruleType === Alert::RULE_FAILURE_COUNT) {
            $baseRules['thresholdCount'] = 'required|integer|min:1';
        }

        if ($ruleType === Alert::RULE_AVG_EXECUTION_TIME) {
            $baseRules['thresholdSeconds'] = 'required|numeric|min:0.1';
        }

        $upsert = $this;
        $validator = Validator::make($request->all(), $baseRules);
        $validator->after(function (\Illuminate\Validation\Validator $v) use ($request, $upsert): void {
            $jobPatterns = $upsert->sanitizePatternArray($request->input('job_patterns'));
            $queuePatterns = $upsert->sanitizePatternArray($request->input('queue_patterns'));

            $jobTypeInput = \trim((string) $request->input('job_type', ''));
            $jobPatternsMerged = $upsert->mergeJobTypeIntoPatterns($jobPatterns, $jobTypeInput !== '' ? $jobTypeInput : null);

            foreach ($jobPatternsMerged as $p) {
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
        $jobPatterns = $this->mergeJobTypeIntoPatterns(
            $jobPatterns,
            ! empty($validated['job_type']) ? (string) $validated['job_type'] : null,
        );
        $queuePatterns = $this->sanitizePatternArray($request->input('queue_patterns'));

        $threshold = [];

        if (\in_array($ruleType, [Alert::RULE_FAILURE_COUNT, Alert::RULE_AVG_EXECUTION_TIME, Alert::RULE_QUEUE_BLOCKED, Alert::RULE_WORKER_OFFLINE, Alert::RULE_SUPERVISOR_OFFLINE, Alert::RULE_HORIZON_OFFLINE], true)) {
            $threshold['minutes'] = (int) ($validated['thresholdMinutes'] ?? 0);
        }

        if ($ruleType === Alert::RULE_FAILURE_COUNT) {
            $threshold['count'] = (int) ($validated['thresholdCount'] ?? 0);
        }

        if ($ruleType === Alert::RULE_AVG_EXECUTION_TIME) {
            $threshold['seconds'] = (float) ($validated['thresholdSeconds'] ?? 0.0);
        }

        $patternRuleTypes = [Alert::RULE_FAILURE_COUNT, Alert::RULE_AVG_EXECUTION_TIME];
        $queuePatternRuleTypes = [Alert::RULE_FAILURE_COUNT, Alert::RULE_AVG_EXECUTION_TIME, Alert::RULE_QUEUE_BLOCKED];

        if (\in_array($ruleType, $patternRuleTypes, true) && ! empty($jobPatterns)) {
            $threshold['job_patterns'] = $jobPatterns;
        }

        $queuePatternsMerged = $queuePatterns;

        if (empty($queuePatternsMerged) && ! empty($validated['queue'])) {
            $queuePatternsMerged = [(string) $validated['queue']];
        }

        if (\in_array($ruleType, $queuePatternRuleTypes, true) && ! empty($queuePatternsMerged)) {
            $threshold['queue_patterns'] = $queuePatternsMerged;
        }

        $queueColumn = null;

        if (\in_array($ruleType, $queuePatternRuleTypes, true) && ! empty($queuePatternsMerged)) {
            $queueColumn = $queuePatternsMerged[0];
        } elseif (! empty($validated['queue'])) {
            $queueColumn = (string) $validated['queue'];
        }

        $jobTypeColumn = null;

        if (! empty($jobPatterns)) {
            $jobTypeColumn = Str::limit(\implode(', ', $jobPatterns), 252);
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
                'queue' => $queueColumn,
                'job_type' => $jobTypeColumn,
                'enabled' => (bool) $validated['enabled'],
                'email_interval_minutes' => (int) $validated['email_interval_minutes'],
            ],
            'provider_ids' => $validated['provider_ids'],
        ];
    }
}
