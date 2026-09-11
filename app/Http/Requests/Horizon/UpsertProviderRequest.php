<?php

namespace App\Http\Requests\Horizon;

use App\Enums\NotificationProviderType;
use App\Models\NotificationProvider;
use Illuminate\Foundation\Http\FormRequest;

class UpsertProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function normalizedProviderData(): array
    {
        $validated = $this->validated();
        $notifierClass = (new NotificationProvider(['type' => $validated['type']]))->notifierClass();

        if ($notifierClass === null) {
            \abort(422, 'Invalid provider type.');
        }

        return [
            'name' => $validated['name'],
            'type' => $validated['type'],
            'config' => $notifierClass::normalizedConfig($validated),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $providers = NotificationProviderType::values();

        $webhookRules = ['nullable', 'url'];
        $mailingRules = [];

        foreach ($providers as $type) {
            $provider = new NotificationProvider(['type' => $type]);

            if ($provider->usesWebhook()) {
                $webhookRules[] = "required_if:type,$type";
            }

            if ($provider->usesMailing()) {
                $mailingRules[] = "required_if:type,$type";
            }
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:' . implode(',', $providers)],
            'webhook_url' => $webhookRules,
            'email_to' => $mailingRules,
        ];
    }
}
