<?php

namespace Tests\Unit;

use App\Http\Requests\Horizon\UpsertAlertRequest;
use App\Models\NotificationProvider;
use App\Models\Service;
use App\Services\Alerts\AlertUpsertService;
use App\Services\Alerts\Rules\Strategies\AvgExecutionTime;
use App\Services\Alerts\Rules\Strategies\FailureCount;
use App\Services\Notifiers\EmailNotifierService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AlertUpsertServiceValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolved_rule_type_falls_back_to_the_first_registered_rule(): void
    {
        $request = UpsertAlertRequest::create('/horizon/alerts', 'POST', []);

        $this->assertSame('failure_count', $request->resolvedRuleType());
    }

    public function test_threshold_count_is_only_required_for_failure_count(): void
    {
        $request = UpsertAlertRequest::create('/horizon/alerts', 'POST', [
            'rule_type' => FailureCount::type()->value,
        ]);
        $rules = $request->rules();

        $this->assertSame('required', $rules['thresholdCount'][0]);
        $this->assertSame('nullable', $rules['thresholdSeconds'][0]);
    }

    public function test_threshold_requirements_follow_the_resolved_rule_type(): void
    {
        $request = UpsertAlertRequest::create('/horizon/alerts', 'POST', [
            'rule_type' => AvgExecutionTime::type()->value,
        ]);
        $rules = $request->rules();

        $this->assertSame('required', $rules['thresholdSeconds'][0]);
        $this->assertSame('required', $rules['thresholdMinutes'][0]);
        $this->assertSame('nullable', $rules['thresholdCount'][0]);
    }

    public function test_validate_alert_accepts_scope_without_disabled_service(): void
    {
        $enabled = Service::factory()->create(['enabled' => true]);
        Service::factory()->create(['enabled' => false]);
        $provider = NotificationProvider::create([
            'name' => 'mail',
            'type' => EmailNotifierService::type(),
            'config' => ['to' => ['ops@example.com']],
        ]);

        $data = $this->buildUpsertData([
            'name' => 'alert-scope',
            'rule_type' => FailureCount::type()->value,
            'service_ids' => [$enabled->id],
            'thresholdCount' => 1,
            'thresholdMinutes' => 5,
            'provider_ids' => [$provider->id],
            'email_interval_minutes' => 0,
            'enabled' => true,
        ]);

        $this->assertSame([$enabled->id], $data['alert']['service_ids']);
    }

    public function test_validate_alert_builds_failure_count_payload_with_patterns(): void
    {
        $service = Service::create(['name' => 'svc', 'base_url' => 'https://svc.test', 'status' => 'online']);
        $provider = NotificationProvider::create([
            'name' => 'mail',
            'type' => EmailNotifierService::type(),
            'config' => ['to' => ['ops@example.com']],
        ]);

        $data = $this->buildUpsertData([
            'name' => 'alert-a',
            'rule_type' => FailureCount::type()->value,
            'service_ids' => [$service->id, $service->id],
            'job_patterns' => [' App\\Jobs\\Sync ', ''],
            'queue_patterns' => ['default'],
            'thresholdCount' => 3,
            'thresholdMinutes' => 15,
            'provider_ids' => [$provider->id],
            'email_interval_minutes' => 10,
            'enabled' => true,
        ]);

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
        $provider = NotificationProvider::create([
            'name' => 'mail',
            'type' => EmailNotifierService::type(),
            'config' => ['to' => ['ops@example.com']],
        ]);

        $this->expectException(ValidationException::class);
        $this->buildUpsertData([
            'name' => 'alert-no-services',
            'rule_type' => FailureCount::type()->value,
            'thresholdCount' => 1,
            'thresholdMinutes' => 5,
            'provider_ids' => [$provider->id],
            'email_interval_minutes' => 0,
            'enabled' => true,
        ]);
    }

    public function test_validate_alert_requires_seconds_for_avg_execution_time_rule(): void
    {
        $service = Service::create(['name' => 'svc', 'base_url' => 'https://svc.test', 'status' => 'online']);
        $provider = NotificationProvider::create([
            'name' => 'mail',
            'type' => EmailNotifierService::type(),
            'config' => ['to' => ['ops@example.com']],
        ]);

        $this->expectException(ValidationException::class);
        $this->buildUpsertData([
            'name' => 'alert-b',
            'rule_type' => AvgExecutionTime::type()->value,
            'service_ids' => [$service->id],
            'thresholdMinutes' => 5,
            'provider_ids' => [$provider->id],
            'email_interval_minutes' => 0,
            'enabled' => true,
        ]);
    }

    /**
     * Run the Form Request rules against a payload and build the persistence payload.
     *
     * `setValidator()` is what `FormRequest::validateResolved()` does at runtime,
     * so the request behaves exactly as it would during a real request. The
     * validator is run directly rather than through `validateResolved()` so the
     * failure path throws `ValidationException` instead of trying to build a
     * redirect response, which needs a session-bound request.
     *
     * @param array<string, mixed> $payload
     *
     * @return array{alert: array<string, mixed>, provider_ids: array<int>}
     */
    private function buildUpsertData(array $payload): array
    {
        $request = UpsertAlertRequest::create('/horizon/alerts', 'POST', $payload);
        $request->setContainer($this->app);
        $validator = Validator::make($payload, $request->rules());
        $request->setValidator($validator);
        $validator->validate();

        return (new AlertUpsertService)->buildUpsertData($request);
    }
}
