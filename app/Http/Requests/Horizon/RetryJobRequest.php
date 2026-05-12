<?php

namespace App\Http\Requests\Horizon;

use Illuminate\Foundation\Http\FormRequest;

class RetryJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

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
