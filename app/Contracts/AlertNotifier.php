<?php

namespace App\Contracts;

use App\Models\Alert;

/**
 * Contract for alert notifications.
 * Implementations are bound in the container for AlertEngine injection.
 */
interface AlertNotifier {

    /**
     * Send an alert for a single event.
     *
     * @param Alert $alert
     * @param int $serviceId
     * @param string|null $jobUuid
     * @param array $config
     * @return void
     */
    public function send(Alert $alert, int $serviceId, ?string $jobUuid, array $config): void;

    /**
     * Send a batched alert.
     *
     * @param Alert $alert
     * @param array<int, array{service_id: int, job_uuid: string|null, triggered_at: string}> $events
     * @param array $config
     * @return void
     */
    public function sendBatched(Alert $alert, array $events, array $config): void;
}
