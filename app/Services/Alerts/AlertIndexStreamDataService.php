<?php

namespace App\Services\Alerts;

use App\Models\Alert;
use App\Models\Service;
use Illuminate\Support\Collection;

class AlertIndexStreamDataService
{
    /**
     * Load alerts for the index stream with list metadata.
     *
     * @return array{
     *     alerts: Collection<int, Alert>,
     *     alertStats: array{total: int, enabled: int, disabled: int},
     *     serviceLabelsByAlertId: array<int, list<string>>
     * }
     */
    public function buildStreamPayload(): array
    {
        $alerts = Alert::query()
            ->withCount('alertLogs')
            ->withMax('alertLogs', 'sent_at')
            ->orderByDesc('created_at')
            ->get();

        $alertStats = [
            'total' => $alerts->count(),
            'enabled' => $alerts->where('enabled', true)->count(),
            'disabled' => $alerts->where('enabled', false)->count(),
        ];

        return [
            'alerts' => $alerts,
            'alertStats' => $alertStats,
            'serviceLabelsByAlertId' => $this->private__buildServiceLabelsByAlertId($alerts),
        ];
    }

    /**
     * @param Collection<int, Alert> $alerts
     *
     * @return array<int, list<string>>
     */
    private function private__buildServiceLabelsByAlertId(Collection $alerts): array
    {
        $serviceNamesById = Service::query()->pluck('name', 'id')->all();
        $labelsByAlertId = [];

        foreach ($alerts as $alert) {
            $labels = [];

            foreach ($alert->service_ids as $serviceId) {
                $name = $serviceNamesById[$serviceId] ?? null;

                if ($name !== null) {
                    $labels[] = $name;
                }
            }

            $labelsByAlertId[$alert->id] = $labels;
        }

        return $labelsByAlertId;
    }
}
