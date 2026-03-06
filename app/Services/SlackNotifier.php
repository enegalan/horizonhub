<?php

namespace App\Services;

use App\Contracts\SlackAlertNotifier;
use App\Models\Alert;
use App\Models\Service;
use Illuminate\Support\Facades\Http;

class SlackNotifier implements SlackAlertNotifier {
    /**
     * Send an alert.
     *
     * @param Alert $alert
     * @param int $serviceId
     * @param int|null $jobId
     * @param array $config
     * @return void
     */
    public function send(Alert $alert, int $serviceId, ?int $jobId, array $config): void {
        $this->sendBatched($alert, [
            ['service_id' => $serviceId, 'job_id' => $jobId, 'triggered_at' => now()->toIso8601String()],
        ], $config);
    }

    /**
     * Send a batched alert.
     *
     * @param Alert $alert
     * @param array<int, array{service_id: int, job_id: int|null, triggered_at: string}> $events
     * @param array $config
     * @return void
     */
    public function sendBatched(Alert $alert, array $events, array $config): void {
        $webhookUrl = $config['webhook_url'] ?? '';
        if ($webhookUrl === '') {
            return;
        }

        $first = $events[0];
        $serviceId = (int) $first['service_id'];
        $service = Service::find($serviceId);
        $count = \count($events);

        $lines = [
            \sprintf('*[Horizon Hub]* Alert: %s | Service: %s', $alert->rule_type, $service ? $service->name : (string) $serviceId),
            "Events: $count",
        ];
        foreach (\array_slice($events, 0, 10) as $i => $event) {
            $lines[] = '• Job ID: ' . ($event['job_id'] ?? 'N/A') . ' at ' . ($event['triggered_at'] ?? '');
        }
        if ($count > 10) {
            $lines[] = "… and " . ($count - 10) . " more";
        }
        $text = \implode("\n", $lines);

        Http::post($webhookUrl, [
            'text' => $text,
        ]);
    }
}
