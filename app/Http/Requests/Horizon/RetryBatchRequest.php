<?php

namespace App\Http\Requests\Horizon;

class RetryBatchRequest extends HorizonRequest
{
    /**
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
