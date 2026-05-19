<?php

namespace App\Http\Requests\Horizon;

use App\Models\Service;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;

class ServiceRequest extends FormRequest
{
    /**
     * Parse `service_id` from the request and restrict to existing services.
     *
     * @return list<int>
     */
    public static function existingIdsFromRequest(Request $request): array
    {
        $raw = $request->input('service_id', []);

        if (empty($raw)) {
            return [];
        }

        $values = \is_array($raw) ? $raw : [$raw];
        $serviceIds = \array_values(\array_unique($values));

        if ($serviceIds === []) {
            return [];
        }

        $existing = Service::query()->whereIn('id', $serviceIds)->pluck('id')->all();
        \sort($existing);

        return $existing;
    }

    /**
     * Authorize the request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules for the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'service_id' => ['nullable', 'array'],
            'service_id.*' => ['integer', 'exists:services,id'],
            'service_tag' => ['nullable', 'array'],
            'service_tag.*' => ['string'],
        ];
    }
}
