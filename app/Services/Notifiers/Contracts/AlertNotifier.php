<?php

namespace App\Services\Notifiers\Contracts;

use App\Models\Alert;

interface AlertNotifier
{
    /**
     * Send an alert for a single event.
     */
    public function send(Alert $alert, int $serviceId, ?string $jobUuid, array $config): void;

    /**
     * Send a batched alert.
     *
     * @param array<int, array{service_id: int, job_uuid: string|null, triggered_at: string}> $events
     */
    public function sendBatched(Alert $alert, array $events, array $config): void;
}
