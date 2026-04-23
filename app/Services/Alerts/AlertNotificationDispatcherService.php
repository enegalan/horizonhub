<?php

namespace App\Services\Alerts;

use App\Models\Alert;
use App\Models\AlertLog;
use App\Models\NotificationProvider;
use App\Services\Notifiers\EmailNotifier;
use App\Services\Notifiers\SlackNotifier;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class AlertNotificationDispatcherService
{
    /**
     * The email notifier.
     */
    private EmailNotifier $emailNotifier;

    /**
     * The slack notifier.
     */
    private SlackNotifier $slackNotifier;

    /**
     * Construct the dispatcher.
     */
    public function __construct(EmailNotifier $emailNotifier, SlackNotifier $slackNotifier)
    {
        $this->emailNotifier = $emailNotifier;
        $this->slackNotifier = $slackNotifier;
    }

    /**
     * Send alert notifications for the given log using notification providers.
     *
     * @param Alert $alert The alert.
     * @param array<int, array{service_id: int, job_uuid: string|null, triggered_at: string}> $events The events.
     * @param AlertLog $log The alert log.
     */
    public function dispatch(Alert $alert, array $events, AlertLog $log): void
    {
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
