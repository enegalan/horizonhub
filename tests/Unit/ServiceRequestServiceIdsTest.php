<?php

namespace Tests\Unit;

use App\Http\Requests\Horizon\ServiceRequest;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class ServiceRequestServiceIdsTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_ids_from_request_uses_first_non_empty_key(): void
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

        $request = Request::create('/x?first%5B%5D=' . $s2->id . '&second%5B%5D=' . $s1->id, 'GET');

        $ids = ServiceRequest::existingIdsFromRequest($request, ['first', 'second']);
        $this->assertSame([(int) $s2->id], $ids);

        $request2 = Request::create('/x?second%5B%5D=' . $s1->id, 'GET');
        $ids2 = ServiceRequest::existingIdsFromRequest($request2, ['first', 'second']);
        $this->assertSame([(int) $s1->id], $ids2);
    }

    public function test_parse_positive_int_ids_from_array_and_scalar(): void
    {
        $this->assertSame([1, 2], ServiceRequest::parseIds(['1', '2', '1']));
        $this->assertSame([5], ServiceRequest::parseIds('5'));
        $this->assertSame([], ServiceRequest::parseIds([]));
        $this->assertSame([], ServiceRequest::parseIds('x'));
        $this->assertSame([], ServiceRequest::parseIds(null));
    }
}
