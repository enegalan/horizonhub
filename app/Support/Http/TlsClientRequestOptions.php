<?php

namespace App\Support\Http;

use App\Enums\TlsClientMode;
use App\Models\Service;
use App\Support\Services\ServiceTlsClientStorage;

class TlsClientRequestOptions
{
    /**
     * Build Guzzle TLS client options for a service's mTLS configuration.
     *
     * @return array<string, mixed>
     */
    public static function forService(Service $service): array
    {
        $mode = $service->tls_client_mode;

        if ($mode === null) {
            return [];
        }

        $passphrase = $service->tls_client_passphrase;

        return match ($mode) {
            TlsClientMode::Pem => self::private__pemOptions(
                ServiceTlsClientStorage::absolutePath($service->tls_client_cert_path),
                ServiceTlsClientStorage::absolutePath($service->tls_client_key_path),
                $passphrase,
            ),
            // OpenSSL 3 often cannot load legacy PKCS#12 ciphers via curl/Guzzle.
            // Extract PEM once (with openssl -legacy when needed) and use that.
            TlsClientMode::P12 => self::private__p12Options($service, $passphrase),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private static function private__p12Options(Service $service, ?string $passphrase): array
    {
        $extracted = ServiceTlsClientStorage::ensurePemFromP12($service, $passphrase);

        return self::private__pemOptions($extracted['cert'], $extracted['key'], null);
    }

    /**
     * @return array<string, mixed>
     */
    private static function private__pemOptions(?string $certPath, ?string $keyPath, ?string $passphrase): array
    {
        if (blank($certPath) || blank($keyPath)) {
            return [];
        }

        return [
            'cert' => $certPath,
            'ssl_key' => blank($passphrase)
                ? $keyPath
                : [$keyPath, (string) $passphrase],
        ];
    }
}
