<?php

namespace Tests\Unit;

use App\Models\Alert;
use App\Models\Service;
use App\Services\Alerts\AlertIndexStreamDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlertIndexStreamDataServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_build_stream_payload_maps_service_labels_and_enabled_counts(): void
    {
        $includedService = Service::create([
            'name' => 'alpha-service',
            'base_url' => 'https://alpha.test',
            'status' => 'online',
        ]);
        $missingServiceId = 9999;

        Alert::create([
            'name' => 'enabled-alert',
            'rule_type' => Alert::RULE_FAILURE_COUNT,
            'threshold' => ['count' => 1, 'minutes' => 5],
            'enabled' => true,
            'service_ids' => [$includedService->id, $missingServiceId],
        ]);
        Alert::create([
            'name' => 'disabled-alert',
            'rule_type' => Alert::RULE_FAILURE_COUNT,
            'threshold' => ['count' => 1, 'minutes' => 5],
            'enabled' => false,
            'service_ids' => [],
        ]);

        $payload = $this->app->make(AlertIndexStreamDataService::class)->buildStreamPayload();

        $this->assertCount(2, $payload['alerts']);
        $this->assertSame([
            'total' => 2,
            'enabled' => 1,
            'disabled' => 1,
        ], $payload['alertStats']);
        $this->assertSame(['alpha-service'], $payload['serviceLabelsByAlertId'][$payload['alerts'][0]->id]);
        $this->assertSame([], $payload['serviceLabelsByAlertId'][$payload['alerts'][1]->id]);
    }
}
