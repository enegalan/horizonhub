<?php

namespace App\Http\Requests\Horizon;

use App\Models\Service;
use Illuminate\Foundation\Http\FormRequest;

class ServiceRequest extends FormRequest
{
    /**
     * Cached service model resolved from service_id.
     */
    private ?Service $resolvedService = null;

    /**
     * Authorize the request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize the `service_id` query parameter:
     * - empty/non-numeric => null
     * - numeric but not existing in `services` => null
     */
    protected function prepareForValidation(): void
    {
        $rawServiceId = $this->input('service_id');

        if ($rawServiceId === null || $rawServiceId === '') {
            $this->merge(['service_id' => null]);

            return;
        }

        if (! \is_numeric($rawServiceId)) {
            $this->merge(['service_id' => null]);

            return;
        }

        $serviceId = (int) $rawServiceId;
        if ($serviceId <= 0) {
            $this->merge(['service_id' => null]);

            return;
        }

        $this->resolvedService = Service::query()->find($serviceId);
        $this->merge(['service_id' => $this->resolvedService !== null ? $serviceId : null]);
    }

    /**
     * Get the validation rules for the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
        ];
    }

    /**
     * Get the service ID from the request.
     */
    public function getServiceId(): ?int
    {
        if ($this->validator === null) {
            $this->prepareForValidation();
        }

        $serviceId = $this->input('service_id');

        return $serviceId !== null ? (int) $serviceId : null;
    }

    /**
     * Get the service model from the request.
     */
    public function getService(): ?Service
    {
        if ($this->validator === null) {
            $this->prepareForValidation();
        }

        return $this->resolvedService;
    }
}
