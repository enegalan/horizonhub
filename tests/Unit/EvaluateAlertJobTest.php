<?php

namespace Tests\Unit;

use App\Jobs\EvaluateAlertJob;
use App\Models\Alert;
use App\Services\Alerts\Engine\AlertEngine;
use App\Services\Alerts\Rules\Strategies\FailureCount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class EvaluateAlertJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_catches_engine_exceptions_and_updates_error_counters(): void
    {
        Cache::flush();
        $alert = Alert::create([
            'name' => 'x2',
            'rule_type' => FailureCount::type(),
            'enabled' => true,
        ]);
        $engine = $this->createMock(AlertEngine::class);
        $engine->method('evaluateAlert')->willThrowException(new \RuntimeException('engine-fail'));

        (new EvaluateAlertJob($alert->id, 'eval-c'))->handle($engine);

        $this->assertEquals(1, Cache::get('horizonhub.alert_evaluation_batches.eval-c.evaluated_count'));
        $this->assertEquals(1, Cache::get('horizonhub.alert_evaluation_batches.eval-c.error_count'));
        $this->assertSame('engine-fail', Cache::get('horizonhub.alert_evaluation_batches.eval-c.first_error_message'));
    }

    public function test_job_records_not_found_as_error_counters(): void
    {
        Cache::flush();
        $job = new EvaluateAlertJob(99999, 'eval-a');
        $engine = $this->createMock(AlertEngine::class);
        $job->handle($engine);

        $this->assertEquals(1, Cache::get('horizonhub.alert_evaluation_batches.eval-a.evaluated_count'));
        $this->assertEquals(1, Cache::get('horizonhub.alert_evaluation_batches.eval-a.error_count'));
        $this->assertSame('Alert not found', Cache::get('horizonhub.alert_evaluation_batches.eval-a.first_error_message'));
    }

    public function test_job_updates_counters_and_first_error_message_once(): void
    {
        Cache::flush();
        $alert = Alert::create([
            'name' => 'x',
            'rule_type' => FailureCount::type(),
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
