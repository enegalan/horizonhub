<?php

namespace App\Http\Requests\Horizon;

class RetryJobRequest extends HorizonRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'uuid' => ['required', 'string'],
            'service_id' => ['required', 'integer', 'exists:services,id'],
        ];
    }
}
