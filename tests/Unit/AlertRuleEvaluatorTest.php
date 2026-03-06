<?php

namespace Tests\Unit;

use App\Models\Alert;
use App\Models\HorizonFailedJob;
use App\Models\HorizonJob;
use App\Models\Service;
use App\Services\AlertRuleEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlertRuleEvaluatorTest extends TestCase {
    use RefreshDatabase;

    private function createService(array $overrides = array()): Service {
        return Service::create(array_merge(array(
            'name' => 'test-svc',
            'api_key' => 'key',
            'base_url' => 'https://a.com',
            'status' => 'online',
        ), $overrides));
    }

    public function test_job_specific_failure_returns_false_when_job_id_is_null(): void {
        $service = $this->createService();
        $alert = Alert::create([
            'service_id' => $service->id,
            'rule_type' => 'job_specific_failure',
            'threshold' => array(),
        ]);
        $evaluator = new AlertRuleEvaluator();

        $this->assertFalse($evaluator->evaluate($alert, $service->id, null));
    }

    public function test_job_specific_failure_returns_false_when_horizon_job_missing(): void {
        $service = $this->createService();
        $alert = Alert::create([
            'service_id' => $service->id,
            'rule_type' => 'job_specific_failure',
            'threshold' => array(),
        ]);
        $evaluator = new AlertRuleEvaluator();

        $this->assertFalse($evaluator->evaluate($alert, $service->id, 99999));
    }

    public function test_job_specific_failure_returns_true_when_failed_job_exists_and_matches_no_filters(): void {
        $service = $this->createService();
        $alert = Alert::create([
            'service_id' => $service->id,
            'rule_type' => 'job_specific_failure',
            'job_type' => null,
            'queue' => null,
            'threshold' => array(),
        ]);
        $job = HorizonJob::create([
            'service_id' => $service->id,
            'job_uuid' => 'uuid-1',
            'queue' => 'default',
            'status' => 'failed',
            'failed_at' => now(),
        ]);
        HorizonFailedJob::create([
            'service_id' => $service->id,
            'job_uuid' => 'uuid-1',
            'queue' => 'default',
            'payload' => array(),
            'exception' => 'err',
            'failed_at' => now(),
        ]);
        $evaluator = new AlertRuleEvaluator();

        $this->assertTrue($evaluator->evaluate($alert, $service->id, $job->id));
    }

    public function test_job_specific_failure_returns_false_when_failed_job_does_not_match_job_type(): void {
        $service = $this->createService();
        $alert = Alert::create([
            'service_id' => $service->id,
            'rule_type' => 'job_specific_failure',
            'job_type' => 'OtherJob',
            'queue' => null,
            'threshold' => array(),
        ]);
        $job = HorizonJob::create([
            'service_id' => $service->id,
            'job_uuid' => 'uuid-2',
            'queue' => 'default',
            'status' => 'failed',
            'failed_at' => now(),
        ]);
        HorizonFailedJob::create([
            'service_id' => $service->id,
            'job_uuid' => 'uuid-2',
            'queue' => 'default',
            'payload' => array('displayName' => 'App\\Jobs\\MyJob'),
            'exception' => 'err',
            'failed_at' => now(),
        ]);
        $evaluator = new AlertRuleEvaluator();

        $this->assertFalse($evaluator->evaluate($alert, $service->id, $job->id));
    }

    public function test_job_specific_failure_returns_true_when_failed_job_matches_job_type(): void {
        $service = $this->createService();
        $alert = Alert::create([
            'service_id' => $service->id,
            'rule_type' => 'job_specific_failure',
            'job_type' => 'MyJob',
            'queue' => null,
            'threshold' => array(),
        ]);
        $job = HorizonJob::create([
            'service_id' => $service->id,
            'job_uuid' => 'uuid-3',
            'queue' => 'default',
            'status' => 'failed',
            'failed_at' => now(),
        ]);
        HorizonFailedJob::create([
            'service_id' => $service->id,
            'job_uuid' => 'uuid-3',
            'queue' => 'default',
            'payload' => array('displayName' => 'App\\Jobs\\MyJob'),
            'exception' => 'err',
            'failed_at' => now(),
        ]);
        $evaluator = new AlertRuleEvaluator();

        $this->assertTrue($evaluator->evaluate($alert, $service->id, $job->id));
    }

    public function test_job_type_failure_returns_true_when_recent_failed_job_matches_type(): void {
        $service = $this->createService();
        $alert = Alert::create([
            'service_id' => $service->id,
            'rule_type' => 'job_type_failure',
            'job_type' => 'SendEmail',
            'threshold' => array(),
        ]);
        HorizonFailedJob::create([
            'service_id' => $service->id,
            'job_uuid' => 'u1',
            'queue' => 'default',
            'payload' => array('displayName' => 'App\\Jobs\\SendEmail'),
            'exception' => 'err',
            'failed_at' => now(),
        ]);
        $evaluator = new AlertRuleEvaluator();

        $this->assertTrue($evaluator->evaluate($alert, $service->id, null));
    }

    public function test_job_type_failure_returns_false_when_no_matching_failed_job(): void {
        $service = $this->createService();
        $alert = Alert::create([
            'service_id' => $service->id,
            'rule_type' => 'job_type_failure',
            'job_type' => 'SendEmail',
            'threshold' => array(),
        ]);
        HorizonFailedJob::create([
            'service_id' => $service->id,
            'job_uuid' => 'u1',
            'queue' => 'default',
            'payload' => array('displayName' => 'App\\Jobs\\OtherJob'),
            'exception' => 'err',
            'failed_at' => now(),
        ]);
        $evaluator = new AlertRuleEvaluator();

        $this->assertFalse($evaluator->evaluate($alert, $service->id, null));
    }

    public function test_failure_count_returns_true_when_threshold_met(): void {
        $service = $this->createService();
        $alert = Alert::create([
            'service_id' => $service->id,
            'rule_type' => 'failure_count',
            'threshold' => array('count' => 2, 'minutes' => 60),
        ]);
        HorizonFailedJob::create([
            'service_id' => $service->id,
            'job_uuid' => 'u1',
            'queue' => 'default',
            'payload' => array(),
            'exception' => 'e',
            'failed_at' => now(),
        ]);
        HorizonFailedJob::create([
            'service_id' => $service->id,
            'job_uuid' => 'u2',
            'queue' => 'default',
            'payload' => array(),
            'exception' => 'e',
            'failed_at' => now(),
        ]);
        $evaluator = new AlertRuleEvaluator();

        $this->assertTrue($evaluator->evaluate($alert, $service->id, null));
    }

    public function test_failure_count_returns_false_when_below_threshold(): void {
        $service = $this->createService();
        $alert = Alert::create([
            'service_id' => $service->id,
            'rule_type' => 'failure_count',
            'threshold' => array('count' => 3, 'minutes' => 60),
        ]);
        HorizonFailedJob::create([
            'service_id' => $service->id,
            'job_uuid' => 'u1',
            'queue' => 'default',
            'payload' => array(),
            'exception' => 'e',
            'failed_at' => now(),
        ]);
        $evaluator = new AlertRuleEvaluator();

        $this->assertFalse($evaluator->evaluate($alert, $service->id, null));
    }

    public function test_avg_execution_time_returns_true_when_avg_above_threshold(): void {
        $service = $this->createService();
        $alert = Alert::create([
            'service_id' => $service->id,
            'rule_type' => 'avg_execution_time',
            'threshold' => array('seconds' => 10, 'minutes' => 60),
        ]);
        HorizonJob::create([
            'service_id' => $service->id,
            'job_uuid' => 'j1',
            'queue' => 'default',
            'status' => 'processed',
            'processed_at' => now(),
            'queued_at' => now()->subSeconds(20),
            'runtime_seconds' => 20,
        ]);
        $evaluator = new AlertRuleEvaluator();

        $this->assertTrue($evaluator->evaluate($alert, $service->id, null));
    }

    public function test_avg_execution_time_returns_false_when_avg_below_threshold(): void {
        $service = $this->createService();
        $alert = Alert::create([
            'service_id' => $service->id,
            'rule_type' => 'avg_execution_time',
            'threshold' => array('seconds' => 100, 'minutes' => 60),
        ]);
        HorizonJob::create([
            'service_id' => $service->id,
            'job_uuid' => 'j1',
            'queue' => 'default',
            'status' => 'processed',
            'processed_at' => now(),
            'queued_at' => now()->subSeconds(5),
            'runtime_seconds' => 5,
        ]);
        $evaluator = new AlertRuleEvaluator();

        $this->assertFalse($evaluator->evaluate($alert, $service->id, null));
    }

    public function test_avg_execution_time_returns_false_when_no_jobs(): void {
        $service = $this->createService();
        $alert = Alert::create([
            'service_id' => $service->id,
            'rule_type' => 'avg_execution_time',
            'threshold' => array('seconds' => 10, 'minutes' => 60),
        ]);
        $evaluator = new AlertRuleEvaluator();

        $this->assertFalse($evaluator->evaluate($alert, $service->id, null));
    }

    public function test_queue_blocked_returns_true_when_last_processed_too_old(): void {
        $service = $this->createService();
        $alert = Alert::create([
            'service_id' => $service->id,
            'rule_type' => 'queue_blocked',
            'threshold' => array('minutes' => 30),
        ]);
        HorizonJob::create([
            'service_id' => $service->id,
            'job_uuid' => 'j1',
            'queue' => 'default',
            'status' => 'processed',
            'processed_at' => now()->subMinutes(45),
        ]);
        $evaluator = new AlertRuleEvaluator();

        $this->assertTrue($evaluator->evaluate($alert, $service->id, null));
    }

    public function test_queue_blocked_returns_false_when_last_processed_recent(): void {
        $service = $this->createService();
        $alert = Alert::create([
            'service_id' => $service->id,
            'rule_type' => 'queue_blocked',
            'threshold' => array('minutes' => 30),
        ]);
        HorizonJob::create([
            'service_id' => $service->id,
            'job_uuid' => 'j1',
            'queue' => 'default',
            'status' => 'processed',
            'processed_at' => now()->subMinutes(5),
        ]);
        $evaluator = new AlertRuleEvaluator();

        $this->assertFalse($evaluator->evaluate($alert, $service->id, null));
    }

    public function test_worker_offline_returns_true_when_last_seen_too_old(): void {
        $service = $this->createService(array('last_seen_at' => now()->subMinutes(10)));
        $alert = Alert::create([
            'service_id' => $service->id,
            'rule_type' => 'worker_offline',
            'threshold' => array('minutes' => 5),
        ]);
        $evaluator = new AlertRuleEvaluator();

        $this->assertTrue($evaluator->evaluate($alert, $service->id, null));
    }

    public function test_worker_offline_returns_false_when_last_seen_recent(): void {
        $service = $this->createService(array('last_seen_at' => now()->subMinutes(2)));
        $alert = Alert::create([
            'service_id' => $service->id,
            'rule_type' => 'worker_offline',
            'threshold' => array('minutes' => 5),
        ]);
        $evaluator = new AlertRuleEvaluator();

        $this->assertFalse($evaluator->evaluate($alert, $service->id, null));
    }

    public function test_unknown_rule_type_returns_false(): void {
        $service = $this->createService();
        $alert = Alert::create([
            'service_id' => $service->id,
            'rule_type' => 'unknown_type',
            'threshold' => array(),
        ]);
        $evaluator = new AlertRuleEvaluator();

        $this->assertFalse($evaluator->evaluate($alert, $service->id, null));
    }
}
