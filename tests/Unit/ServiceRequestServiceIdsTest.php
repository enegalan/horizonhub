<?php

namespace Tests\Unit;

use App\Models\Service;
use App\Services\Services\ServiceFilterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class ServiceRequestServiceIdsTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_ids_from_request_reads_service_id(): void
    {
        $s1 = Service::create([
            'name' => 'svc-a',
            'base_url' => 'https://a.test',
            'status' => 'online',
        ]);
        $s2 = Service::create([
            'name' => 'svc-b',
            'base_url' => 'https://b.test',
            'status' => 'online',
        ]);

        $request = Request::create('/x', 'GET', [
            'service_id' => [$s1->id, $s2->id],
        ]);

        $filter = $this->app->make(ServiceFilterService::class);
        $ids = $filter->existingServiceIdsFromRequest($request);
        $this->assertSame([(int) $s1->id, (int) $s2->id], $ids);

        $request2 = Request::create('/x', 'GET');
        $this->assertSame([], $filter->existingServiceIdsFromRequest($request2));
    }
}
