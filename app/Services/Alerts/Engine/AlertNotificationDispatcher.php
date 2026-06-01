<?php

namespace App\Services\Alerts\Engine;

use App\Models\Alert;
use App\Models\AlertLog;
use App\Models\NotificationProvider;
use App\Services\Notifiers\Contracts\AlertNotifier;
use App\Services\Notifiers\DiscordNotifierService;
use App\Services\Notifiers\EmailNotifierService;
use App\Services\Notifiers\SlackNotifierService;
use Illuminate\Support\Facades\Log;

class AlertNotificationDispatcher
{
    /**
     * The notifiers.
     *
     * @var array<class-string, AlertNotifier>
     */
    private array $notifiers;

    /**
     * @param DiscordNotifierService $discordNotifier The discord notifier.
     * @param EmailNotifierService $emailNotifier The email notifier.
     * @param SlackNotifierService $slackNotifier The slack notifier.
     */
    public function __construct(DiscordNotifierService $discordNotifier, EmailNotifierService $emailNotifier, SlackNotifierService $slackNotifier)
    {
        $this->notifiers = [
            DiscordNotifierService::class => $discordNotifier,
            EmailNotifierService::class => $emailNotifier,
            SlackNotifierService::class => $slackNotifier,
        ];
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

        if ($providers->isEmpty()) {
            return;
        }

        /** @var NotificationProvider $provider */
        foreach ($providers as $provider) {
            try {
                $notifierClass = $provider->notifierClass();

                if ($notifierClass === null) {
                    continue;
                }

                $config = $provider->deliverableConfig();

                if ($config === null) {
                    if (! $provider->usesWebhook()) {
                        Log::warning(config('app.name') . ': email provider has no recipients, skip', ['alert_id' => $alert->id, 'provider_id' => $provider->id]);
                    }

                    continue;
                }

                ($this->notifiers[$notifierClass] ?? app($notifierClass))->sendBatched($alert, $events, $config);
            } catch (\Throwable $e) {
                Log::error(config('app.name') . ': alert notification failed', ['alert_id' => $alert->id, 'provider_id' => $provider->id, 'error' => $e->getMessage()]);
                $log->update(['status' => 'failed', 'failure_message' => $e->getMessage()]);
            }
        }
    }
}
