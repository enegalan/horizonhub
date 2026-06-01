<?php

namespace App\Services\Alerts;

use App\Models\Alert;
use App\Models\AlertLog;
use App\Models\NotificationProvider;
use App\Models\Service;
use Illuminate\Support\Collection;

final class AlertDataService
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
    public function build(): array
    {
        $alerts = Alert::query()
            ->withCount('alertLogs')
            ->withMax('alertLogs', 'sent_at')
            ->orderByDesc('created_at')
            ->get();

        return [
            'alerts' => $alerts,
            'alertStats' => [
                'total' => $alerts->count(),
                'enabled' => $alerts->where('enabled')->count(),
                'disabled' => $alerts->where('enabled', false)->count(),
            ],
            'serviceLabelsByAlertId' => $this->private__buildServiceLabelsByAlertId($alerts),
        ];
    }

    /**
     * Count emitted alert deliveries grouped by notification provider type.
     */
    public function countsByProviderType(): array
    {
        $countsByProviderTypes = AlertLog::query()
            ->selectRaw('notification_providers.type, COUNT(*) as aggregate')
            ->join('alerts', 'alerts.id', '=', 'alert_logs.alert_id')
            ->join('alert_notification_provider', 'alert_notification_provider.alert_id', '=', 'alerts.id')
            ->join('notification_providers', 'notification_providers.id', '=', 'alert_notification_provider.notification_provider_id')
            ->groupBy('notification_providers.type')
            ->pluck('aggregate', 'notification_providers.type');

        $providerTypes = array_keys(NotificationProvider::getProviders());

        $counts = [
            'total' => AlertLog::query()->count(),
        ];

        foreach ($providerTypes as $providerType) {
            $counts[$providerType] = $countsByProviderTypes[$providerType] ?? 0;
        }

        return $counts;
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

                if (! empty($name)) {
                    $labels[] = $name;
                }
            }

            $labelsByAlertId[$alert->id] = $labels;
        }

        return $labelsByAlertId;
    }
}
