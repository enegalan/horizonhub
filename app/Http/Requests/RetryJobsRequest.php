<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RetryJobsRequest extends FormRequest {
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array {
        return [
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'min:1'],
        ];
    }
}
