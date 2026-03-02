<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\Service;
use Illuminate\Support\Facades\Http;

class SlackNotifier {
    public function send(Alert $alert, int $serviceId, ?int $jobId, array $config): void {
        $this->sendBatched($alert, array(
            array('service_id' => $serviceId, 'job_id' => $jobId, 'triggered_at' => now()->toIso8601String()),
        ), $config);
    }

    /**
     * @param array<int, array{service_id: int, job_id: int|null, triggered_at: string}> $events
     */
    public function sendBatched(Alert $alert, array $events, array $config): void {
        $webhookUrl = $config['webhook_url'] ?? '';
        if ($webhookUrl === '') {
            return;
        }

        $first = $events[0];
        $serviceId = (int) $first['service_id'];
        $service = Service::find($serviceId);
        $count = count($events);

        $lines = array(
            sprintf('*[Horizon Hub]* Alert: %s | Service: %s', $alert->rule_type, $service ? $service->name : (string) $serviceId),
            'Events: ' . $count,
        );
        foreach (array_slice($events, 0, 10) as $i => $event) {
            $lines[] = '• Job ID: ' . ($event['job_id'] ?? 'N/A') . ' at ' . ($event['triggered_at'] ?? '');
        }
        if ($count > 10) {
            $lines[] = '… and ' . ($count - 10) . ' more';
        }
        $text = implode("\n", $lines);

        Http::post($webhookUrl, [
            'text' => $text,
        ]);
    }
}
