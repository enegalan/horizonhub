<?php

namespace Tests\Feature;

use App\Enums\AlertLogStatus;
use App\Enums\NotificationProviderType;
use App\Mail\AlertBatchedMail;
use App\Models\Alert;
use App\Models\AlertLog;
use App\Models\NotificationProvider;
use App\Models\Service;
use App\Services\Alerts\Engine\AlertBatchStore;
use App\Services\Alerts\Engine\AlertEngine;
use App\Services\Alerts\Rules\AlertRuleStrategyRegistry;
use App\Services\Alerts\Rules\Strategies\FailureCount;
use App\Services\Notifiers\EmailNotifierService;
use App\Services\Notifiers\SlackNotifierService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AlertDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_failing_provider_does_not_block_remaining_providers(): void
    {
        Mail::fake();
        Http::fake(['*' => Http::response('server error', 500)]);

        $service = $this->private__service();
        $emailProvider = $this->private__provider(EmailNotifierService::type(), ['to' => ['ops@example.com']]);
        $slackProvider = $this->private__provider(SlackNotifierService::type(), ['webhook_url' => 'https://hooks.slack.test/delivery']);

        $alert = $this->private__alertWithProvider($slackProvider, $emailProvider);
        $log = $this->private__log($alert, $service);

        $this->private__engine()->dispatch($alert, [$this->private__event($service)], $log);

        Mail::assertSent(AlertBatchedMail::class);
        $this->assertSame(AlertLogStatus::Failed, $log->refresh()->status);
    }

    public function test_slack_delivery_failure_marks_alert_log_failed(): void
    {
        Http::fake(['*' => Http::response('server error', 500)]);

        $service = $this->private__service();
        $alert = $this->private__alertWithProvider(
            $this->private__provider(SlackNotifierService::type(), ['webhook_url' => 'https://hooks.slack.test/delivery']),
        );
        $log = $this->private__log($alert, $service);

        $this->private__engine()->dispatch($alert, [$this->private__event($service)], $log);

        $log->refresh();
        $this->assertSame(AlertLogStatus::Failed, $log->status);
        $this->assertNotNull($log->failure_message);
    }

    public function test_slack_delivery_posts_batched_payload_through_the_engine(): void
    {
        Http::fake();

        $service = $this->private__service();
        $alert = $this->private__alertWithProvider(
            $this->private__provider(SlackNotifierService::type(), ['webhook_url' => 'https://hooks.slack.test/delivery']),
        );
        $log = $this->private__log($alert, $service);

        $this->private__engine()->dispatch($alert, [$this->private__event($service)], $log);

        Http::assertSent(function ($request): bool {
            if ($request->url() !== 'https://hooks.slack.test/delivery') {
                return false;
            }

            $blocks = \json_encode($request['blocks']);

            return \is_string($blocks)
                && \is_string($request['text'])
                && str_contains($blocks, 'Failure count in window')
                && str_contains($blocks, 'svc-delivery');
        });

        $this->assertSame(AlertLogStatus::Sent, $log->refresh()->status);
    }

    public function test_slack_delivery_skips_provider_without_webhook(): void
    {
        Http::fake();

        $service = $this->private__service();
        $alert = $this->private__alertWithProvider(
            $this->private__provider(SlackNotifierService::type(), ['webhook_url' => '']),
        );
        $log = $this->private__log($alert, $service);

        $this->private__engine()->dispatch($alert, [$this->private__event($service)], $log);

        Http::assertNothingSent();
        $this->assertSame(AlertLogStatus::Sent, $log->refresh()->status);
    }

    public function test_smtp_delivery_failure_marks_alert_log_failed(): void
    {
        Mail::shouldReceive('to')
            ->once()
            ->andThrow(new \RuntimeException('SMTP connection refused'));

        $service = $this->private__service();
        $alert = $this->private__alertWithProvider(
            $this->private__provider(EmailNotifierService::type(), ['to' => ['ops@example.com']]),
        );
        $log = $this->private__log($alert, $service);

        $this->private__engine()->dispatch($alert, [$this->private__event($service)], $log);

        $log->refresh();
        $this->assertSame(AlertLogStatus::Failed, $log->status);
        $this->assertSame('SMTP connection refused', $log->failure_message);
    }

    public function test_smtp_delivery_sends_batched_alert_through_the_engine(): void
    {
        Mail::fake();

        $service = $this->private__service();
        $alert = $this->private__alertWithProvider(
            $this->private__provider(EmailNotifierService::type(), ['to' => ['ops@example.com']]),
        );
        $log = $this->private__log($alert, $service);

        $this->private__engine()->dispatch($alert, [$this->private__event($service)], $log);

        Mail::assertSent(AlertBatchedMail::class, function (AlertBatchedMail $mail): bool {
            return $mail->hasTo('ops@example.com')
                && $mail->notification['ruleLabel'] === 'Failure count in window'
                && $mail->notification['serviceName'] === 'svc-delivery'
                && $mail->notification['totalEventCount'] === 1;
        });

        $this->assertSame(AlertLogStatus::Sent, $log->refresh()->status);
    }

    public function test_smtp_delivery_skips_provider_without_recipients(): void
    {
        Mail::fake();

        $service = $this->private__service();
        $alert = $this->private__alertWithProvider(
            $this->private__provider(EmailNotifierService::type(), ['to' => []]),
        );
        $log = $this->private__log($alert, $service);

        $this->private__engine()->dispatch($alert, [$this->private__event($service)], $log);

        Mail::assertNothingSent();
        $this->assertSame(AlertLogStatus::Sent, $log->refresh()->status);
    }

    /**
     * Create an enabled alert attached to the given providers.
     */
    private function private__alertWithProvider(NotificationProvider ...$providers): Alert
    {
        $alert = Alert::create([
            'name' => 'delivery',
            'rule_type' => FailureCount::type(),
            'enabled' => true,
        ]);

        $alert->notificationProviders()->sync(\array_map(
            static fn (NotificationProvider $provider): int => $provider->id,
            $providers,
        ));
        $alert->load('notificationProviders');

        return $alert;
    }

    /**
     * Build the alert engine with the real rule registry.
     */
    private function private__engine(): AlertEngine
    {
        return new AlertEngine(
            new AlertBatchStore,
            $this->app->make(AlertRuleStrategyRegistry::class),
        );
    }

    /**
     * Build a single alert delivery event.
     *
     * @return array{service_id: int, job_uuid: string|null, triggered_at: string}
     */
    private function private__event(Service $service): array
    {
        return [
            'service_id' => $service->id,
            'job_uuid' => null,
            'triggered_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Create an alert log entry in the sent state.
     */
    private function private__log(Alert $alert, Service $service): AlertLog
    {
        return AlertLog::create([
            'alert_id' => $alert->id,
            'service_id' => $service->id,
            'status' => AlertLogStatus::Sent->value,
            'trigger_count' => 1,
            'sent_at' => now(),
        ]);
    }

    /**
     * Create a notification provider of the given type and config.
     *
     * @param NotificationProviderType $type The provider type.
     * @param array<string, mixed> $config The provider config.
     */
    private function private__provider(NotificationProviderType $type, array $config): NotificationProvider
    {
        return NotificationProvider::create([
            'name' => 'provider-' . $type->value,
            'type' => $type,
            'config' => $config,
        ]);
    }

    /**
     * Create a service for alert delivery.
     */
    private function private__service(): Service
    {
        return Service::create([
            'name' => 'svc-delivery',
            'base_url' => 'https://svc-delivery.test',
            'status' => 'online',
        ]);
    }
}
