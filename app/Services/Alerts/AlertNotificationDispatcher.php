<?php

namespace App\Services\Alerts;

use App\Contracts\EmailAlertNotifier;
use App\Contracts\SlackAlertNotifier;
use App\Models\Alert;
use App\Models\AlertLog;
use App\Models\NotificationProvider;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class AlertNotificationDispatcher {

    /**
     * The email notifier.
     *
     * @var EmailAlertNotifier
     */
    private EmailAlertNotifier $emailNotifier;

    /**
     * The slack notifier.
     *
     * @var SlackAlertNotifier
     */
    private SlackAlertNotifier $slackNotifier;

    /**
     * Construct the dispatcher.
     *
     * @param EmailAlertNotifier $emailNotifier
     * @param SlackAlertNotifier $slackNotifier
     */
    public function __construct(
        EmailAlertNotifier $emailNotifier,
        SlackAlertNotifier $slackNotifier
    ) {
        $this->emailNotifier = $emailNotifier;
        $this->slackNotifier = $slackNotifier;
    }

    /**
     * Send alert notifications for the given log using notification providers.
     *
     * @param Alert $alert
     * @param array<int, array{service_id: int, job_uuid: string|null, triggered_at: string}> $events
     * @param AlertLog $log
     * @return void
     */
    public function dispatch(Alert $alert, array $events, AlertLog $log): void {
        $providers = $alert->notificationProviders;

        if (! ($providers instanceof Collection) || $providers->isEmpty()) {
            return;
        }

        /** @var NotificationProvider $provider */
        foreach ($providers as $provider) {
            try {
                if ($provider->type === NotificationProvider::TYPE_SLACK) {
                    $webhookUrl = $provider->getWebhookUrl();
                    if (! empty($webhookUrl)) {
                        $this->slackNotifier->sendBatched($alert, $events, ['webhook_url' => $webhookUrl]);
                    }
                }
                if ($provider->type === NotificationProvider::TYPE_EMAIL) {
                    $to = $provider->getToEmails();
                    if (! empty($to)) {
                        $this->emailNotifier->sendBatched($alert, $events, ['to' => $to]);
                    } else {
                        Log::warning('Horizon Hub: email provider has no recipients, skip', ['alert_id' => $alert->id, 'provider_id' => $provider->id]);
                    }
                }
            } catch (\Throwable $e) {
                Log::error('Horizon Hub alert notification failed', ['alert_id' => $alert->id, 'provider_id' => $provider->id, 'error' => $e->getMessage()]);
                $log->update(['status' => 'failed', 'failure_message' => $e->getMessage()]);
            }
        }
    }
}
