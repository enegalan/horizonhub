<?php

namespace App\Http\Requests\Horizon;

/**
 * Form request for the retry job action.
 */
class RetryJobRequest extends HorizonRequest
{
    /**
     * The validation rules for the request.
     *
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
