<?php

namespace Tests\Unit;

use App\Models\Alert;
use App\Models\NotificationProvider;
use App\Models\Service;
use App\Services\Alerts\AlertUpsertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AlertUpsertServiceValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_validate_alert_builds_failure_count_payload_with_patterns_and_queue_fallback(): void
    {
        $service = Service::query()->create(['name' => 'svc', 'base_url' => 'https://svc.test', 'status' => 'online']);
        $provider = NotificationProvider::query()->create([
            'name' => 'mail',
            'type' => NotificationProvider::TYPE_EMAIL,
            'config' => ['to' => ['ops@example.com']],
        ]);

        $request = Request::create('/horizon/alerts', 'POST', [
            'name' => 'alert-a',
            'rule_type' => Alert::RULE_FAILURE_COUNT,
            'service_ids' => [$service->id, $service->id],
            'queue' => 'default',
            'job_type' => 'App\\Jobs\\Sync',
            'job_patterns' => [' App\\Jobs\\Sync ', ''],
            'queue_patterns' => [],
            'thresholdCount' => 3,
            'thresholdMinutes' => 15,
            'provider_ids' => [$provider->id],
            'email_interval_minutes' => 10,
            'enabled' => true,
        ]);

        $data = (new AlertUpsertService)->validateAlert($request);

        $this->assertSame('alert-a', $data['alert']['name']);
        $this->assertSame([$service->id], $data['alert']['service_ids']);
        $this->assertSame('default', $data['alert']['queue']);
        $this->assertSame(3, $data['alert']['threshold']['count']);
        $this->assertSame(15, $data['alert']['threshold']['minutes']);
        $this->assertSame(['App\\Jobs\\Sync'], $data['alert']['threshold']['job_patterns']);
        $this->assertSame(['default'], $data['alert']['threshold']['queue_patterns']);
        $this->assertSame([$provider->id], $data['provider_ids']);
    }

    public function test_validate_alert_requires_seconds_for_avg_execution_time_rule(): void
    {
        $service = Service::query()->create(['name' => 'svc', 'base_url' => 'https://svc.test', 'status' => 'online']);
        $provider = NotificationProvider::query()->create([
            'name' => 'mail',
            'type' => NotificationProvider::TYPE_EMAIL,
            'config' => ['to' => ['ops@example.com']],
        ]);

        $request = Request::create('/horizon/alerts', 'POST', [
            'name' => 'alert-b',
            'rule_type' => Alert::RULE_AVG_EXECUTION_TIME,
            'service_ids' => [$service->id],
            'thresholdMinutes' => 5,
            'provider_ids' => [$provider->id],
            'email_interval_minutes' => 0,
            'enabled' => true,
        ]);

        $this->expectException(ValidationException::class);
        (new AlertUpsertService)->validateAlert($request);
    }
}
