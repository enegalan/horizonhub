<?php

namespace Tests\Unit;

use App\Models\Alert;
use App\Models\AlertLog;
use App\Models\NotificationProvider;
use App\Models\Service;
use App\Services\Alerts\AlertDataService;
use App\Services\Alerts\Rules\Strategies\FailureCount;
use App\Services\Notifiers\EmailNotifierService;
use App\Services\Notifiers\SlackNotifierService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlertLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_counts_emitted_alert_logs_by_provider_type(): void
    {
        $service = Service::create([
            'name' => 'stats-svc',
            'base_url' => 'https://stats.test',
            'status' => 'online',
        ]);

        $slackProvider = NotificationProvider::query()->create([
            'name' => 'slack-stats',
            'type' => SlackNotifierService::type(),
            'config' => ['webhook_url' => 'https://hooks.slack.test/services/T/B'],
        ]);
        $emailProvider = NotificationProvider::query()->create([
            'name' => 'email-stats',
            'type' => EmailNotifierService::type(),
            'config' => ['to' => ['ops@example.test']],
        ]);

        $slackAlert = Alert::create([
            'name' => 'slack-alert',
            'rule_type' => FailureCount::type(),
            'threshold' => ['count' => 1, 'minutes' => 5],
            'enabled' => true,
        ]);
        $slackAlert->notificationProviders()->sync([$slackProvider->id]);

        $dualAlert = Alert::create([
            'name' => 'dual-alert',
            'rule_type' => FailureCount::type(),
            'threshold' => ['count' => 1, 'minutes' => 5],
            'enabled' => true,
        ]);
        $dualAlert->notificationProviders()->sync([$slackProvider->id, $emailProvider->id]);

        $emailOnlyAlert = Alert::create([
            'name' => 'email-alert',
            'rule_type' => FailureCount::type(),
            'threshold' => ['count' => 1, 'minutes' => 5],
            'enabled' => true,
        ]);
        $emailOnlyAlert->notificationProviders()->sync([$emailProvider->id]);

        $this->private__createAlertLog($slackAlert, $service);
        $this->private__createAlertLog($dualAlert, $service);
        $this->private__createAlertLog($emailOnlyAlert, $service);

        $stats = $this->app->make(AlertDataService::class)->countsByProviderType();

        $this->assertSame(3, $stats['total']);
        $this->assertSame(2, $stats['slack']);
        $this->assertSame(2, $stats['email']);
        $this->assertSame(0, $stats['discord']);
    }

    private function private__createAlertLog(Alert $alert, Service $service): void
    {
        AlertLog::create([
            'alert_id' => $alert->id,
            'service_id' => $service->id,
            'trigger_count' => 1,
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }
}
