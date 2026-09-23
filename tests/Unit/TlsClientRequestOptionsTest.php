<?php

namespace Tests\Unit;

use App\Enums\TlsClientMode;
use App\Models\Service;
use App\Support\Http\TlsClientRequestOptions;
use App\Support\Services\ServiceTlsClientStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TlsClientRequestOptionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(ServiceTlsClientStorage::DISK);
    }

    public function test_p12_mode_uses_extracted_pem_options(): void
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
            $service->tls_client_cert_path = $relative;
            $service->save();

            $options = TlsClientRequestOptions::forService($service->fresh());

            $this->assertArrayHasKey('cert', $options);
            $this->assertArrayHasKey('ssl_key', $options);
            $this->assertArrayNotHasKey('cert_type', $options);
            $this->assertStringContainsString(ServiceTlsClientStorage::EXTRACTED_CERT_NAME, (string) $options['cert']);
            $this->assertStringContainsString(ServiceTlsClientStorage::EXTRACTED_KEY_NAME, (string) $options['ssl_key']);
            $this->assertFileExists((string) $options['cert']);
            $this->assertFileExists((string) $options['ssl_key']);
        } finally {
            $this->private__cleanupFixture($fixture);
        }
    }

    public function test_pem_mode_applies_passphrase_to_ssl_key(): void
    {
        $service = Service::create([
            'name' => 'svc-tls-pem-pass',
            'base_url' => 'https://svc-tls-pem-pass.test',
            'status' => 'online',
            'tls_client_mode' => TlsClientMode::Pem->value,
            'tls_client_cert_path' => 'service-tls/1/client.crt',
            'tls_client_key_path' => 'service-tls/1/client.key',
            'tls_client_passphrase' => 'secret',
        ]);

        $this->assertSame([
            'cert' => ServiceTlsClientStorage::absolutePath('service-tls/1/client.crt'),
            'ssl_key' => [ServiceTlsClientStorage::absolutePath('service-tls/1/client.key'), 'secret'],
        ], TlsClientRequestOptions::forService($service));
    }

    public function test_pem_mode_builds_cert_and_ssl_key_options(): void
    {
        $service = Service::create([
            'name' => 'svc-tls-pem',
            'base_url' => 'https://svc-tls-pem.test',
            'status' => 'online',
            'tls_client_mode' => TlsClientMode::Pem->value,
            'tls_client_cert_path' => 'service-tls/1/client.crt',
            'tls_client_key_path' => 'service-tls/1/client.key',
        ]);

        Storage::disk(ServiceTlsClientStorage::DISK)->put('service-tls/1/client.crt', 'cert');
        Storage::disk(ServiceTlsClientStorage::DISK)->put('service-tls/1/client.key', 'key');

        $this->assertSame([
            'cert' => ServiceTlsClientStorage::absolutePath('service-tls/1/client.crt'),
            'ssl_key' => ServiceTlsClientStorage::absolutePath('service-tls/1/client.key'),
        ], TlsClientRequestOptions::forService($service));
    }

    public function test_returns_empty_array_when_mode_is_null(): void
    {
        $service = Service::create([
            'name' => 'svc-tls-none',
            'base_url' => 'https://svc-tls-none.test',
            'status' => 'online',
        ]);

        $this->assertSame([], TlsClientRequestOptions::forService($service));
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
            $this->markTestSkipped('openssl command failed: ' . \implode(' ', $command));
        }
    }
}
