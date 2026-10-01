<?php

namespace App\Http\Requests\Horizon;

/**
 * Form request for the retry batch action.
 */
class RetryBatchRequest extends HorizonRequest
{
    /**
     * The validation rules for the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'jobs' => ['required', 'array'],
            'jobs.*.id' => ['required', 'string'],
            'jobs.*.service_id' => ['required', 'integer', 'exists:services,id'],
        ];
    }
}
