<?php

namespace App\Contracts;

use App\Models\Alert;

interface AlertNotifier {
    /**
     * Send an alert for a single event.
     *
     * @param Alert $alert
     * @param int $serviceId
     * @param int|null $jobId
     * @param array $config
     * @return void
     */
    public function send(Alert $alert, int $serviceId, ?int $jobId, array $config): void;

    /**
     * Send a batched alert.
     *
     * @param Alert $alert
     * @param array<int, array{service_id: int, job_id: int|null, triggered_at: string}> $events
     * @param array $config
     * @return void
     */
    public function sendBatched(Alert $alert, array $events, array $config): void;
}
