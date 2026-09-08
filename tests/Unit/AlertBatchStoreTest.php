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

    public function test_late_updates_are_rejected_when_evaluation_status_is_absent(): void
    {
        Cache::flush();
        $store = new AlertBatchStore;

        $this->assertSame('expired', $store->getStatus('gone'));

        $store->recordEvaluationError('gone', 'too late');
        $store->recordEvaluationResult('gone', ['triggered' => true, 'delivered' => true, 'error_message' => 'x']);
        $store->markCompleted('gone');
        $store->markBatchFailed('gone', 'fail');
        $store->putTotalAlerts('gone', 5);
        $store->initializeCounters('gone');

        $this->assertFalse(Cache::has('horizonhub.alert_evaluation_batches.gone.status'));
        $this->assertFalse(Cache::has('horizonhub.alert_evaluation_batches.gone.evaluated_count'));
        $this->assertFalse(Cache::has('horizonhub.alert_evaluation_batches.gone.error_count'));
        $this->assertFalse(Cache::has('horizonhub.alert_evaluation_batches.gone.triggered_count'));
        $this->assertFalse(Cache::has('horizonhub.alert_evaluation_batches.gone.delivered_count'));
        $this->assertFalse(Cache::has('horizonhub.alert_evaluation_batches.gone.total_alerts'));
        $this->assertFalse(Cache::has('horizonhub.alert_evaluation_batches.gone.first_error_message'));
        $this->assertFalse(Cache::has('horizonhub.alert_evaluation_batches.gone.error_message'));
    }

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

    public function test_record_evaluation_updates_preserve_ttl_for_active_evaluations(): void
    {
        Cache::flush();
        $store = new AlertBatchStore;
        $store->putStatus('active', 'running');
        $store->initializeCounters('active');

        $store->recordEvaluationResult('active', [
            'triggered' => true,
            'delivered' => true,
            'error_message' => 'first',
        ]);
        $store->recordEvaluationError('active', 'second');

        $this->assertSame('running', $store->getStatus('active'));
        $this->assertSame(2, $store->getEvaluatedCount('active'));
        $this->assertSame(1, $store->getTriggeredCount('active'));
        $this->assertSame(1, $store->getDeliveredCount('active'));
        $this->assertSame(2, $store->getErrorCount('active'));
        $this->assertSame('first', $store->getFirstErrorMessage('active'));
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
