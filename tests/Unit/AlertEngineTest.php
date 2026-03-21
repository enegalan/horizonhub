<?php

namespace Tests\Unit;

use App\Models\Alert;
use App\Models\Service;
use App\Services\AlertEngine;
use App\Services\AlertRuleEvaluator;
use App\Services\Alerts\AlertBatchStore;
use App\Services\Alerts\AlertNotificationDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlertEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_evaluate_after_event_does_not_call_evaluator_for_scheduled_only_rules(): void
    {
        $evaluator = $this->createMock(AlertRuleEvaluator::class);
        $evaluator->expects($this->never())->method('evaluateWithTriggeringJobs');

        $batch = $this->createMock(AlertBatchStore::class);
        $batch->method('getPending')->willReturn([]);
        $batch->method('shouldSendNow')->willReturn(false);

        $dispatcher = $this->createMock(AlertNotificationDispatcher::class);
        $dispatcher->expects($this->never())->method('dispatch');

        $service = Service::create([
            'name' => 'alert-svc',
            'api_key' => 'a52345678901234567890123456789012345678901234567890123456789012',
            'base_url' => 'https://alerts.test',
            'status' => 'online',
        ]);

        Alert::query()->create([
            'name' => 'queue blocked',
            'service_id' => null,
            'rule_type' => Alert::RULE_QUEUE_BLOCKED,
            'threshold' => null,
            'queue' => null,
            'job_type' => null,
            'enabled' => true,
        ]);

        $engine = new AlertEngine($evaluator, $batch, $dispatcher);
        $engine->evaluateAfterEvent((int) $service->id, 'JobFailed', 'job-uuid');
    }

    public function test_evaluate_after_event_calls_evaluator_for_job_failed_rule(): void
    {
        $evaluator = $this->createMock(AlertRuleEvaluator::class);
        $evaluator->expects($this->once())
            ->method('evaluateWithTriggeringJobs')
            ->willReturn(['triggered' => false, 'job_uuids' => []]);

        $batch = $this->createMock(AlertBatchStore::class);
        $batch->method('getPending')->willReturn([]);
        $batch->method('shouldSendNow')->willReturn(false);

        $dispatcher = $this->createMock(AlertNotificationDispatcher::class);
        $dispatcher->expects($this->never())->method('dispatch');

        $service = Service::create([
            'name' => 'alert-svc-2',
            'api_key' => 'a62345678901234567890123456789012345678901234567890123456789012',
            'base_url' => 'https://alerts-2.test',
            'status' => 'online',
        ]);

        Alert::query()->create([
            'name' => 'failure count',
            'service_id' => null,
            'rule_type' => Alert::RULE_FAILURE_COUNT,
            'threshold' => null,
            'queue' => null,
            'job_type' => null,
            'enabled' => true,
        ]);

        $engine = new AlertEngine($evaluator, $batch, $dispatcher);
        $engine->evaluateAfterEvent((int) $service->id, 'JobFailed', 'job-uuid');
    }
}
