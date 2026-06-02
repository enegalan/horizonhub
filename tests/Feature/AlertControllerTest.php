<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\AlertLog;
use App\Models\NotificationProvider;
use App\Models\Service;
use App\Services\Alerts\AlertEvaluationBatchService;
use App\Services\Alerts\AlertUpsertService;
use App\Services\Alerts\Engine\AlertEngine;
use App\Services\Alerts\Rules\Strategies\FailureCount;
use App\Services\Notifiers\EmailNotifierService;
use App\Support\FormDrawer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlertControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_destroy_evaluate_single_show_store_and_update_paths(): void
    {
        $service = Service::query()->create(['name' => 'svc', 'base_url' => 'https://x.test', 'status' => 'online']);
        $provider = NotificationProvider::query()->create([
            'name' => 'mail',
            'type' => EmailNotifierService::type(),
            'config' => ['to' => ['a@example.com']],
        ]);
        $alert = Alert::query()->create([
            'name' => 'old-alert',
            'rule_type' => FailureCount::type(),
            'enabled' => true,
            'service_ids' => [$service->id],
        ]);
        $alert->notificationProviders()->sync([$provider->id]);

        AlertLog::query()->create([
            'alert_id' => $alert->id,
            'service_id' => $service->id,
            'status' => 'sent',
            'trigger_count' => 1,
            'sent_at' => now(),
        ]);

        $engine = $this->createMock(AlertEngine::class);
        $engine->method('evaluateAlert')->willReturn([
            'alert_id' => $alert->id,
            'pending_flushed' => false,
            'triggered' => false,
            'triggered_service_id' => null,
            'error_message' => null,
            'pending_flush_error_message' => null,
            'delivered' => false,
        ]);
        $this->app->instance(AlertEngine::class, $engine);

        $upsert = $this->createMock(AlertUpsertService::class);
        $upsert->method('buildFormViewVariables')->willReturn([
            'alert' => $alert,
            'services' => collect([$service]),
            'providers' => collect([$provider]),
            'ruleTypes' => [FailureCount::type() => 'Failure count in window'],
            'selectedProviderIds' => [$provider->id],
            'selectedServiceIds' => [$service->id],
            'header' => 'Edit alert',
        ]);
        $upsert->method('validateAlert')->willReturn([
            'alert' => [
                'name' => 'new-alert',
                'service_ids' => [$service->id],
                'rule_type' => FailureCount::type(),
                'threshold' => ['count' => 1, 'minutes' => 5],
                'enabled' => true,
                'email_interval_minutes' => 0,
            ],
            'provider_ids' => [$provider->id],
        ]);
        $this->app->instance(AlertUpsertService::class, $upsert);

        $this->get(route('horizon.alerts.edit', ['alert' => $alert]), [
            'Turbo-Frame' => FormDrawer::FRAME_ID,
        ])->assertOk();
        $this->get(route('horizon.alerts.show', ['alert' => $alert]))->assertOk();
        $this->post(route('horizon.alerts.evaluate', ['alert' => $alert]))->assertOk()->assertJsonPath('alert_id', $alert->id);

        $this->post(route('horizon.alerts.store'))->assertRedirect(route('horizon.alerts.index'));
        $created = Alert::query()->where('name', 'new-alert')->latest('id')->first();
        $this->assertNotNull($created);

        $this->put(route('horizon.alerts.update', ['alert' => $alert]))->assertRedirect(route('horizon.alerts.index'));
        $alert->refresh();
        $this->assertSame('new-alert', $alert->name);

        $this->delete(route('horizon.alerts.destroy', ['alert' => $alert]))->assertRedirect(route('horizon.alerts.index'));
        $this->assertDatabaseMissing('alerts', ['id' => $alert->id]);
    }

    public function test_evaluate_all_and_status_return_json_from_batch_service(): void
    {
        $batch = $this->createMock(AlertEvaluationBatchService::class);
        $batch->method('startEvaluateAll')->willReturn([
            'evaluation_id' => 'ev-x',
            'status' => 'running',
            'total_alerts' => 2,
        ]);
        $batch->method('getEvaluationStatus')->willReturn([
            'evaluation_id' => 'ev-x',
            'status' => 'completed',
            'total_alerts' => 2,
            'evaluated_count' => 2,
            'triggered_count' => 1,
            'delivered_count' => 1,
            'error_count' => 0,
            'first_error_message' => null,
            'error_message' => null,
        ]);
        $this->app->instance(AlertEvaluationBatchService::class, $batch);

        $this->post(route('horizon.alerts.evaluate-all'))
            ->assertOk()
            ->assertJsonPath('evaluation_id', 'ev-x');

        $this->get(route('horizon.alerts.evaluations.status', ['evaluationId' => 'ev-x']))
            ->assertOk()
            ->assertJsonPath('status', 'completed');
    }

    public function test_index_and_create_pages_render(): void
    {
        Alert::query()->create([
            'name' => 'a1',
            'rule_type' => FailureCount::type(),
            'enabled' => true,
        ]);

        $this->get(route('horizon.alerts.index'))->assertOk();
        $this->get(route('horizon.alerts.create'), [
            'Turbo-Frame' => FormDrawer::FRAME_ID,
        ])->assertOk();
    }

    public function test_retry_log_calls_engine_only_for_failed_logs(): void
    {
        $service = Service::query()->create(['name' => 'svc', 'base_url' => 'https://x.test', 'status' => 'online']);
        $alert = Alert::query()->create(['name' => 'a1', 'rule_type' => FailureCount::type(), 'enabled' => true]);
        $failedLog = AlertLog::query()->create([
            'alert_id' => $alert->id,
            'service_id' => $service->id,
            'status' => 'failed',
            'trigger_count' => 1,
            'sent_at' => now(),
        ]);
        $sentLog = AlertLog::query()->create([
            'alert_id' => $alert->id,
            'service_id' => $service->id,
            'status' => 'sent',
            'trigger_count' => 1,
            'sent_at' => now(),
        ]);

        $engine = $this->createMock(AlertEngine::class);
        $engine->expects($this->once())->method('retryAlertLog');
        $this->app->instance(AlertEngine::class, $engine);

        $this->post(route('horizon.alerts.logs.retry', ['log' => $sentLog]))->assertRedirect();
        $this->post(route('horizon.alerts.logs.retry', ['log' => $failedLog]))->assertRedirect();
    }

    public function test_toggle_enabled_updates_alert_state(): void
    {
        $alert = Alert::query()->create([
            'name' => 'toggle-alert',
            'rule_type' => FailureCount::type(),
            'enabled' => true,
        ]);

        $this->post(route('horizon.alerts.toggle-enabled', ['alert' => $alert]))
            ->assertOk()
            ->assertJsonPath('alert_id', $alert->id)
            ->assertJsonPath('enabled', false);

        $alert->refresh();
        $this->assertFalse($alert->enabled);

        $this->post(route('horizon.alerts.toggle-enabled', ['alert' => $alert]))
            ->assertOk()
            ->assertJsonPath('enabled', true);

        $alert->refresh();
        $this->assertTrue($alert->enabled);
    }
}
