<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEventRequest extends FormRequest {
    /**
     * Determine if the user is authorized to make this request.
     * 
     * @internal This method returns true because the request authorization is delegated to the ValidateHubSignature middleware.
     *
     * @return bool
     */
    public function authorize(): bool {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string|Rule>>
     */
    public function rules(): array {
        return [
            'events' => ['sometimes', 'array'],
            'events.*.event_type' => ['required_with:events', 'string', 'in:JobProcessed,JobFailed,JobProcessing,SupervisorLooped,QueuePaused,QueueResumed'],
            'events.*.job_id' => ['required_with:events', 'nullable', 'string'],
            'events.*.queue' => ['required_with:events', 'string'],
            'events.*.status' => ['nullable', 'string'],
            'events.*.attempts' => ['nullable', 'integer', 'min:0'],
            'events.*.name' => ['nullable', 'string'],
            'events.*.payload' => ['nullable', 'array'],
            'events.*.queued_at' => ['nullable', 'string'],
            'events.*.processed_at' => ['nullable', 'string'],
            'events.*.failed_at' => ['nullable', 'string'],
            'events.*.runtime_seconds' => ['nullable', 'numeric', 'min:0'],
            'events.*.exception' => ['nullable', 'string'],
            'event_type' => ['required_without:events', 'string', 'in:JobProcessed,JobFailed,JobProcessing,SupervisorLooped,QueuePaused,QueueResumed'],
            'job_id' => [Rule::requiredIf(fn () => ! $this->has('events') && $this->input('event_type') !== 'SupervisorLooped'), 'nullable', 'string'],
            'queue' => ['required_without:events', 'string'],
            'status' => ['nullable', 'string'],
            'attempts' => ['nullable', 'integer', 'min:0'],
            'name' => ['nullable', 'string'],
            'payload' => ['nullable', 'array'],
            'queued_at' => ['nullable', 'string'],
            'processed_at' => ['nullable', 'string'],
            'failed_at' => ['nullable', 'string'],
            'runtime_seconds' => ['nullable', 'numeric', 'min:0'],
            'exception' => ['nullable', 'string'],
        ];
    }

    /**
     * Get the events from the request.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getEvents(): array {
        $payload = $this->validated();
        if (isset($payload['events']) && is_array($payload['events'])) {
            return $payload['events'];
        }
        unset($payload['events']);
        return $payload ? [array_merge(['attempts' => 0], $payload)] : [];
    }
}
