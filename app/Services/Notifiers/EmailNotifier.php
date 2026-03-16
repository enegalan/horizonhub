<?php

namespace App\Services\Notifiers;

use App\Contracts\EmailAlertNotifier;
use App\Mail\AlertBatchedMail;
use App\Models\Alert;
use App\Models\Service;
use App\Services\HorizonApiProxyService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailNotifier extends AbstractAlertNotifier implements EmailAlertNotifier {

    /**
     * Maximum length of exception text in the email body (keeps MIME encoding within memory limits).
     *
     * @var int
     */
    private const EMAIL_EXCEPTION_MAX_LENGTH = 500;

    /**
     * Maximum number of events to include in the email body (avoids memory exhaustion).
     *
     * @var int
     */
    private const MAX_EVENTS_IN_EMAIL = 20;

    /**
     * Construct the email notifier.
     *
     * @param HorizonApiProxyService $horizonApi
     */
    public function __construct(HorizonApiProxyService $horizonApi) {
        parent::__construct($horizonApi);
    }

    /**
     * Send a batched alert.
     *
     * @param Alert $alert
     * @param array<int, array{service_id: int, job_uuid: string|null, triggered_at: string}> $events
     * @param array $config
     * @return void
     */
    public function sendBatched(Alert $alert, array $events, array $config): void {
        $to = $config['to'] ?? [];
        $to = \is_array($to) ? \array_values(\array_filter(\array_map('trim', $to))) : [];
        if (empty($to)) {
            return;
        }

        $count = \count($events);
        $enrichedEvents = $this->enrichEvents($events, self::MAX_EVENTS_IN_EMAIL, self::EMAIL_EXCEPTION_MAX_LENGTH);

        $first = $events[0];
        $serviceId = (int) $first['service_id'];
        $service = Service::find($serviceId);

        $subject = '[Horizon Hub] Alert: ' . $alert->rule_type . ($service ? " - {$service->name}" : '');
        if ($count > 1) {
            $subject .= " ($count events)";
        }

        Log::info('Horizon Hub: sending alert email', ['alert_id' => $alert->id, 'to' => $to, 'event_count' => $count]);

        Mail::to($to)->send(new AlertBatchedMail($alert, $enrichedEvents, $service, $subject, $count));
    }
}
