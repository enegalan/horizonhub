<?php

namespace App\Services\Alerts;

use App\Models\Alert;
use App\Models\NotificationProvider;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AlertUpsertService
{
    /**
     * The maximum number of alert pattern lines.
     */
    private const ALERT_PATTERN_LINES_MAX = 20;

    /**
     * Build the form view variables.
     *
     * @return array<string, mixed>
     */
    public function buildFormViewVariables(Alert $alert): array
    {
        $services = Service::orderBy('name')->get();
        $providers = NotificationProvider::orderBy('type')->orderBy('name')->get();
        $ruleTypes = [
            Alert::RULE_FAILURE_COUNT => 'Failure count in window',
            Alert::RULE_AVG_EXECUTION_TIME => 'Avg execution time exceeded',
            Alert::RULE_QUEUE_BLOCKED => 'Queue blocked',
            Alert::RULE_WORKER_OFFLINE => 'Worker offline',
            Alert::RULE_SUPERVISOR_OFFLINE => 'Supervisor offline',
            Alert::RULE_HORIZON_OFFLINE => 'Horizon offline',
        ];
        $header = $alert->exists ? 'Edit alert' : 'New alert';

        return [
            'alert' => $alert,
            'services' => $services,
            'providers' => $providers,
            'ruleTypes' => $ruleTypes,
            'selectedProviderIds' => $alert->exists ? $alert->notificationProviders()->pluck('notification_providers.id')->all() : [],
            'selectedServiceIds' => $alert->exists ? $alert->scopedServiceIds() : [],
            'header' => $header,
        ];
    }

    /**
     * Validate the alert.
     *
     * @param  Alert|null  $alert
     * @return array{alert: array<string, mixed>, provider_ids: array<int>}
     */
    public function validateAlert(Request $request): array
    {
        $ruleTypes = [
            Alert::RULE_FAILURE_COUNT,
            Alert::RULE_AVG_EXECUTION_TIME,
            Alert::RULE_QUEUE_BLOCKED,
            Alert::RULE_WORKER_OFFLINE,
            Alert::RULE_SUPERVISOR_OFFLINE,
            Alert::RULE_HORIZON_OFFLINE,
        ];
        $baseRules = [
            'rule_type' => 'required|in:'.implode(',', $ruleTypes),
            'service_ids' => 'nullable|array',
            'service_ids.*' => 'integer|exists:services,id',
            'queue' => 'nullable|string|max:255',
            'job_type' => 'nullable|string|max:255',
            'job_patterns' => 'nullable|array|max:'.self::ALERT_PATTERN_LINES_MAX,
            'job_patterns.*' => 'nullable|string|max:255',
            'queue_patterns' => 'nullable|array|max:'.self::ALERT_PATTERN_LINES_MAX,
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
            ! empty($validated['job_type']) ? (string) $validated['job_type'] : null
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
        if (\in_array($ruleType, $patternRuleTypes, true)) {
            if ($jobPatterns !== []) {
                $threshold['job_patterns'] = $jobPatterns;
            }
        }
        $queuePatternsMerged = $queuePatterns;
        if ($queuePatternsMerged === [] && ! empty($validated['queue'])) {
            $queuePatternsMerged = [(string) $validated['queue']];
        }
        if (\in_array($ruleType, $queuePatternRuleTypes, true)) {
            if ($queuePatternsMerged !== []) {
                $threshold['queue_patterns'] = $queuePatternsMerged;
            }
        }

        $queueColumn = null;
        if (\in_array($ruleType, $queuePatternRuleTypes, true) && $queuePatternsMerged !== []) {
            $queueColumn = $queuePatternsMerged[0];
        } elseif (! empty($validated['queue'])) {
            $queueColumn = (string) $validated['queue'];
        }

        $jobTypeColumn = $this->jobTypeColumnFromPatterns($jobPatterns);
        $serviceIds = \array_values(\array_unique(\array_map('intval', $validated['service_ids'] ?? [])));
        \sort($serviceIds);

        $alertData = [
            'name' => $validated['name'] ?? null,
            'service_ids' => $serviceIds,
            'rule_type' => $ruleType,
            'threshold' => $threshold,
            'queue' => $queueColumn,
            'job_type' => $jobTypeColumn,
            'enabled' => (bool) $validated['enabled'],
            'email_interval_minutes' => (int) $validated['email_interval_minutes'],
        ];

        return [
            'alert' => $alertData,
            'provider_ids' => $validated['provider_ids'],
        ];
    }

    /**
     * Sanitize the pattern array.
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
        $out = \array_values(\array_unique($out));

        return \array_slice($out, 0, self::ALERT_PATTERN_LINES_MAX);
    }

    /**
     * Merge the job type into the patterns.
     *
     * @param  list<string>  $patterns
     * @return list<string>
     */
    public function mergeJobTypeIntoPatterns(array $patterns, ?string $jobType): array
    {
        if ($jobType === null || $jobType === '') {
            return $patterns;
        }
        $jt = \trim($jobType);
        if ($jt === '') {
            return $patterns;
        }
        if (\in_array($jt, $patterns, true)) {
            return $patterns;
        }
        $merged = $patterns;
        $merged[] = $jt;

        return \array_slice(\array_values(\array_unique($merged)), 0, self::ALERT_PATTERN_LINES_MAX);
    }

    /**
     * Get the job type column from the patterns.
     *
     * @param  list<string>  $patterns
     */
    public function jobTypeColumnFromPatterns(array $patterns): ?string
    {
        return $patterns === [] ? null : Str::limit(\implode(', ', $patterns), 252);
    }
}
