<?php

namespace Tests\Unit;

use App\Models\NotificationProvider;
use App\Models\Service;
use App\Services\Alerts\AlertUpsertService;
use App\Services\Alerts\Rules\Strategies\AvgExecutionTime;
use App\Services\Alerts\Rules\Strategies\FailureCount;
use App\Services\Notifiers\EmailNotifierService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AlertUpsertServiceValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_validate_alert_accepts_scope_without_disabled_service(): void
    {
        $enabled = Service::factory()->create(['enabled' => true]);
        Service::factory()->create(['enabled' => false]);
        $provider = NotificationProvider::query()->create([
            'name' => 'mail',
            'type' => EmailNotifierService::type(),
            'config' => ['to' => ['ops@example.com']],
        ]);

        $request = Request::create('/horizon/alerts', 'POST', [
            'name' => 'alert-scope',
            'rule_type' => FailureCount::type(),
            'service_ids' => [$enabled->id],
            'thresholdCount' => 1,
            'thresholdMinutes' => 5,
            'provider_ids' => [$provider->id],
            'email_interval_minutes' => 0,
            'enabled' => true,
        ]);

        $data = (new AlertUpsertService)->validateAlert($request);

        $this->assertSame([$enabled->id], $data['alert']['service_ids']);
    }

    public function test_validate_alert_builds_failure_count_payload_with_patterns(): void
    {
        $service = Service::query()->create(['name' => 'svc', 'base_url' => 'https://svc.test', 'status' => 'online']);
        $provider = NotificationProvider::query()->create([
            'name' => 'mail',
            'type' => EmailNotifierService::type(),
            'config' => ['to' => ['ops@example.com']],
        ]);

        $request = Request::create('/horizon/alerts', 'POST', [
            'name' => 'alert-a',
            'rule_type' => FailureCount::type(),
            'service_ids' => [$service->id, $service->id],
            'job_patterns' => [' App\\Jobs\\Sync ', ''],
            'queue_patterns' => ['default'],
            'thresholdCount' => 3,
            'thresholdMinutes' => 15,
            'provider_ids' => [$provider->id],
            'email_interval_minutes' => 10,
            'enabled' => true,
        ]);

        $data = (new AlertUpsertService)->validateAlert($request);

        $this->assertSame('alert-a', $data['alert']['name']);
        $this->assertSame([$service->id], $data['alert']['service_ids']);
        $this->assertSame(3, $data['alert']['threshold']['count']);
        $this->assertSame(15, $data['alert']['threshold']['minutes']);
        $this->assertSame(['App\\Jobs\\Sync'], $data['alert']['threshold']['job_patterns']);
        $this->assertSame(['default'], $data['alert']['threshold']['queue_patterns']);
        $this->assertSame([$provider->id], $data['provider_ids']);
    }

    public function test_validate_alert_requires_at_least_one_service(): void
    {
        $provider = NotificationProvider::query()->create([
            'name' => 'mail',
            'type' => EmailNotifierService::type(),
            'config' => ['to' => ['ops@example.com']],
        ]);

        $request = Request::create('/horizon/alerts', 'POST', [
            'name' => 'alert-no-services',
            'rule_type' => FailureCount::type(),
            'thresholdCount' => 1,
            'thresholdMinutes' => 5,
            'provider_ids' => [$provider->id],
            'email_interval_minutes' => 0,
            'enabled' => true,
        ]);

        $this->expectException(ValidationException::class);
        (new AlertUpsertService)->validateAlert($request);
    }

    public function test_validate_alert_requires_seconds_for_avg_execution_time_rule(): void
    {
        $service = Service::query()->create(['name' => 'svc', 'base_url' => 'https://svc.test', 'status' => 'online']);
        $provider = NotificationProvider::query()->create([
            'name' => 'mail',
            'type' => EmailNotifierService::type(),
            'config' => ['to' => ['ops@example.com']],
        ]);

        $request = Request::create('/horizon/alerts', 'POST', [
            'name' => 'alert-b',
            'rule_type' => AvgExecutionTime::type(),
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
