<?php

namespace App\Http\Requests\Horizon;

use App\Models\Service;
use Illuminate\Foundation\Http\FormRequest;

class ServiceRequest extends FormRequest {

    /**
     * Authorize the request.
     *
     * @return bool
     */
    public function authorize(): bool {
        return true;
    }

    /**
     * Normalize the `service_id` query parameter:
     * - empty/non-numeric => null
     * - numeric but not existing in `services` => null
     *
     * @return void
     */
    protected function prepareForValidation(): void {
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

        $exists = Service::query()->where('id', $serviceId)->exists();
        $this->merge(['service_id' => $exists ? $serviceId : null]);
    }

    /**
     * Get the validation rules for the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array {
        return [
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
        ];
    }

    /**
     * Get the service ID from the request.
     *
     * @return int|null
     */
    public function getServiceId(): ?int {
        if ($this->validator === null) {
            $this->prepareForValidation();
        }

        $serviceId = $this->input('service_id');

        return $serviceId !== null ? (int) $serviceId : null;
    }
}
