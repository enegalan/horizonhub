<?php

namespace Tests\Unit;

use App\Models\Alert;
use App\Models\Service;
use App\Services\Alerts\AlertBatchStoreService;
use App\Services\Alerts\AlertNotificationDispatcherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlertEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_evaluate_after_event_calls_evaluator_for_job_failed_rule(): void
    {
        $batch = $this->createMock(AlertBatchStoreService::class);
        $batch->method('getPending')->willReturn([]);
        $batch->method('shouldSendNow')->willReturn(false);

        $dispatcher = $this->createMock(AlertNotificationDispatcherService::class);
        $dispatcher->expects($this->never())->method('dispatch');

        $service = Service::create([
            'name' => 'alert-svc-2',
            'base_url' => 'https://alerts-2.test',
            'status' => 'online',
        ]);

        Alert::query()->create([
            'name' => 'failure count',
            'service_ids' => [$service->id],
            'rule_type' => Alert::RULE_FAILURE_COUNT,
            'threshold' => null,
            'queue' => null,
            'job_type' => null,
            'enabled' => true,
        ]);

    }

    public function test_evaluate_after_event_does_not_call_evaluator_for_scheduled_only_rules(): void
    {
        $batch = $this->createMock(AlertBatchStoreService::class);
        $batch->method('getPending')->willReturn([]);
        $batch->method('shouldSendNow')->willReturn(false);

        $dispatcher = $this->createMock(AlertNotificationDispatcherService::class);
        $dispatcher->expects($this->never())->method('dispatch');

        $service = Service::create([
            'name' => 'alert-svc',
            'base_url' => 'https://alerts.test',
            'status' => 'online',
        ]);

        Alert::query()->create([
            'name' => 'queue blocked',
            'service_ids' => [],
            'rule_type' => Alert::RULE_QUEUE_BLOCKED,
            'threshold' => null,
            'queue' => null,
            'job_type' => null,
            'enabled' => true,
        ]);

    }

    public function test_evaluate_after_event_honors_multiple_selected_services(): void
    {
        $batch = $this->createMock(AlertBatchStoreService::class);
        $batch->method('getPending')->willReturn([]);
        $batch->method('shouldSendNow')->willReturn(false);

        $dispatcher = $this->createMock(AlertNotificationDispatcherService::class);
        $dispatcher->expects($this->never())->method('dispatch');

        $serviceOne = Service::create([
            'name' => 'alert-svc-3',
            'base_url' => 'https://alerts-3.test',
            'status' => 'online',
        ]);
        $serviceTwo = Service::create([
            'name' => 'alert-svc-4',
            'base_url' => 'https://alerts-4.test',
            'status' => 'online',
        ]);

        Alert::query()->create([
            'name' => 'scoped services alert',
            'service_ids' => [(int) $serviceOne->id, (int) $serviceTwo->id],
            'rule_type' => Alert::RULE_FAILURE_COUNT,
            'threshold' => null,
            'queue' => null,
            'job_type' => null,
            'enabled' => true,
        ]);
    }
}
