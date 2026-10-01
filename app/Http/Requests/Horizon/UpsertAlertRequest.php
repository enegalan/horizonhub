<?php

namespace App\Http\Requests\Horizon;

use App\Enums\AlertRuleType;
use App\Models\Alert;
use App\Support\Alerts\AlertRuleCatalog;

/**
 * Form request for creating and updating an alert.
 *
 * The threshold fields are conditional on the selected rule type, so the rules
 * are resolved from the submitted `rule_type` rather than declared statically.
 */
class UpsertAlertRequest extends HorizonRequest
{
    /**
     * The resolved rule type for the submitted payload.
     */
    public function resolvedRuleType(): string
    {
        $ruleType = (string) $this->input('rule_type', '');

        if ($ruleType !== '') {
            return $ruleType;
        }

        return (string) \array_key_first(Alert::getProviders());
    }

    /**
     * Get the validation rules.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rule_type' => ['required', 'in:' . implode(',', AlertRuleType::values())],
            'service_ids' => ['required', 'array', 'min:1'],
            'service_ids.*' => ['integer', 'exists:services,id'],
            'job_patterns' => ['nullable', 'array'],
            'job_patterns.*' => ['nullable', 'string', 'max:255'],
            'queue_patterns' => ['nullable', 'array'],
            'queue_patterns.*' => ['nullable', 'string', 'max:255'],
            'thresholdCount' => $this->thresholdRule(['integer', 'min:1'], AlertRuleCatalog::ruleTypesRequiringCount()),
            'thresholdMinutes' => $this->thresholdRule(['integer', 'min:1'], AlertRuleCatalog::ruleTypesRequiringMinutes()),
            'thresholdSeconds' => $this->thresholdRule(['numeric', 'min:0.1'], AlertRuleCatalog::ruleTypesRequiringSeconds()),
            'provider_ids' => ['required', 'array', 'min:1'],
            'provider_ids.*' => ['integer', 'exists:notification_providers,id'],
            'email_interval_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
            'enabled' => ['required', 'boolean'],
            'name' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Build the rules for a threshold field.
     *
     * @param list<string> $base Rules applied when the field is required.
     * @param list<string> $requiredFor Rule types that require the field.
     *
     * @return list<string>
     */
    private function thresholdRule(array $base, array $requiredFor): array
    {
        $rules = ['nullable'];

        if (\in_array($this->resolvedRuleType(), $requiredFor, true)) {
            $rules = ['required'];
        }

        return \array_merge($rules, $base);
    }
}
