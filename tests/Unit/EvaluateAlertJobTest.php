<?php

namespace Tests\Unit;

use App\Jobs\EvaluateAlertJob;
use App\Models\Alert;
use App\Services\Alerts\AlertEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class EvaluateAlertJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_catches_engine_exceptions_and_writes_error_result(): void
    {
        Cache::flush();
        $alert = Alert::query()->create([
            'name' => 'x2',
            'rule_type' => Alert::RULE_FAILURE_COUNT,
            'enabled' => true,
        ]);
        $engine = $this->createMock(AlertEngine::class);
        $engine->method('evaluateAlert')->willThrowException(new \RuntimeException('engine-fail'));

        (new EvaluateAlertJob($alert->id, 'eval-c'))->handle($engine);

        $result = Cache::get('horizonhub.alert_evaluation_batches.eval-c.results.' . $alert->id);
        $this->assertSame('engine-fail', $result['error_message']);
        $this->assertEquals(1, Cache::get('horizonhub.alert_evaluation_batches.eval-c.error_count'));
    }

    public function test_job_stores_not_found_result_and_error_counters(): void
    {
        Cache::flush();
        $job = new EvaluateAlertJob(99999, 'eval-a');
        $engine = $this->createMock(AlertEngine::class);
        $job->handle($engine);

        $result = Cache::get('horizonhub.alert_evaluation_batches.eval-a.results.99999');
        $this->assertSame('Alert not found', $result['error_message']);
        $this->assertEquals(1, Cache::get('horizonhub.alert_evaluation_batches.eval-a.evaluated_count'));
        $this->assertEquals(1, Cache::get('horizonhub.alert_evaluation_batches.eval-a.error_count'));
    }

    public function test_job_updates_counters_and_first_error_message_once(): void
    {
        Cache::flush();
        $alert = Alert::query()->create([
            'name' => 'x',
            'rule_type' => Alert::RULE_FAILURE_COUNT,
            'enabled' => true,
        ]);

        $engine = $this->createMock(AlertEngine::class);
        $engine->method('evaluateAlert')->willReturn([
            'alert_id' => $alert->id,
            'pending_flushed' => false,
            'triggered' => true,
            'triggered_service_id' => 3,
            'error_message' => 'first error',
            'pending_flush_error_message' => null,
            'delivered' => true,
        ]);

        (new EvaluateAlertJob($alert->id, 'eval-b'))->handle($engine);
        $this->assertEquals(1, Cache::get('horizonhub.alert_evaluation_batches.eval-b.triggered_count'));
        $this->assertEquals(1, Cache::get('horizonhub.alert_evaluation_batches.eval-b.delivered_count'));
        $this->assertSame('first error', Cache::get('horizonhub.alert_evaluation_batches.eval-b.first_error_message'));

        Cache::put('horizonhub.alert_evaluation_batches.eval-b.first_error_message', 'locked', 1800);
        (new EvaluateAlertJob($alert->id, 'eval-b'))->handle($engine);
        $this->assertSame('locked', Cache::get('horizonhub.alert_evaluation_batches.eval-b.first_error_message'));
    }
}
