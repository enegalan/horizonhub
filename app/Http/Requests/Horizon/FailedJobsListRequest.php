<?php

namespace App\Http\Requests\Horizon;

use App\Rules\RetryModalDateFilter;
use Illuminate\Validation\Rule;

/**
 * Form request for the failed jobs list.
 */
class FailedJobsListRequest extends HorizonRequest
{
    /**
     * The validation rules for the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'service_ids' => ['nullable', 'array'],
            'service_ids.*' => ['integer', 'exists:services,id'],
            'service_tag' => ['nullable', 'array'],
            'service_tag.*' => ['string'],
            'search' => ['nullable', 'string', 'max:255'],
            'date_from' => ['nullable', 'string', 'max:32', new RetryModalDateFilter],
            'date_to' => ['nullable', 'string', 'max:32', new RetryModalDateFilter],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'selection' => ['nullable', 'string', Rule::in(['page', 'all'])],
        ];
    }
}
