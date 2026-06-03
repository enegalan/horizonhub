<?php

namespace Tests\Unit;

use App\Models\Alert;
use App\Services\Alerts\Engine\AlertBatchStore;
use App\Services\Alerts\Rules\Strategies\FailureCount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AlertBatchStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_and_last_sent_cache_workflows(): void
    {
        Cache::flush();
        $alert = Alert::create([
            'name' => 'b1',
            'rule_type' => FailureCount::type(),
            'enabled' => true,
        ]);
        $service = new AlertBatchStore;

        $this->assertSame([], $service->getPending($alert));
        $service->setPending($alert, [['service_id' => 1, 'job_uuid' => 'a', 'triggered_at' => now()->toIso8601String()]]);
        $this->assertCount(1, $service->getPending($alert));
        $service->clearPending($alert);
        $this->assertSame([], $service->getPending($alert));

        $this->assertNull($service->getLastSentAt($alert));
        $service->setLastSentAt($alert);
        $this->assertNotNull($service->getLastSentAt($alert));
    }

    public function test_should_send_now_honors_interval_and_fallbacks(): void
    {
        Cache::flush();
        $alert = Alert::create([
            'name' => 'b2',
            'rule_type' => FailureCount::type(),
            'enabled' => true,
            'email_interval_minutes' => 10,
        ]);
        $service = new AlertBatchStore;

        $this->assertTrue($service->shouldSendNow($alert));

        $service->setLastSentAt($alert, now());
        $this->assertFalse($service->shouldSendNow($alert));

        Cache::put('horizonhub_alert_sent_at_' . $alert->id, now()->subMinutes(11)->toIso8601String(), now()->addMinutes(30));
        $this->assertTrue($service->shouldSendNow($alert));

        $alert->email_interval_minutes = 0;
        $this->assertTrue($service->shouldSendNow($alert));
    }
}
