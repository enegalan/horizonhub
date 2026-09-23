<?php

namespace Tests\Unit;

use App\Enums\TlsClientMode;
use App\Models\Service;
use App\Support\Services\ServiceTlsClientStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ServiceTlsClientStorageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(ServiceTlsClientStorage::DISK);
    }

    public function test_sync_stores_pem_files_and_forget_removes_directory(): void
    {
        $service = Service::create([
            'name' => 'svc-tls-storage',
            'base_url' => 'https://svc-tls-storage.test',
            'status' => 'online',
            'tls_client_mode' => TlsClientMode::Pem->value,
        ]);

        $paths = ServiceTlsClientStorage::sync(
            $service,
            TlsClientMode::Pem->value,
            UploadedFile::fake()->create('client.crt', 10, 'application/x-x509-ca-cert'),
            UploadedFile::fake()->create('client.key', 10, 'application/x-pem-file'),
        );

        $this->assertSame('service-tls/' . $service->id . '/client.crt', $paths['tls_client_cert_path']);
        $this->assertSame('service-tls/' . $service->id . '/client.key', $paths['tls_client_key_path']);
        Storage::disk(ServiceTlsClientStorage::DISK)->assertExists($paths['tls_client_cert_path']);
        Storage::disk(ServiceTlsClientStorage::DISK)->assertExists($paths['tls_client_key_path']);

        ServiceTlsClientStorage::forget($service);

        Storage::disk(ServiceTlsClientStorage::DISK)->assertMissing('service-tls/' . $service->id);
    }

    public function test_sync_with_null_mode_clears_files(): void
    {
        $service = Service::create([
            'name' => 'svc-tls-clear-storage',
            'base_url' => 'https://svc-tls-clear-storage.test',
            'status' => 'online',
            'tls_client_mode' => TlsClientMode::P12->value,
            'tls_client_cert_path' => 'service-tls/99/client.p12',
        ]);

        Storage::disk(ServiceTlsClientStorage::DISK)->put('service-tls/' . $service->id . '/client.p12', 'p12');

        $paths = ServiceTlsClientStorage::sync($service, null);

        $this->assertNull($paths['tls_client_cert_path']);
        $this->assertNull($paths['tls_client_key_path']);
        Storage::disk(ServiceTlsClientStorage::DISK)->assertMissing('service-tls/' . $service->id);
    }

    public function test_sync_p12_extracts_pem_sidecars(): void
    {
        $fixture = $this->private__createPkcs12Fixture('extract-secret');

        try {
            $service = Service::create([
                'name' => 'svc-tls-p12-extract',
                'base_url' => 'https://svc-tls-p12-extract.test',
                'status' => 'online',
                'tls_client_mode' => TlsClientMode::P12->value,
                'tls_client_passphrase' => 'extract-secret',
            ]);

            $upload = new UploadedFile($fixture['p12'], 'client.p12', 'application/x-pkcs12', null, true);

            $paths = ServiceTlsClientStorage::sync(
                $service,
                TlsClientMode::P12->value,
                $upload,
                null,
                'extract-secret',
            );

            $this->assertSame('service-tls/' . $service->id . '/client.p12', $paths['tls_client_cert_path']);
            Storage::disk(ServiceTlsClientStorage::DISK)->assertExists($paths['tls_client_cert_path']);
            Storage::disk(ServiceTlsClientStorage::DISK)->assertExists(
                ServiceTlsClientStorage::directory($service) . '/' . ServiceTlsClientStorage::EXTRACTED_CERT_NAME,
            );
            Storage::disk(ServiceTlsClientStorage::DISK)->assertExists(
                ServiceTlsClientStorage::directory($service) . '/' . ServiceTlsClientStorage::EXTRACTED_KEY_NAME,
            );
        } finally {
            $this->private__cleanupFixture($fixture);
        }
    }

    /**
     * @param array{dir: string, p12: string, cert?: string, key?: string} $fixture
     */
    private function private__cleanupFixture(array $fixture): void
    {
        foreach (['p12', 'key', 'cert'] as $key) {
            if (! empty($fixture[$key]) && \is_file($fixture[$key])) {
                @\unlink($fixture[$key]);
            }
        }

        if (! empty($fixture['dir']) && \is_dir($fixture['dir'])) {
            @\rmdir($fixture['dir']);
        }
    }

    /**
     * @return array{dir: string, p12: string, cert: string, key: string}
     */
    private function private__createPkcs12Fixture(string $password): array
    {
        $dir = \sys_get_temp_dir() . '/hh-p12-' . \bin2hex(\random_bytes(4));
        \mkdir($dir, 0700);

        $cert = $dir . '/cert.pem';
        $key = $dir . '/key.pem';
        $p12 = $dir . '/client.p12';

        $this->private__runOrSkip([
            'openssl', 'req', '-x509', '-newkey', 'rsa:2048',
            '-keyout', $key, '-out', $cert, '-days', '1', '-nodes',
            '-subj', '/CN=horizonhub-test',
        ]);

        $export = [
            'openssl', 'pkcs12', '-export',
            '-in', $cert, '-inkey', $key, '-out', $p12,
            '-passout', 'pass:' . $password,
        ];

        // Prefer a legacy-encrypted bundle when the local openssl supports it,
        // so extraction exercises the OpenSSL 3 -legacy fallback path.
        $legacyExport = \array_merge($export, ['-legacy']);
        $legacyOk = $this->private__runCommand($legacyExport) === 0;

        if (! $legacyOk && $this->private__runCommand($export) !== 0) {
            $this->markTestSkipped('openssl pkcs12 export failed');
        }

        return compact('dir', 'p12', 'cert', 'key');
    }

    /**
     * @param list<string> $command
     */
    private function private__runCommand(array $command): int
    {
        $process = \proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

        if (! \is_resource($process)) {
            return 1;
        }

        \stream_get_contents($pipes[1]);
        \fclose($pipes[1]);
        \stream_get_contents($pipes[2]);
        \fclose($pipes[2]);

        return \proc_close($process);
    }

    /**
     * @param list<string> $command
     */
    private function private__runOrSkip(array $command): void
    {
        if ($this->private__runCommand($command) !== 0) {
            $this->markTestSkipped('openssl command failed: ' . \implode(' ', $command));
        }
    }
}
