<?php

namespace Tests\Unit;

use App\Enums\TlsClientMode;
use App\Models\Service;
use App\Support\PathBuilder;
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

    public function test_http_options_for_p12_uses_extracted_pem(): void
    {
        $fixture = $this->private__createPkcs12Fixture('p12-secret');

        try {
            $service = Service::create([
                'name' => 'svc-tls-p12',
                'base_url' => 'https://svc-tls-p12.test',
                'status' => 'online',
                'tls_client_mode' => TlsClientMode::P12->value,
                'tls_client_passphrase' => 'p12-secret',
            ]);

            $relative = ServiceTlsClientStorage::directory($service) . '/client.p12';
            Storage::disk(ServiceTlsClientStorage::DISK)->put($relative, \file_get_contents($fixture['p12']));
            $service->update(['tls_client_cert_path' => $relative]);

            $options = ServiceTlsClientStorage::httpOptions($service->fresh());

            $this->assertArrayHasKey('cert', $options);
            $this->assertArrayHasKey('ssl_key', $options);
            $this->assertFileExists((string) $options['cert']);
            $this->assertFileExists((string) $options['ssl_key']);
        } finally {
            $this->private__cleanupFixture($fixture);
        }
    }

    public function test_http_options_for_pem_and_empty_mode(): void
    {
        $none = Service::create([
            'name' => 'svc-tls-none',
            'base_url' => 'https://svc-tls-none.test',
            'status' => 'online',
        ]);

        $this->assertSame([], ServiceTlsClientStorage::httpOptions($none));

        $pem = Service::create([
            'name' => 'svc-tls-pem',
            'base_url' => 'https://svc-tls-pem.test',
            'status' => 'online',
            'tls_client_mode' => TlsClientMode::Pem->value,
            'tls_client_cert_path' => 'service-tls/1/client.crt',
            'tls_client_key_path' => 'service-tls/1/client.key',
            'tls_client_passphrase' => 'secret',
        ]);

        $this->assertSame([
            'cert' => PathBuilder::absolutePath('service-tls/1/client.crt', ServiceTlsClientStorage::DISK),
            'ssl_key' => [PathBuilder::absolutePath('service-tls/1/client.key', ServiceTlsClientStorage::DISK), 'secret'],
        ], ServiceTlsClientStorage::httpOptions($pem));
    }

    public function test_sync_null_mode_clears_files(): void
    {
        $service = Service::create([
            'name' => 'svc-tls-clear',
            'base_url' => 'https://svc-tls-clear.test',
            'status' => 'online',
            'tls_client_mode' => TlsClientMode::P12->value,
            'tls_client_cert_path' => 'service-tls/1/client.p12',
        ]);

        Storage::disk(ServiceTlsClientStorage::DISK)->put('service-tls/' . $service->id . '/client.p12', 'p12');

        $paths = ServiceTlsClientStorage::sync($service, null);

        $this->assertNull($paths['tls_client_cert_path']);
        Storage::disk(ServiceTlsClientStorage::DISK)->assertMissing('service-tls/' . $service->id);
    }

    public function test_sync_stores_pem_and_forget_clears_directory(): void
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
            UploadedFile::fake()->create('client.crt', 10),
            UploadedFile::fake()->create('client.key', 10),
        );

        $this->assertSame('service-tls/' . $service->id . '/client.crt', $paths['tls_client_cert_path']);
        $this->assertSame('service-tls/' . $service->id . '/client.key', $paths['tls_client_key_path']);

        ServiceTlsClientStorage::forget($service);
        Storage::disk(ServiceTlsClientStorage::DISK)->assertMissing('service-tls/' . $service->id);
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
        $this->private__runOrSkip([
            'openssl', 'pkcs12', '-export',
            '-in', $cert, '-inkey', $key, '-out', $p12,
            '-passout', 'pass:' . $password,
        ]);

        return compact('dir', 'p12', 'cert', 'key');
    }

    /**
     * @param list<string> $command
     */
    private function private__runOrSkip(array $command): void
    {
        $process = \proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

        if (! \is_resource($process)) {
            $this->markTestSkipped('openssl is not available');
        }

        \stream_get_contents($pipes[1]);
        \fclose($pipes[1]);
        \stream_get_contents($pipes[2]);
        \fclose($pipes[2]);

        if (\proc_close($process) !== 0) {
            $this->markTestSkipped('openssl command failed');
        }
    }
}
