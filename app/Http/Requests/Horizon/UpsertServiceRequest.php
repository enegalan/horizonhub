<?php

namespace App\Http\Requests\Horizon;

use App\Enums\TlsClientMode;
use App\Models\Service;
use App\Support\Services\ServiceTagNormalizer;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpsertServiceRequest extends HorizonRequest
{
    private const HEADER_NAME_PATTERN = '/^[!#$%&\'*+.^_`|~0-9A-Za-z-]+$/';

    private const TLS_CERT_EXTENSIONS = ['crt', 'pem', 'cer', 'p12', 'pfx'];

    private const TLS_KEY_EXTENSIONS = ['key', 'pem'];

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
            'tls_client_mode' => ['nullable', 'string', Rule::in(TlsClientMode::values())],
            'tls_client_cert' => ['nullable', 'file', 'max:1024'],
            'tls_client_key' => ['nullable', 'file', 'max:1024'],
            'tls_client_passphrase' => ['nullable', 'string', 'max:1024'],
            'tls_client_remove_cert' => ['nullable', 'boolean'],
            'tls_client_remove_key' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->private__validateHeaders($validator);
            $this->private__validateTlsClient($validator);
        });
    }

    protected function prepareForValidation(): void
    {
        $tags = $this->input('tags', null);

        if (\is_array($tags)) {
            $this->merge([
                'tags' => ServiceTagNormalizer::normalize($tags),
            ]);
        }

        $mode = $this->input('tls_client_mode');

        if ($mode === '' || $mode === 'none') {
            $this->merge(['tls_client_mode' => null]);
        }

        if ($this->exists('tls_client_passphrase') && \trim((string) $this->input('tls_client_passphrase')) === '') {
            $this->merge(['tls_client_passphrase' => null]);
        }
    }

    private function private__validateHeaders(Validator $validator): void
    {
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
    }

    private function private__validateTlsClient(Validator $validator): void
    {
        $mode = $this->input('tls_client_mode');

        if (blank($mode)) {
            return;
        }

        /** @var Service|null $existing */
        $existing = $this->route('service');
        $removeCert = $this->boolean('tls_client_remove_cert');
        $removeKey = $this->boolean('tls_client_remove_key');
        $hasCert = $existing !== null && filled($existing->tls_client_cert_path) && ! $removeCert;
        $hasKey = $existing !== null && filled($existing->tls_client_key_path) && ! $removeKey;
        $certFile = $this->file('tls_client_cert');
        $keyFile = $this->file('tls_client_key');

        if ($certFile !== null && $existing !== null && filled($existing->tls_client_cert_path) && ! $removeCert) {
            $validator->errors()->add(
                'tls_client_cert',
                'Remove the existing certificate before uploading a new one.',
            );
        }

        if ($keyFile !== null && $existing !== null && filled($existing->tls_client_key_path) && ! $removeKey) {
            $validator->errors()->add(
                'tls_client_key',
                'Remove the existing private key before uploading a new one.',
            );
        }

        if ($certFile !== null) {
            $extension = \strtolower((string) $certFile->getClientOriginalExtension());

            if (! \in_array($extension, self::TLS_CERT_EXTENSIONS, true)) {
                $validator->errors()->add(
                    'tls_client_cert',
                    'The certificate must be a .crt, .pem, .cer, .p12, or .pfx file.',
                );
            }
        }

        if ($keyFile !== null) {
            $extension = \strtolower((string) $keyFile->getClientOriginalExtension());

            if (! \in_array($extension, self::TLS_KEY_EXTENSIONS, true)) {
                $validator->errors()->add(
                    'tls_client_key',
                    'The private key must be a .key or .pem file.',
                );
            }
        }

        if ($mode === TlsClientMode::Pem->value) {
            if ($certFile === null && ! $hasCert) {
                $validator->errors()->add('tls_client_cert', 'A certificate file is required for PEM mode.');
            }

            if ($keyFile === null && ! $hasKey) {
                $validator->errors()->add('tls_client_key', 'A private key file is required for PEM mode.');
            }

            return;
        }

        if ($mode === TlsClientMode::P12->value && $certFile === null && ! $hasCert) {
            $validator->errors()->add('tls_client_cert', 'A PKCS#12 file is required for PKCS#12 mode.');
        }
    }
}
