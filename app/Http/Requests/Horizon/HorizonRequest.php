<?php

namespace App\Http\Requests\Horizon;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Base request for the Horizon panel.
 */
class HorizonRequest extends FormRequest
{
    /**
     * Authorize the request.
     */
    public function authorize(): bool
    {
        return true;
    }
}
