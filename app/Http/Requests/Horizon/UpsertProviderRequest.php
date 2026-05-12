<?php

namespace App\Http\Requests\Horizon;

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

        if ($validated['type'] === NotificationProvider::TYPE_EMAIL) {
            $emails = \array_values(\array_filter(\array_map('trim', \explode(',', (string) ($validated['email_to'] ?? '')))));

            foreach ($emails as $email) {
                if (! \filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    \abort(422, 'One or more email addresses are invalid.');
                }
            }

            if ($emails === []) {
                \abort(422, 'Email recipients are required.');
            }

            $config = ['to' => $emails];
        } else {
            $config = ['webhook_url' => (string) ($validated['webhook_url'] ?? '')];
        }

        return [
            'name' => $validated['name'],
            'type' => $validated['type'],
            'config' => $config,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:slack,email'],
            'webhook_url' => ['required_if:type,slack', 'nullable', 'url'],
            'email_to' => ['required_if:type,email', 'nullable', 'string'],
        ];
    }
}
