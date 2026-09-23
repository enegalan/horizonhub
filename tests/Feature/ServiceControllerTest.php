<?php

namespace Tests\Feature;

use App\Enums\ServiceStatus;
use App\Models\Service;
use App\Services\Horizon\HorizonClientCacheService;
use App\Support\FormDrawer;
use App\Support\Services\ServiceTlsClientStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ServiceControllerTest extends TestCase
{
    use RefreshDatabase;

    private const PEM_CERTIFICATE = <<<'PEM'
-----BEGIN CERTIFICATE-----
MIICEDCCAXmgAwIBAgIUZuS8mee4ajv0TcbZCxo+raHhGQowDQYJKoZIhvcNAQEL
BQAwGjEYMBYGA1UEAwwPaG9yaXpvbmh1Yi10ZXN0MB4XDTI2MDkyMzIyMDEzNloX
DTI3MDkyMzIyMDEzNlowGjEYMBYGA1UEAwwPaG9yaXpvbmh1Yi10ZXN0MIGfMA0G
CSqGSIb3DQEBAQUAA4GNADCBiQKBgQDn/z1p1Ox9AvTJsIEtk0y2OoAZBevAXKjg
pYv53kw7f6LAyNTv/kyZQpLKAcrgFPwcixW+O4w9Lhv8uBrS3wUlfdiT/S80dQNN
iQz6bHvLZ8TtIPYUYZEQ1SvayRbQsyFOE7shmDUG+ms8tZmH4XZa87GDLITEw96A
ez2BvRHpfwIDAQABo1MwUTAdBgNVHQ4EFgQUkk2VPyDrVBc/PmVHpM2BRNBjYOkw
HwYDVR0jBBgwFoAUkk2VPyDrVBc/PmVHpM2BRNBjYOkwDwYDVR0TAQH/BAUwAwEB
/zANBgkqhkiG9w0BAQsFAAOBgQBXQ3Z8O7txJbh45I7lQV1JBbtLr65mQq4cdL3t
b/mxlEEdvUybCby02IVPqH0S1ts0i9Gts1LnIvtDLLdPLLfqTIG2X0mTUHeXWxLU
/d9dQVn4g73SITBqVBaJdo80p0TXfG1b6IAnlOZpQa6ig5VWnywuPDuNOcgopOi4
EG12mQ==
-----END CERTIFICATE-----
PEM;

    private const PEM_PRIVATE_KEY = <<<'PEM'
-----BEGIN PRIVATE KEY-----
MIICdwIBADANBgkqhkiG9w0BAQEFAASCAmEwggJdAgEAAoGBAOf/PWnU7H0C9Mmw
gS2TTLY6gBkF68BcqOCli/neTDt/osDI1O/+TJlCksoByuAU/ByLFb47jD0uG/y4
GtLfBSV92JP9LzR1A02JDPpse8tnxO0g9hRhkRDVK9rJFtCzIU4TuyGYNQb6azy1
mYfhdlrzsYMshMTD3oB7PYG9Eel/AgMBAAECgYEAhcRqCMe4xCwcMN8Q3NJ9/OSm
T8dWM8g5p65Mk3pVwkjJ8xbZkLe0Ovpj4Q4/iA0RgPPBSbrUFcKsaH9PGB93uxlU
SmGQjg6ad8jj/tIAsMp0SKFnexuqkG131tN66ucZBN2qGkkGaLWufTEPtFg3ylqB
uxGbao1Rvqiq/93So2ECQQD15/UoNJCDuuD3r0b8zs/hNTBTnjvF05d2BdjxoDa5
3gDrS59yi6sO8KbvQczKk7S43XKrhsmhrpUX36TRFlR1AkEA8YUfWQmZ8ue/VMZw
U6HMiuPUnD1k/BBsEOnD3zOf8uUwOcIvuB/gWY7/dlCKSRIpq1dKxS6tDsFpaDuw
JkE3owJAeOQnPzPQVCKclMfj00dtJV97ubAR3KiwToKDbA6CuQ+uTf7ojWyilP60
Yu1rW7AP6c5coHzsRYNJoun84hnjPQJACbQIe2JIXhrzc+t5DdMdTaMzoodQ7FOY
k+FgbjI7xd1xX5CurB4TvGVjXBSGScNCC1E5fsyORV595qMnQ5IxMwJBAILqHapH
1pG64GFctKjHlqieELyJ44mgcSYaItNQ4l5NCBJoJKr6xwtOmokrgj+8k1hvLNvO
2Q8K/KWnAuoWwsk=
-----END PRIVATE KEY-----
PEM;

    public function test_index_edit_show_store_update_destroy_and_connection_paths(): void
    {
        $service = Service::create([
            'name' => 'svc-a',
            'base_url' => 'https://svc-a.test',
            'public_url' => 'https://public-a.test',
            'status' => 'offline',
        ]);

        \config()->set('horizonhub.http.retry', ['times' => 1, 'sleep_ms' => 0, 'retry_on_status' => []]);

        Http::fake([
            'https://svc-a-updated.test/horizon/api/stats' => Http::sequence()
                ->push(['status' => 'running'], 200)
                ->push(['message' => 'failed ping'], 500),
        ]);

        $this->get(route('horizon.services.index'))->assertOk();
        Service::factory()->create(['tags' => ['production']]);

        $formDrawerHeaders = ['Turbo-Frame' => FormDrawer::FRAME_ID];

        $this->get(route('horizon.services.create'), $formDrawerHeaders)
            ->assertOk()
            ->assertSee('production', false);
        $this->get(route('horizon.services.edit', ['service' => $service]), $formDrawerHeaders)
            ->assertOk()
            ->assertSee('production', false);
        $this->get(route('horizon.services.show', ['service' => $service]))
            ->assertOk()
            ->assertDontSee('Supervisor data is not available', false)
            ->assertDontSee('No queues for this service yet', false);

        $this->post(route('horizon.services.store'), [
            'name' => 'svc-b',
            'base_url' => 'https://svc-b.test/',
            'public_url' => 'https://public-b.test/',
            'tags' => ['Production', ' mailing '],
        ])->assertRedirect(route('horizon.services.index'));
        $this->assertDatabaseHas('services', ['name' => 'svc-b', 'base_url' => 'https://svc-b.test', 'public_url' => 'https://public-b.test']);
        $created = Service::where('name', 'svc-b')->first();
        $this->assertNotNull($created);
        $this->assertSame(['mailing', 'production'], $created->tags);

        Cache::put(HorizonClientCacheService::failureCooldownCacheKey($service), true, now()->addMinutes(1));

        $this->put(route('horizon.services.update', ['service' => $service]), [
            'name' => 'svc-a-updated',
            'base_url' => 'https://svc-a-updated.test/',
            'public_url' => '',
        ])->assertRedirect(route('horizon.services.index'));

        $this->assertDatabaseHas('services', [
            'name' => 'svc-a-updated',
            'base_url' => 'https://svc-a-updated.test',
            'public_url' => null,
        ]);

        $this->assertFalse(Cache::has(HorizonClientCacheService::failureCooldownCacheKey($service)));

        $this->post(route('horizon.services.test-connection', ['service' => $service]))
            ->assertRedirect()
            ->assertSessionHas('status', [
                'message' => 'Service Horizon API is reachable.',
                'type' => 'success',
            ]);
        $service->refresh();
        $this->assertSame(ServiceStatus::Online, $service->status);

        $this->post(route('horizon.services.test-connection', ['service' => $service]))
            ->assertRedirect()
            ->assertSessionHas('status', [
                'message' => 'failed ping',
                'type' => 'error',
            ]);
        $service->refresh();
        $this->assertSame(ServiceStatus::Offline, $service->status);

        $this->post(route('horizon.services.toggle-enabled', ['service' => $service]))
            ->assertOk()
            ->assertJson(['service_id' => $service->id, 'enabled' => false]);
        $service->refresh();
        $this->assertFalse($service->enabled);

        $this->post(route('horizon.services.toggle-enabled', ['service' => $service]))
            ->assertOk()
            ->assertJson(['enabled' => true]);

        $this->delete(route('horizon.services.destroy', ['service' => $service]))->assertRedirect(route('horizon.services.index'));
        $this->assertDatabaseMissing('services', ['id' => $service->id]);
    }

    public function test_store_and_update_persist_headers_including_name_only_rows(): void
    {
        $this->post(route('horizon.services.store'), [
            'name' => 'svc-headers',
            'base_url' => 'https://svc-headers.test/',
            'headers' => [
                ['name' => 'Authorization', 'value' => 'Bearer abc'],
                ['name' => 'X-Feature-Flag', 'value' => ''],
            ],
        ])->assertRedirect(route('horizon.services.index'));

        $service = Service::where('name', 'svc-headers')->firstOrFail();

        $this->assertDatabaseHas('service_headers', [
            'service_id' => $service->id,
            'name' => 'Authorization',
            'value' => 'Bearer abc',
        ]);
        $this->assertDatabaseHas('service_headers', [
            'service_id' => $service->id,
            'name' => 'X-Feature-Flag',
            'value' => null,
        ]);

        $this->put(route('horizon.services.update', ['service' => $service]), [
            'name' => 'svc-headers',
            'base_url' => 'https://svc-headers.test/',
            'headers' => [
                ['name' => 'X-Api-Key', 'value' => 'key-1'],
            ],
        ])->assertRedirect(route('horizon.services.index'));

        $this->assertDatabaseMissing('service_headers', [
            'service_id' => $service->id,
            'name' => 'Authorization',
        ]);
        $this->assertDatabaseHas('service_headers', [
            'service_id' => $service->id,
            'name' => 'X-Api-Key',
            'value' => 'key-1',
        ]);
    }

    public function test_store_and_update_persist_pem_tls_client_settings(): void
    {
        Storage::fake(ServiceTlsClientStorage::DISK);

        $this->post(route('horizon.services.store'), [
            'name' => 'svc-tls-pem',
            'base_url' => 'https://svc-tls-pem.test/',
            'tls_client_mode' => 'pem',
            'tls_client_cert' => UploadedFile::fake()->createWithContent('client.crt', self::PEM_CERTIFICATE),
            'tls_client_key' => UploadedFile::fake()->createWithContent('client.key', self::PEM_PRIVATE_KEY),
            'tls_client_passphrase' => 'pem-secret',
        ])->assertRedirect(route('horizon.services.index'));

        $service = Service::where('name', 'svc-tls-pem')->firstOrFail();

        $this->assertSame('pem', $service->tls_client_mode?->value);
        $this->assertSame('service-tls/' . $service->id . '/client.crt', $service->tls_client_cert_path);
        $this->assertSame('service-tls/' . $service->id . '/client.key', $service->tls_client_key_path);
        $this->assertSame('pem-secret', $service->tls_client_passphrase);
        Storage::disk(ServiceTlsClientStorage::DISK)->assertExists($service->tls_client_cert_path);
        Storage::disk(ServiceTlsClientStorage::DISK)->assertExists($service->tls_client_key_path);

        $this->put(route('horizon.services.update', ['service' => $service]), [
            'name' => 'svc-tls-pem',
            'base_url' => 'https://svc-tls-pem.test/',
            'tls_client_mode' => 'pem',
            'tls_client_passphrase' => '',
        ])->assertRedirect(route('horizon.services.index'));

        $service->refresh();
        $this->assertSame('pem-secret', $service->tls_client_passphrase);
        $this->assertSame('service-tls/' . $service->id . '/client.crt', $service->tls_client_cert_path);
    }

    public function test_store_ignores_header_row_with_only_whitespace_in_name_and_value(): void
    {
        $this->post(route('horizon.services.store'), [
            'name' => 'svc-ws-empty',
            'base_url' => 'https://svc-ws-empty.test/',
            'headers' => [
                ['name' => 'Authorization', 'value' => 'Bearer abc'],
                ['name' => '   ', 'value' => '   '],
            ],
        ])->assertRedirect(route('horizon.services.index'));

        $service = Service::where('name', 'svc-ws-empty')->firstOrFail();

        $this->assertDatabaseCount('service_headers', 1);
        $this->assertDatabaseHas('service_headers', [
            'service_id' => $service->id,
            'name' => 'Authorization',
        ]);
    }

    public function test_store_persists_p12_tls_client_settings(): void
    {
        Storage::fake(ServiceTlsClientStorage::DISK);

        $dir = \sys_get_temp_dir() . '/hh-feat-p12-' . \bin2hex(\random_bytes(4));
        \mkdir($dir, 0700);
        $cert = $dir . '/cert.pem';
        $key = $dir . '/key.pem';
        $p12 = $dir . '/client.p12';

        try {
            $req = \proc_open([
                'openssl', 'req', '-x509', '-newkey', 'rsa:2048',
                '-keyout', $key, '-out', $cert, '-days', '1', '-nodes',
                '-subj', '/CN=horizonhub-feature',
            ], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

            if (! \is_resource($req) || \proc_close($req) !== 0) {
                $this->markTestSkipped('openssl req failed');
            }

            $export = \proc_open([
                'openssl', 'pkcs12', '-export',
                '-in', $cert, '-inkey', $key, '-out', $p12,
                '-passout', 'pass:p12-secret',
            ], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

            if (! \is_resource($export) || \proc_close($export) !== 0) {
                $this->markTestSkipped('openssl pkcs12 export failed');
            }

            $this->post(route('horizon.services.store'), [
                'name' => 'svc-tls-p12',
                'base_url' => 'https://svc-tls-p12.test/',
                'tls_client_mode' => 'p12',
                'tls_client_cert' => new UploadedFile($p12, 'client.p12', 'application/x-pkcs12', null, true),
                'tls_client_passphrase' => 'p12-secret',
            ])->assertRedirect(route('horizon.services.index'));

            $service = Service::where('name', 'svc-tls-p12')->firstOrFail();

            $this->assertSame('p12', $service->tls_client_mode?->value);
            $this->assertSame('service-tls/' . $service->id . '/client.p12', $service->tls_client_cert_path);
            $this->assertNull($service->tls_client_key_path);
            $this->assertSame('p12-secret', $service->tls_client_passphrase);
            Storage::disk(ServiceTlsClientStorage::DISK)->assertExists($service->tls_client_cert_path);
            Storage::disk(ServiceTlsClientStorage::DISK)->assertExists(
                ServiceTlsClientStorage::directory($service) . '/extracted.crt',
            );
        } finally {
            foreach ([$p12, $cert, $key] as $file) {
                if (\is_file($file)) {
                    @\unlink($file);
                }
            }

            if (\is_dir($dir)) {
                @\rmdir($dir);
            }
        }
    }

    public function test_store_rejects_duplicate_header_names(): void
    {
        $this->from(route('horizon.services.create'))
            ->post(route('horizon.services.store'), [
                'name' => 'svc-dup-headers',
                'base_url' => 'https://svc-dup-headers.test/',
                'headers' => [
                    ['name' => 'Authorization', 'value' => 'one'],
                    ['name' => 'authorization', 'value' => 'two'],
                ],
            ])
            ->assertRedirect(route('horizon.services.create'))
            ->assertSessionHasErrors(['headers.1.name']);

        $this->assertDatabaseMissing('services', ['name' => 'svc-dup-headers']);
    }

    public function test_store_rejects_header_name_with_only_whitespace_when_value_is_set(): void
    {
        $this->from(route('horizon.services.create'))
            ->post(route('horizon.services.store'), [
                'name' => 'svc-ws-header',
                'base_url' => 'https://svc-ws-header.test/',
                'headers' => [
                    ['name' => '   ', 'value' => 'secret'],
                ],
            ])
            ->assertRedirect(route('horizon.services.create'))
            ->assertSessionHasErrors(['headers.0.name']);

        $this->assertDatabaseMissing('services', ['name' => 'svc-ws-header']);
    }

    public function test_store_rejects_pem_mode_without_uploaded_files(): void
    {
        $this->from(route('horizon.services.create'))
            ->post(route('horizon.services.store'), [
                'name' => 'svc-tls-missing',
                'base_url' => 'https://svc-tls-missing.test/',
                'tls_client_mode' => 'pem',
            ])
            ->assertRedirect(route('horizon.services.create'))
            ->assertSessionHasErrors(['tls_client_cert', 'tls_client_key']);

        $this->assertDatabaseMissing('services', ['name' => 'svc-tls-missing']);
    }

    public function test_store_rejects_reserved_header_names(): void
    {
        $this->from(route('horizon.services.create'))
            ->post(route('horizon.services.store'), [
                'name' => 'svc-reserved-header',
                'base_url' => 'https://svc-reserved-header.test/',
                'headers' => [
                    ['name' => 'Host', 'value' => 'evil.example'],
                ],
            ])
            ->assertRedirect(route('horizon.services.create'))
            ->assertSessionHasErrors(['headers.0.name']);

        $this->assertDatabaseMissing('services', ['name' => 'svc-reserved-header']);
    }

    public function test_test_connection_returns_warning_flash_when_upstream_times_out(): void
    {
        $service = Service::create([
            'name' => 'svc-timeout-flash',
            'base_url' => 'https://service-timeout-flash.test',
            'status' => 'online',
        ]);

        \config()->set('horizonhub.http.api_timeout', 10);
        \config()->set('horizonhub.http.retry', ['times' => 1, 'sleep_ms' => 0, 'retry_on_status' => []]);

        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out after 10001 milliseconds with 0 bytes received'));

        $this->post(route('horizon.services.test-connection', ['service' => $service]))
            ->assertRedirect()
            ->assertSessionHas('status.type', 'warning');

        $status = \session('status');
        $this->assertIsArray($status);
        $this->assertStringContainsString('HORIZON_HUB_API_TIMEOUT', (string) ($status['message'] ?? ''));
        $this->assertStringContainsString('10s', (string) ($status['message'] ?? ''));

        $service->refresh();
        $this->assertSame(ServiceStatus::Offline, $service->status);
    }

    public function test_update_clears_tls_client_settings_when_mode_is_none(): void
    {
        Storage::fake(ServiceTlsClientStorage::DISK);

        $service = Service::create([
            'name' => 'svc-tls-clear',
            'base_url' => 'https://svc-tls-clear.test',
            'status' => 'online',
            'tls_client_mode' => 'pem',
            'tls_client_cert_path' => 'service-tls/1/client.crt',
            'tls_client_key_path' => 'service-tls/1/client.key',
            'tls_client_passphrase' => 'keep-me',
        ]);

        Storage::disk(ServiceTlsClientStorage::DISK)->put('service-tls/' . $service->id . '/client.crt', 'cert');
        Storage::disk(ServiceTlsClientStorage::DISK)->put('service-tls/' . $service->id . '/client.key', 'key');

        $this->put(route('horizon.services.update', ['service' => $service]), [
            'name' => 'svc-tls-clear',
            'base_url' => 'https://svc-tls-clear.test/',
            'tls_client_mode' => '',
        ])->assertRedirect(route('horizon.services.index'));

        $service->refresh();
        $this->assertNull($service->tls_client_mode);
        $this->assertNull($service->tls_client_cert_path);
        $this->assertNull($service->tls_client_key_path);
        $this->assertNull($service->tls_client_passphrase);
        Storage::disk(ServiceTlsClientStorage::DISK)->assertMissing('service-tls/' . $service->id);
    }

    public function test_update_rejects_tls_cert_upload_without_removing_existing_file(): void
    {
        Storage::fake(ServiceTlsClientStorage::DISK);

        $service = Service::create([
            'name' => 'svc-tls-replace-guard',
            'base_url' => 'https://svc-tls-replace-guard.test',
            'status' => 'online',
            'tls_client_mode' => 'pem',
            'tls_client_cert_path' => 'service-tls/1/client.crt',
            'tls_client_key_path' => 'service-tls/1/client.key',
        ]);

        Storage::disk(ServiceTlsClientStorage::DISK)->put('service-tls/' . $service->id . '/client.crt', 'cert');
        Storage::disk(ServiceTlsClientStorage::DISK)->put('service-tls/' . $service->id . '/client.key', 'key');
        $service->update([
            'tls_client_cert_path' => 'service-tls/' . $service->id . '/client.crt',
            'tls_client_key_path' => 'service-tls/' . $service->id . '/client.key',
        ]);

        $this->from(route('horizon.services.edit', $service))
            ->put(route('horizon.services.update', ['service' => $service]), [
                'name' => 'svc-tls-replace-guard',
                'base_url' => 'https://svc-tls-replace-guard.test/',
                'tls_client_mode' => 'pem',
                'tls_client_cert' => UploadedFile::fake()->create('client.crt', 10, 'application/x-x509-ca-cert'),
                'tls_client_remove_cert' => '0',
                'tls_client_remove_key' => '0',
            ])
            ->assertRedirect(route('horizon.services.edit', $service))
            ->assertSessionHasErrors(['tls_client_cert']);
    }

    public function test_update_replaces_tls_cert_after_explicit_remove(): void
    {
        Storage::fake(ServiceTlsClientStorage::DISK);

        $service = Service::create([
            'name' => 'svc-tls-replace-ok',
            'base_url' => 'https://svc-tls-replace-ok.test',
            'status' => 'online',
            'tls_client_mode' => 'pem',
        ]);

        Storage::disk(ServiceTlsClientStorage::DISK)->put('service-tls/' . $service->id . '/client.crt', 'old-cert');
        Storage::disk(ServiceTlsClientStorage::DISK)->put('service-tls/' . $service->id . '/client.key', 'old-key');
        $service->update([
            'tls_client_cert_path' => 'service-tls/' . $service->id . '/client.crt',
            'tls_client_key_path' => 'service-tls/' . $service->id . '/client.key',
        ]);

        $this->put(route('horizon.services.update', ['service' => $service]), [
            'name' => 'svc-tls-replace-ok',
            'base_url' => 'https://svc-tls-replace-ok.test/',
            'tls_client_mode' => 'pem',
            'tls_client_cert' => UploadedFile::fake()->createWithContent('client.crt', self::PEM_CERTIFICATE),
            'tls_client_key' => UploadedFile::fake()->createWithContent('client.key', self::PEM_PRIVATE_KEY),
            'tls_client_remove_cert' => '1',
            'tls_client_remove_key' => '1',
        ])->assertRedirect(route('horizon.services.index'));

        $service->refresh();
        $this->assertSame('service-tls/' . $service->id . '/client.crt', $service->tls_client_cert_path);
        $this->assertSame('service-tls/' . $service->id . '/client.key', $service->tls_client_key_path);
        Storage::disk(ServiceTlsClientStorage::DISK)->assertExists($service->tls_client_cert_path);
        Storage::disk(ServiceTlsClientStorage::DISK)->assertExists($service->tls_client_key_path);
    }

    public function test_update_requires_fresh_p12_upload_when_mode_changes_from_pem(): void
    {
        Storage::fake(ServiceTlsClientStorage::DISK);

        $service = Service::create([
            'name' => 'svc-tls-mode-switch',
            'base_url' => 'https://svc-tls-mode-switch.test',
            'status' => 'online',
            'tls_client_mode' => 'pem',
        ]);
        $service->update([
            'tls_client_cert_path' => 'service-tls/' . $service->id . '/client.crt',
            'tls_client_key_path' => 'service-tls/' . $service->id . '/client.key',
        ]);

        Storage::disk(ServiceTlsClientStorage::DISK)->put('service-tls/' . $service->id . '/client.crt', 'cert');
        Storage::disk(ServiceTlsClientStorage::DISK)->put('service-tls/' . $service->id . '/client.key', 'key');

        $this->from(route('horizon.services.edit', $service))
            ->put(route('horizon.services.update', ['service' => $service]), [
                'name' => 'svc-tls-mode-switch',
                'base_url' => 'https://svc-tls-mode-switch.test/',
                'tls_client_mode' => 'p12',
            ])
            ->assertRedirect(route('horizon.services.edit', $service))
            ->assertSessionHasErrors(['tls_client_cert']);

        $service->refresh();
        $this->assertSame('pem', $service->tls_client_mode?->value);
    }

    public function test_update_requires_fresh_pem_uploads_when_mode_changes_from_p12(): void
    {
        Storage::fake(ServiceTlsClientStorage::DISK);

        $service = Service::create([
            'name' => 'svc-tls-mode-switch-2',
            'base_url' => 'https://svc-tls-mode-switch-2.test',
            'status' => 'online',
            'tls_client_mode' => 'p12',
            'tls_client_passphrase' => 'p12-secret',
        ]);
        $service->update([
            'tls_client_cert_path' => 'service-tls/' . $service->id . '/client.p12',
        ]);

        Storage::disk(ServiceTlsClientStorage::DISK)->put('service-tls/' . $service->id . '/client.p12', 'p12');

        $this->from(route('horizon.services.edit', $service))
            ->put(route('horizon.services.update', ['service' => $service]), [
                'name' => 'svc-tls-mode-switch-2',
                'base_url' => 'https://svc-tls-mode-switch-2.test/',
                'tls_client_mode' => 'pem',
            ])
            ->assertRedirect(route('horizon.services.edit', $service))
            ->assertSessionHasErrors(['tls_client_cert', 'tls_client_key']);

        $service->refresh();
        $this->assertSame('p12', $service->tls_client_mode?->value);
    }
}
