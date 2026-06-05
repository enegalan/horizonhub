<?php

namespace App\Http\Requests\Horizon;

use App\Contracts\HorizonHubStore;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ServiceRequest extends FormRequest
{
    /** @var HorizonHubStore */
    private $store;

    /**
     * The constructor.
     *
     * @param HorizonHubStore $store The horizon hub store.
     */
    public function __construct(HorizonHubStore $store)
    {
        parent::__construct();
        $this->store = $store;
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
            'service_id.*' => ['integer', Rule::in($this->store->allServiceIds())],
            'service_tag' => ['nullable', 'array'],
            'service_tag.*' => ['string'],
        ];
    }
}
