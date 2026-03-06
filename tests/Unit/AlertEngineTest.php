<?php

namespace Tests\Unit;

use App\Models\Alert;
use App\Models\HorizonFailedJob;
use App\Models\AlertLog;
use App\Models\Service;
use App\Services\AlertEngine;
use App\Services\AlertRuleEvaluator;
use App\Services\EmailNotifier;
use App\Services\SlackNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlertEngineTest extends TestCase {
    use RefreshDatabase;

    public function test_failure_count_rule_creates_alert_log_when_threshold_met(): void {
        $service = Service::create([
            'name' => 'svc',
            'api_key' => 'key',
            'base_url' => 'https://a.com',
            'status' => 'online',
        ]);
        Alert::create([
            'service_id' => $service->id,
            'rule_type' => 'failure_count',
            'threshold' => ['count' => 2, 'minutes' => 60],
            'notification_channels' => [],
        ]);
        HorizonFailedJob::create([
            'service_id' => $service->id,
            'job_uuid' => 'u1',
            'queue' => 'default',
            'payload' => [],
            'exception' => 'err',
            'failed_at' => \now(),
        ]);
        HorizonFailedJob::create([
            'service_id' => $service->id,
            'job_uuid' => 'u2',
            'queue' => 'default',
            'payload' => [],
            'exception' => 'err',
            'failed_at' => \now(),
        ]);
        $engine = new AlertEngine(
            $this->createMock(EmailNotifier::class),
            $this->createMock(SlackNotifier::class),
            new AlertRuleEvaluator()
        );
        $engine->evaluateAfterEvent($service->id, 'JobFailed', null);

        $this->assertSame(1, AlertLog::count());
        $log = AlertLog::first();
        $this->assertNotNull($log);
        $this->assertSame('sent', $log->status);
        $this->assertSame($service->id, $log->service_id);
    }
}
