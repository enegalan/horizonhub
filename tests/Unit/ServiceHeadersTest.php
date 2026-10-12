<?php

namespace Tests\Unit;

use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ServiceHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_headers_normalizes_into_a_name_value_map(): void
    {
        $service = Service::factory()->create();
        $service->headers()->create(['name' => 'Authorization', 'value' => 'Bearer token']);
        $service->headers()->create(['name' => 'X-Name-Only']);

        $this->assertSame(
            ['Authorization' => 'Bearer token', 'X-Name-Only' => ''],
            $service->getHeaders(),
        );
    }

    public function test_many_services_load_headers_with_a_bounded_query_count(): void
    {
        $services = Service::factory()->count(20)->create();

        foreach ($services as $service) {
            $service->headers()->create(['name' => 'X-Service', 'value' => $service->name]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $loaded = Service::getServices(orderByName: true);

        foreach ($loaded as $service) {
            $this->assertSame(['X-Service' => $service->name], $service->getHeaders());
        }

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(2, $queries, 'Services and headers must each load in one query.');
    }

    public function test_repeated_get_headers_calls_are_memoized(): void
    {
        $service = Service::factory()->create();
        $service->headers()->create(['name' => 'X-Token', 'value' => 'abc']);
        $service->getHeaders();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $headers = $service->getHeaders();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertSame(['X-Token' => 'abc'], $headers);
        $this->assertCount(0, $queries, 'Repeated header reads must not re-query.');
    }
}
