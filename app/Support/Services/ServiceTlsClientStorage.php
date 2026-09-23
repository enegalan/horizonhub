<?php

namespace App\Support\Services;

use App\Enums\TlsClientMode;
use App\Models\Service;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ServiceTlsClientStorage
{
    public const DISK = 'local';

    private const EXTRACTED_CERT = 'extracted.crt';

    private const EXTRACTED_KEY = 'extracted.key';

    public static function absolutePath(?string $relativePath): ?string
    {
        return blank($relativePath) ? null : Storage::disk(self::DISK)->path($relativePath);
    }

    public static function directory(Service $service): string
    {
        return 'service-tls/' . $service->id;
    }

    /**
     * @return array{cert: string, key: string}
     */
    public static function ensurePemFromP12(Service $service, ?string $passphrase = null): array
    {
        $p12 = self::absolutePath($service->tls_client_cert_path);

        if (blank($p12) || ! \is_readable($p12)) {
            throw new RuntimeException('PKCS#12 file is missing or not readable.');
        }

        $dir = self::directory($service);
        $cert = self::absolutePath($dir . '/' . self::EXTRACTED_CERT);
        $key = self::absolutePath($dir . '/' . self::EXTRACTED_KEY);

        if (! \is_readable((string) $cert) || ! \is_readable((string) $key)) {
            self::private__extractPem(
                $p12,
                (string) $cert,
                (string) $key,
                (string) ($passphrase ?? $service->tls_client_passphrase ?? ''),
            );
        }

        return ['cert' => (string) $cert, 'key' => (string) $key];
    }

    public static function forget(Service $service): void
    {
        $directory = self::directory($service);

        if (Storage::disk(self::DISK)->exists($directory)) {
            Storage::disk(self::DISK)->deleteDirectory($directory);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function httpOptions(Service $service): array
    {
        $mode = $service->tls_client_mode;

        if ($mode === null) {
            return [];
        }

        if ($mode === TlsClientMode::P12) {
            $pem = self::ensurePemFromP12($service);

            return [
                'cert' => $pem['cert'],
                'ssl_key' => $pem['key'],
            ];
        }

        $cert = self::absolutePath($service->tls_client_cert_path);
        $key = self::absolutePath($service->tls_client_key_path);

        if (blank($cert) || blank($key)) {
            return [];
        }

        $passphrase = $service->tls_client_passphrase;

        return [
            'cert' => $cert,
            'ssl_key' => blank($passphrase) ? $key : [$key, (string) $passphrase],
        ];
    }

    /**
     * @return array{tls_client_cert_path: string|null, tls_client_key_path: string|null}
     */
    public static function sync(
        Service $service,
        ?string $mode,
        ?UploadedFile $cert = null,
        ?UploadedFile $key = null,
        ?string $passphrase = null,
        bool $removeCert = false,
        bool $removeKey = false,
    ): array {
        if (blank($mode)) {
            self::forget($service);

            return ['tls_client_cert_path' => null, 'tls_client_key_path' => null];
        }

        $disk = Storage::disk(self::DISK);
        $directory = self::directory($service);
        $certPath = $service->tls_client_cert_path;
        $keyPath = $service->tls_client_key_path;

        if ($removeCert && $cert === null) {
            if ($certPath !== null) {
                $disk->delete($certPath);
            }
            $disk->delete([$directory . '/' . self::EXTRACTED_CERT, $directory . '/' . self::EXTRACTED_KEY]);
            $certPath = null;
        }

        if ($removeKey && $key === null && $keyPath !== null) {
            $disk->delete($keyPath);
            $keyPath = null;
        }

        if ($cert !== null) {
            $name = self::private__safeFileName(
                $cert,
                $mode === TlsClientMode::P12->value ? 'client.p12' : 'client.crt',
            );

            if ($certPath !== null && $certPath !== $directory . '/' . $name) {
                $disk->delete($certPath);
            }

            $disk->delete([$directory . '/' . self::EXTRACTED_CERT, $directory . '/' . self::EXTRACTED_KEY]);
            $disk->putFileAs($directory, $cert, $name);
            $certPath = $directory . '/' . $name;
        }

        if ($mode === TlsClientMode::Pem->value) {
            if ($key !== null) {
                $name = self::private__safeFileName($key, 'client.key');

                if ($keyPath !== null && $keyPath !== $directory . '/' . $name) {
                    $disk->delete($keyPath);
                }

                $disk->putFileAs($directory, $key, $name);
                $keyPath = $directory . '/' . $name;
            }
        } else {
            if ($keyPath !== null) {
                $disk->delete($keyPath);
            }
            $keyPath = null;

            if ($certPath !== null) {
                $service->tls_client_cert_path = $certPath;

                try {
                    self::ensurePemFromP12($service, $passphrase ?? $service->tls_client_passphrase);
                } catch (RuntimeException $e) {
                    if ($cert !== null) {
                        $disk->delete($certPath);
                        $disk->delete([$directory . '/' . self::EXTRACTED_CERT, $directory . '/' . self::EXTRACTED_KEY]);
                    }

                    throw ValidationException::withMessages([
                        'tls_client_cert' => $e->getMessage(),
                        'tls_client_passphrase' => 'Check the PKCS#12 passphrase.',
                    ]);
                }
            }
        }

        return [
            'tls_client_cert_path' => $certPath,
            'tls_client_key_path' => $keyPath,
        ];
    }

    private static function private__extractPem(
        string $p12,
        string $certOut,
        string $keyOut,
        string $passphrase,
    ): void {
        $dir = \dirname($certOut);

        if (! \is_dir($dir) && ! \mkdir($dir, 0700, true) && ! \is_dir($dir)) {
            throw new RuntimeException('Unable to create TLS storage directory.');
        }

        $ok = self::private__opensslExport($p12, $certOut, ['-clcerts', '-nokeys'], $passphrase)
            && self::private__opensslExport($p12, $keyOut, ['-nocerts', '-nodes'], $passphrase);

        if (! $ok) {
            @\unlink($certOut);
            @\unlink($keyOut);

            throw new RuntimeException(
                'Could not parse PKCS#12 file. Check the passphrase, or re-export with a modern cipher (OpenSSL 3 rejects some legacy PKCS#12 algorithms).',
            );
        }

        @\chmod($certOut, 0600);
        @\chmod($keyOut, 0600);
    }

    /**
     * @param list<string> $args
     */
    private static function private__opensslExport(
        string $p12,
        string $out,
        array $args,
        string $passphrase,
    ): bool {
        foreach ([[], ['-legacy']] as $legacy) {
            $result = Process::env(['HORIZONHUB_PKCS12_PASS' => $passphrase])->run(\array_merge(
                ['openssl', 'pkcs12', '-in', $p12, '-out', $out, '-passin', 'env:HORIZONHUB_PKCS12_PASS'],
                $args,
                $legacy,
            ));

            if ($result->successful() && \is_readable($out) && \filesize($out) > 0) {
                return true;
            }

            @\unlink($out);
        }

        return false;
    }

    private static function private__safeFileName(UploadedFile $file, string $fallback): string
    {
        $name = \basename($file->getClientOriginalName());
        $name = \preg_replace('/[^\w.\-]+/u', '-', $name) ?? '';
        $name = \trim($name, '.-_');

        return $name !== '' ? $name : $fallback;
    }
}
