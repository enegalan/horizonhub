<?php

namespace App\Support\Services;

use App\Enums\TlsClientMode;
use App\Models\Service;
use App\Support\PathBuilder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ServiceTlsClientStorage
{
    /**
     * The disk to use for storing TLS client certificates.
     */
    public const DISK = 'local';

    /**
     * The name of the directory for storing TLS client certificates.
     */
    private const DIRECTORY = 'service-tls';

    /**
     * The name of the extracted certificate file that will be stored in the service directory.
     */
    private const EXTRACTED_CERT = 'extracted.crt';

    /**
     * The name of the extracted key file that will be stored in the service directory.
     */
    private const EXTRACTED_KEY = 'extracted.key';

    /**
     * Get the directory for a service.
     *
     * @param Service $service The service.
     *
     * @return string The directory for the service.
     */
    public static function directory(Service $service): string
    {
        return self::DIRECTORY . '/' . $service->id;
    }

    /**
     * Get the directory for the extracted certificate.
     *
     * @param Service $service The service.
     *
     * @return string The directory for the extracted certificate.
     */
    public static function extractedCertDirectory(Service $service): string
    {
        return self::directory($service) . '/' . self::EXTRACTED_CERT;
    }

    /**
     * Get the directory for the extracted key.
     *
     * @param Service $service The service.
     *
     * @return string The directory for the extracted key.
     */
    public static function extractedKeyDirectory(Service $service): string
    {
        return self::directory($service) . '/' . self::EXTRACTED_KEY;
    }

    /**
     * Forget the TLS client certificates for a service.
     *
     * @param Service $service The service.
     */
    public static function forget(Service $service): void
    {
        $directory = self::directory($service);

        if (Storage::disk(self::DISK)->exists($directory)) {
            Storage::disk(self::DISK)->deleteDirectory($directory);
        }
    }

    /**
     * Get the HTTP options for a service.
     *
     * @param Service $service The service.
     *
     * @return array<string, mixed>
     */
    public static function httpOptions(Service $service): array
    {
        $mode = $service->tls_client_mode;

        if ($mode === null) {
            return [];
        }

        if ($mode === TlsClientMode::P12) {
            $pem = self::private__ensurePemFromP12($service, $service->tls_client_passphrase);

            return [
                'cert' => $pem['cert'],
                'ssl_key' => $pem['key'],
            ];
        }

        $cert = PathBuilder::absolutePath($service->tls_client_cert_path, self::DISK);
        $key = PathBuilder::absolutePath($service->tls_client_key_path, self::DISK);

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
     * Sync the TLS client certificates for a service.
     *
     * @param Service $service The service.
     * @param string|null $mode The mode of the TLS client certificates.
     * @param UploadedFile|null $cert The certificate file.
     * @param UploadedFile|null $key The key file.
     * @param string|null $passphrase The passphrase for the PKCS#12 file.
     * @param bool $removeCert Whether to remove the certificate.
     * @param bool $removeKey Whether to remove the key.
     *
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

        // Remove the certificate if it is being removed and no new certificate is provided.
        if ($removeCert && $cert === null) {
            if ($certPath !== null) {
                $disk->delete($certPath);
            }
            $disk->delete([self::extractedCertDirectory($service), self::extractedKeyDirectory($service)]);
            $certPath = null;
        }

        // Remove the key if it is being removed and no new key is provided.
        if ($removeKey && $key === null && $keyPath !== null) {
            $disk->delete($keyPath);
            $keyPath = null;
        }

        // Upload the new certificate if it is provided.
        if ($cert !== null) {
            $name = self::private__safeFileName(
                $cert,
                $mode === TlsClientMode::P12->value ? 'client.p12' : 'client.crt',
            );

            // Delete the old certificate if it is being replaced.
            if ($certPath !== null && $certPath !== "$directory/$name") {
                $disk->delete($certPath);
            }

            // Delete the extracted certificate/key files.
            $disk->delete([self::extractedCertDirectory($service), self::extractedKeyDirectory($service)]);

            // Upload the new certificate.
            $disk->putFileAs($directory, $cert, $name);
            $certPath = "$directory/$name";
        }

        // Upload the new key if it is provided.
        if ($mode === TlsClientMode::Pem->value) {
            // Upload the new key if it is provided.
            if ($key !== null) {
                $name = self::private__safeFileName($key, 'client.key');

                // Delete the old key if it is being replaced.
                if ($keyPath !== null && $keyPath !== "$directory/$name") {
                    $disk->delete($keyPath);
                }

                // Upload the new key.
                $disk->putFileAs($directory, $key, $name);
                $keyPath = "$directory/$name";
            }
        } else {
            // Remove the key if it is being removed and no new key is provided.
            if ($keyPath !== null) {
                $disk->delete($keyPath);
            }
            $keyPath = null;

            // Extract the certificate/key from the PKCS#12 file.
            if ($certPath !== null) {
                // Set the certificate path for the service.
                $service->tls_client_cert_path = $certPath;

                // Try to extract the certificate/key from the PKCS#12 file.
                try {
                    self::private__ensurePemFromP12($service, $passphrase ?? $service->tls_client_passphrase);
                } catch (RuntimeException $e) {
                    // Delete the certificate/key files if the extraction failed.
                    if ($cert !== null) {
                        $disk->delete($certPath);
                        $disk->delete([self::extractedCertDirectory($service), self::extractedKeyDirectory($service)]);
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

    /**
     * Ensure a PEM file is created from a PKCS#12 file.
     *
     * @param Service $service The service.
     * @param string|null $passphrase The passphrase for the PKCS#12 file.
     *
     * @return array{cert: string, key: string} The PEM file and key.
     */
    private static function private__ensurePemFromP12(Service $service, ?string $passphrase = null): array
    {
        $p12 = PathBuilder::absolutePath($service->tls_client_cert_path, self::DISK);

        if (blank($p12) || ! \is_readable($p12)) {
            throw new RuntimeException('PKCS#12 file is missing or not readable.');
        }

        $dir = self::directory($service);
        $cert = (string) PathBuilder::absolutePath("$dir/" . self::EXTRACTED_CERT, self::DISK);
        $key = (string) PathBuilder::absolutePath("$dir/" . self::EXTRACTED_KEY, self::DISK);
        $passphrase = (string) $passphrase;

        // If the certificate/key are not readable, extract them from the PKCS#12 file.
        if (! \is_readable($cert) || ! \is_readable($key)) {
            $dir = \dirname($cert);

            if (! \is_dir($dir) && ! \mkdir($dir, 0700, true) && ! \is_dir($dir)) {
                throw new RuntimeException('Unable to create TLS storage directory.');
            }

            $ok = self::private__opensslExport($p12, $cert, ['-clcerts', '-nokeys'], $passphrase)
                && self::private__opensslExport($p12, $key, ['-nocerts', '-nodes'], $passphrase);

            if (! $ok) {
                @\unlink($cert);
                @\unlink($key);

                throw new RuntimeException(
                    'Could not parse PKCS#12 file. Check the passphrase, or re-export with a modern cipher (OpenSSL 3 rejects some legacy PKCS#12 algorithms).',
                );
            }

            @\chmod($cert, 0600);
            @\chmod($key, 0600);
        }

        return ['cert' => $cert, 'key' => $key];
    }

    /**
     * Export a PEM file from a PKCS#12 file.
     *
     * @param string $p12 The path to the PKCS#12 file.
     * @param string $out The path to the output file.
     * @param list<string> $args The arguments to pass to the OpenSSL command.
     * @param string $passphrase The passphrase for the PKCS#12 file.
     *
     * @return bool Whether the export was successful.
     */
    private static function private__opensslExport(
        string $p12,
        string $out,
        array $args,
        string $passphrase,
    ): bool {
        // export the PKCS#12 file with and without the legacy flag
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

    /**
     * Get a safe file name from a uploaded file.
     *
     * @param UploadedFile $file The uploaded file.
     * @param string $fallback The fallback file name.
     *
     * @return string The safe file name.
     */
    private static function private__safeFileName(UploadedFile $file, string $fallback): string
    {
        $name = \basename($file->getClientOriginalName());
        $name = \preg_replace('/[^\w.\-]+/u', '-', $name) ?? '';
        $name = \trim($name, '.-_');

        return $name !== '' ? $name : $fallback;
    }
}
