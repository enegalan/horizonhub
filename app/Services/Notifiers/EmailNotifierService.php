<?php

namespace App\Services\Notifiers;

use App\Mail\AlertBatchedMail;
use App\Models\Alert;
use App\Services\Horizon\HorizonClientService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailNotifierService extends AbstractAlertNotifier
{
    /**
     * The constructor.
     *
     * @param HorizonClientService $horizonApi The horizon API client.
     */
    public function __construct(HorizonClientService $horizonApi)
    {
        parent::__construct($horizonApi);
    }

    /**
     * Send a batched alert.
     *
     * @param Alert $alert The alert.
     * @param array<int, array{service_id: int, job_uuid: string|null, triggered_at: string}> $events The events.
     * @param array<string, mixed> $config The config.
     */
    public function sendBatched(Alert $alert, array $events, array $config): void
    {
        $to = \is_array($config['to'] ?? null)
            ? \array_values(\array_filter(\array_map('trim', $config['to'])))
            : [];

        if (empty($to)) {
            return;
        }

        $notification = $this->buildNotification($alert, $events);

        Log::info('Horizon Hub: sending alert email', [
            'alert_id' => $alert->id,
            'to' => $to,
            'event_count' => $notification['totalEventCount'],
        ]);

        Mail::to($to)->send(new AlertBatchedMail($alert, $notification));
    }
}
