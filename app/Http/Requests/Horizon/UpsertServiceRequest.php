<?php

namespace App\Http\Requests\Horizon;

use App\Models\Service;
use App\Support\Services\ServiceTagNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpsertServiceRequest extends FormRequest
{
    private const HEADER_NAME_PATTERN = '/^[!#$%&\'*+.^_`|~0-9A-Za-z-]+$/';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Service|null $service */
        $service = $this->route('service');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('services', 'name')->ignore($service?->id),
            ],
            'base_url' => ['required', 'url'],
            'public_url' => ['nullable', 'url'],
            'headers' => ['nullable', 'array'],
            'headers.*.name' => ['nullable', 'string'],
            'headers.*.value' => ['nullable', 'string'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $headers = $this->input('headers');

            if (! \is_array($headers)) {
                return;
            }

            $seen = [];
            $reserved = config('horizonhub.service_reserved_header_names');

            foreach ($headers as $index => $header) {
                if (! \is_array($header)) {
                    continue;
                }

                $name = \trim((string) ($header['name'] ?? ''));
                $value = isset($header['value']) ? \trim((string) $header['value']) : '';

                if (blank($name) && blank($value)) {
                    continue;
                }

                if (blank($name)) {
                    $validator->errors()->add("headers.$index.name", 'The name field is required when value is present.');

                    continue;
                }

                if (! \preg_match(self::HEADER_NAME_PATTERN, $name)) {
                    $validator->errors()->add("headers.$index.name", 'The name format is invalid.');

                    continue;
                }

                $lower = \strtolower($name);

                if (\in_array($lower, $reserved, true)) {
                    $validator->errors()->add("headers.$index.name", 'This header name is reserved and cannot be set manually.');

                    continue;
                }

                if (isset($seen[$lower])) {
                    $validator->errors()->add("headers.$index.name", 'Duplicated header name.');

                    continue;
                }

                $seen[$lower] = true;
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $tags = $this->input('tags', null);

        if (! \is_array($tags)) {
            return;
        }

        $this->merge([
            'tags' => ServiceTagNormalizer::normalizeList($tags),
        ]);
    }
}
