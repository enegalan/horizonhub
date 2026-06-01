<?php

namespace App\Http\Requests\Horizon;

use App\Models\NotificationProvider;
use App\Services\Notifiers\DiscordNotifierService;
use App\Services\Notifiers\EmailNotifierService;
use App\Services\Notifiers\SlackNotifierService;
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
        $notifierClass = NotificationProvider::getProviders()[$validated['type']] ?? null;

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
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:' . \implode(',', \array_keys(NotificationProvider::getProviders()))],
            'webhook_url' => [
                'required_if:type,' . SlackNotifierService::type(),
                'required_if:type,' . DiscordNotifierService::type(),
                'nullable',
                'url',
            ],
            'email_to' => ['required_if:type,' . EmailNotifierService::type(), 'nullable', 'string'],
        ];
    }
}
