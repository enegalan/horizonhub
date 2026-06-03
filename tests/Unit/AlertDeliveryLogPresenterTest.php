<?php

namespace Tests\Unit;

use App\Models\Alert;
use App\Models\AlertLog;
use App\Models\Service;
use App\Services\Alerts\Rules\Strategies\FailureCount;
use App\Support\Alerts\AlertDeliveryLogPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlertDeliveryLogPresenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_payload_from_log_handles_null_and_builds_grouped_job_items(): void
    {
        $this->assertNull(AlertDeliveryLogPresenter::payloadFromLog(null));

        config()->set('horizonhub.alerts.delivery_log_max_distinct_jobs', 1);
        $service = Service::create(['name' => 'svc', 'base_url' => 'https://x.test', 'status' => 'online']);
        $alert = Alert::create(['name' => 'a', 'rule_type' => FailureCount::type(), 'enabled' => true]);
        $log = AlertLog::create([
            'alert_id' => $alert->id,
            'service_id' => $service->id,
            'trigger_count' => 3,
            'job_uuids' => ['u1', 'u1', 'u2'],
            'status' => 'failed',
            'failure_message' => 'boom',
            'sent_at' => now(),
        ]);

        $payload = AlertDeliveryLogPresenter::payloadFromLog($log);
        $this->assertSame('3 events', $payload['events_text']);
        $this->assertSame(3, $payload['events_count']);
        $this->assertSame('failed', $payload['status']);
        $this->assertCount(1, $payload['job_items']);
        $this->assertSame(1, $payload['job_ids_more']);
    }
}
