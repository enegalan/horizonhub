<?php

namespace App\Support\Services;

use App\Enums\TlsClientMode;
use App\Models\Service;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ServiceTlsClientStorage
{
    public const DISK = 'local';

    public const EXTRACTED_CERT_NAME = 'extracted.crt';

    public const EXTRACTED_KEY_NAME = 'extracted.key';

    /**
     * Resolve a stored relative path to an absolute filesystem path.
     */
    public static function absolutePath(?string $relativePath): ?string
    {
        if (blank($relativePath)) {
            return null;
        }

        return Storage::disk(self::DISK)->path($relativePath);
    }

    /**
     * Relative directory for a service's TLS client files.
     */
    public static function directory(Service $service): string
    {
        return 'service-tls/' . $service->id;
    }

    /**
     * Ensure a PKCS#12 bundle is extracted to PEM files readable by OpenSSL 3 / Guzzle.
     *
     * @return array{cert: string, key: string} Absolute paths to extracted PEM files.
     */
    public static function ensurePemFromP12(Service $service, ?string $passphrase = null): array
    {
        $p12Relative = $service->tls_client_cert_path;
        $p12Absolute = self::absolutePath($p12Relative);

        if (blank($p12Absolute) || ! \is_readable($p12Absolute)) {
            throw new RuntimeException('PKCS#12 file is missing or not readable.');
        }

        $directory = self::directory($service);
        $certRelative = $directory . '/' . self::EXTRACTED_CERT_NAME;
        $keyRelative = $directory . '/' . self::EXTRACTED_KEY_NAME;
        $certAbsolute = self::absolutePath($certRelative);
        $keyAbsolute = self::absolutePath($keyRelative);

        $needsExtract = ! \is_readable((string) $certAbsolute)
            || ! \is_readable((string) $keyAbsolute)
            || \filemtime((string) $certAbsolute) < \filemtime($p12Absolute)
            || \filemtime((string) $keyAbsolute) < \filemtime($p12Absolute);

        if ($needsExtract) {
            self::private__extractPemFromP12(
                $p12Absolute,
                (string) $certAbsolute,
                (string) $keyAbsolute,
                (string) ($passphrase ?? $service->tls_client_passphrase ?? ''),
            );
        }

        return [
            'cert' => (string) $certAbsolute,
            'key' => (string) $keyAbsolute,
        ];
    }

    /**
     * Delete all stored TLS client files for a service.
     */
    public static function forget(Service $service): void
    {
        $disk = Storage::disk(self::DISK);
        $directory = self::directory($service);

        if ($disk->exists($directory)) {
            $disk->deleteDirectory($directory);
        }
    }

    /**
     * Store or replace uploaded TLS client files and return DB path attributes.
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

            return [
                'tls_client_cert_path' => null,
                'tls_client_key_path' => null,
            ];
        }

        $disk = Storage::disk(self::DISK);
        $directory = self::directory($service);
        $certPath = $service->tls_client_cert_path;
        $keyPath = $service->tls_client_key_path;

        if ($removeCert && $cert === null) {
            if ($certPath !== null) {
                $disk->delete($certPath);
            }

            $disk->delete([
                $directory . '/' . self::EXTRACTED_CERT_NAME,
                $directory . '/' . self::EXTRACTED_KEY_NAME,
            ]);
            $certPath = null;
        }

        if ($removeKey && $key === null && $keyPath !== null) {
            $disk->delete($keyPath);
            $keyPath = null;
        }

        if ($cert !== null) {
            $certName = $mode === TlsClientMode::P12->value
                ? self::private__safeFileName($cert, 'client.p12', ['p12', 'pfx'])
                : self::private__safeFileName($cert, 'client.crt', ['crt', 'pem', 'cer']);
            $newCertPath = $directory . '/' . $certName;

            if ($certPath !== null && $certPath !== $newCertPath) {
                $disk->delete($certPath);
            }

            $disk->delete([
                $directory . '/' . self::EXTRACTED_CERT_NAME,
                $directory . '/' . self::EXTRACTED_KEY_NAME,
            ]);

            $disk->putFileAs($directory, $cert, $certName);
            $certPath = $newCertPath;
        }

        if ($mode === TlsClientMode::Pem->value) {
            if ($key !== null) {
                $keyName = self::private__safeFileName($key, 'client.key', ['key', 'pem']);
                $newKeyPath = $directory . '/' . $keyName;

                if ($keyPath !== null && $keyPath !== $newKeyPath) {
                    $disk->delete($keyPath);
                }

                $disk->putFileAs($directory, $key, $keyName);
                $keyPath = $newKeyPath;
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
                        $disk->delete([
                            $directory . '/' . self::EXTRACTED_CERT_NAME,
                            $directory . '/' . self::EXTRACTED_KEY_NAME,
                        ]);
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
     * Extract PEM certificate and unencrypted private key from a PKCS#12 file.
     */
    private static function private__extractPemFromP12(
        string $p12AbsolutePath,
        string $certAbsolutePath,
        string $keyAbsolutePath,
        string $passphrase,
    ): void {
        $certDir = \dirname($certAbsolutePath);

        if (! \is_dir($certDir) && ! \mkdir($certDir, 0700, true) && ! \is_dir($certDir)) {
            throw new RuntimeException('Unable to create TLS storage directory.');
        }

        $certOk = self::private__runPkcs12Export(
            $p12AbsolutePath,
            $certAbsolutePath,
            ['-clcerts', '-nokeys'],
            $passphrase,
        );

        $keyOk = $certOk && self::private__runPkcs12Export(
            $p12AbsolutePath,
            $keyAbsolutePath,
            ['-nocerts', '-nodes'],
            $passphrase,
        );

        if (! $certOk || ! $keyOk || ! \is_readable($certAbsolutePath) || ! \is_readable($keyAbsolutePath)) {
            @\unlink($certAbsolutePath);
            @\unlink($keyAbsolutePath);

            throw new RuntimeException(
                'Could not parse PKCS#12 file. Check the passphrase, or re-export the bundle with a modern cipher (OpenSSL 3 rejects some legacy PKCS#12 algorithms).',
            );
        }

        @\chmod($certAbsolutePath, 0600);
        @\chmod($keyAbsolutePath, 0600);
    }

    /**
     * @return array<string, string>
     */
    private static function private__processEnvWithPassphrase(string $passphrase): array
    {
        $env = \getenv();

        if (! \is_array($env)) {
            $env = [];
        }

        $normalized = [];

        foreach ($env as $key => $value) {
            if (\is_string($key) && \is_string($value)) {
                $normalized[$key] = $value;
            }
        }

        $normalized['HORIZONHUB_PKCS12_PASS'] = $passphrase;

        return $normalized;
    }

    /**
     * Run openssl pkcs12 export, retrying with -legacy for OpenSSL 3.
     *
     * @param list<string> $extraArgs
     */
    private static function private__runPkcs12Export(
        string $p12AbsolutePath,
        string $outAbsolutePath,
        array $extraArgs,
        string $passphrase,
    ): bool {
        $attempts = [
            [],
            ['-legacy'],
        ];

        foreach ($attempts as $legacyArgs) {
            $command = \array_merge(
                ['openssl', 'pkcs12', '-in', $p12AbsolutePath, '-out', $outAbsolutePath, '-passin', 'env:HORIZONHUB_PKCS12_PASS'],
                $extraArgs,
                $legacyArgs,
            );

            $descriptorSpec = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];

            $process = \proc_open(
                $command,
                $descriptorSpec,
                $pipes,
                null,
                self::private__processEnvWithPassphrase($passphrase),
            );

            if (! \is_resource($process)) {
                continue;
            }

            \fclose($pipes[0]);
            \stream_get_contents($pipes[1]);
            \fclose($pipes[1]);
            \stream_get_contents($pipes[2]);
            \fclose($pipes[2]);

            $exitCode = \proc_close($process);

            if ($exitCode === 0 && \is_readable($outAbsolutePath) && \filesize($outAbsolutePath) > 0) {
                return true;
            }

            @\unlink($outAbsolutePath);
        }

        return false;
    }

    /**
     * Build a safe on-disk file name from the uploaded original name.
     *
     * @param list<string> $allowedExtensions
     */
    private static function private__safeFileName(UploadedFile $file, string $fallback, array $allowedExtensions): string
    {
        $original = \basename($file->getClientOriginalName());
        $extension = \strtolower((string) \pathinfo($original, \PATHINFO_EXTENSION));

        if (! \in_array($extension, $allowedExtensions, true)) {
            $extension = \strtolower((string) \pathinfo($fallback, \PATHINFO_EXTENSION));
        }

        $stem = (string) \pathinfo($original, \PATHINFO_FILENAME);
        $stem = \preg_replace('/[^\w.\-]+/u', '-', $stem) ?? '';
        $stem = \trim($stem, '.-_');

        if ($stem === '') {
            $stem = (string) \pathinfo($fallback, \PATHINFO_FILENAME);
        }

        return $stem . '.' . $extension;
    }
}
