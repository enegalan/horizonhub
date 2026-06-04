<?php

namespace App\Support\HorizonHub;

use App\Contracts\HorizonHubStore;
use App\Models\Alert;
use App\Models\AlertLog;
use App\Models\NotificationProvider;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class HorizonHubStore implements HorizonHubStore
{
    public function alertLogCountsByProviderType(): array
    {
        $rows = AlertLog::selectRaw('notification_providers.type, COUNT(*) as aggregate')
            ->join('alerts', 'alert_logs.alert_id', '=', 'alerts.id')
            ->join('alert_notification_provider', 'alerts.id', '=', 'alert_notification_provider.alert_id')
            ->join('notification_providers', 'alert_notification_provider.notification_provider_id', '=', 'notification_providers.id')
            ->groupBy('notification_providers.type')
            ->pluck('aggregate', 'type')
            ->all();

        $counts = [];

        foreach (\array_keys(NotificationProvider::getProviders()) as $providerType) {
            $counts[$providerType] = (int) ($rows[$providerType] ?? 0);
        }

        return $counts;
    }

    public function alertLogsSinceForChart(int $alertId, Carbon $since): Collection
    {
        return AlertLog::where('alert_id', $alertId)
            ->where('sent_at', '>=', $since)
            ->get(['sent_at', 'status']);
    }

    public function alertLogTotalCount(): int
    {
        return AlertLog::count();
    }

    public function alertsIndexStreamData(): array
    {
        $alerts = Alert::withCount('alertLogs')
            ->withMax('alertLogs', 'sent_at')
            ->orderByDesc('created_at')
            ->get();
        $serviceNamesById = $this->pluckServiceNames();
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

        return [
            'alerts' => $alerts,
            'serviceNamesById' => $serviceNamesById,
            'labelsByAlertId' => $labelsByAlertId,
        ];
    }

    public function allServiceIds(): array
    {
        return Service::pluck('id')->map(static fn ($id): int => (int) $id)->all();
    }

    public function allServiceTags(): array
    {
        return Service::get(['tags'])->pluck('tags')->flatten()->unique()->sort()->values()->all();
    }

    public function createAlert(array $attributes, array $providerIds): Alert
    {
        $alert = Alert::create($attributes);
        $alert->notificationProviders()->sync($providerIds);

        return $alert;
    }

    public function createAlertLog(array $attributes): AlertLog
    {
        return AlertLog::create($attributes);
    }

    public function createNotificationProvider(array $attributes): NotificationProvider
    {
        return NotificationProvider::create($attributes);
    }

    public function createService(array $attributes, array $headers): Service
    {
        $service = Service::create($attributes);
        $this->private__storeHeaders($service, $headers);

        return $service;
    }

    public function deleteAlert(Alert $alert): void
    {
        $alert->delete();
    }

    public function deleteNotificationProvider(NotificationProvider $provider): void
    {
        $provider->delete();
    }

    public function deleteService(Service $service): void
    {
        $service->delete();
    }

    public function enabledAlertIds(): array
    {
        return Alert::enabled()->pluck('id')->all();
    }

    public function enabledAlerts(): Collection
    {
        return Alert::enabled()->get();
    }

    public function enabledAlertsExist(): bool
    {
        return Alert::enabled()->exists();
    }

    public function enabledServices(): Collection
    {
        return Service::enabled()->get();
    }

    public function enabledServicesForStaleCheck(): Collection
    {
        return Service::enabled()->get();
    }

    public function enabledServicesOrdered(): Collection
    {
        return Service::enabled()->orderBy('name')->get();
    }

    public function enabledServiceTags(): array
    {
        return Service::enabled()->get(['tags'])->pluck('tags')->flatten()->unique()->sort()->values()->all();
    }

    public function existingServiceIds(array $candidateIds): array
    {
        $existing = Service::whereIn('id', $candidateIds)->pluck('id')->all();
        \sort($existing);

        return $existing;
    }

    public function findAlert(int $id): ?Alert
    {
        return Alert::find($id);
    }

    public function findAlertLog(int $id): ?AlertLog
    {
        return AlertLog::find($id);
    }

    public function findAlertLogForAlert(int $alertId, int|string $logId): ?AlertLog
    {
        return AlertLog::with('service')
            ->where('alert_id', $alertId)
            ->find($logId);
    }

    public function findAlertLogOrFail(int|string $value): AlertLog
    {
        return AlertLog::findOrFail($value);
    }

    public function findAlertOrFail(int|string $value): Alert
    {
        return Alert::findOrFail($value);
    }

    public function findEnabledService(int $id): ?Service
    {
        return Service::enabled()->find($id);
    }

    public function findNotificationProvider(int $id): ?NotificationProvider
    {
        return NotificationProvider::find($id);
    }

    public function findNotificationProviderOrFail(int|string $value): NotificationProvider
    {
        return NotificationProvider::findOrFail($value);
    }

    public function findService(int $id): ?Service
    {
        return Service::find($id);
    }

    public function findServiceOrFail(int|string $value): Service
    {
        return Service::findOrFail($value);
    }

    public function markServicesStaleOffline(int $standByMinutes, int $deadMinutes): void
    {
        $standByThreshold = now()->subMinutes($standByMinutes);
        $deadThreshold = now()->subMinutes($deadMinutes);

        Service::enabled()
            ->where('status', 'online')
            ->where(function (Builder $query) use ($standByThreshold): void {
                $query->whereNull('last_seen_at')
                    ->orWhere('last_seen_at', '<', $standByThreshold);
            })
            ->update(['status' => 'stand_by']);

        Service::enabled()
            ->whereIn('status', ['online', 'stand_by'])
            ->where(function (Builder $query) use ($deadThreshold): void {
                $query->whereNull('last_seen_at')
                    ->orWhere('last_seen_at', '<', $deadThreshold);
            })
            ->update(['status' => 'offline']);
    }

    public function matchingTagServiceIds(array $tags): array
    {
        return Service::matchingTags($tags)->pluck('id')->all();
    }

    public function paginateAlertLogsForAlert(Alert $alert, array $filters): LengthAwarePaginator
    {
        $query = $alert->alertLogs()
            ->with('service')
            ->orderByDesc('sent_at');

        if (! empty($filters['service_id'])) {
            $query->where('service_id', (int) $filters['service_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', (string) $filters['status']);
        }

        $perPage = (int) max(1, $filters['per_page'] ?? config('horizonhub.jobs_per_page'));

        return $query->paginate($perPage)->withQueryString();
    }

    public function pluckServiceNames(): array
    {
        return Service::pluck('name', 'id')->all();
    }

    public function providersOrdered(): Collection
    {
        return NotificationProvider::orderBy('type')->orderBy('name')->get();
    }

    public function recentAlertLogs(int $limit, ?array $serviceFilterIds = null): Collection
    {
        $query = AlertLog::with(['alert', 'service'])->orderByDesc('sent_at');

        if ($serviceFilterIds !== null && $serviceFilterIds !== []) {
            $query->whereIn('service_id', $serviceFilterIds);
        }

        return $query->limit($limit)->get();
    }

    public function resolveEnabledServiceIds(array $serviceIds): array
    {
        if ($serviceIds === []) {
            return [];
        }

        $ids = Service::enabled()
            ->whereIn('id', $serviceIds)
            ->pluck('id')
            ->all();
        \sort($ids);

        return $ids;
    }

    public function servicesByIds(array $ids): Collection
    {
        return Service::whereIn('id', $ids)->orderBy('name')->get();
    }

    public function servicesForAlertForm(array $selectedIds): Collection
    {
        return Service::query()
            ->where(function (Builder $query) use ($selectedIds): void {
                $query->enabled();

                if ($selectedIds !== []) {
                    $query->orWhere(fn (Builder $inner) => $inner->disabled()->whereIn('id', $selectedIds));
                }
            })
            ->orderBy('name')
            ->get();
    }

    public function servicesOrdered(?array $idsFilter = null): Collection
    {
        $query = Service::orderBy('name');

        if ($idsFilter !== null && $idsFilter !== []) {
            $query->whereIn('id', $idsFilter);
        }

        return $query->get();
    }

    public function toggleAlertEnabled(Alert $alert): void
    {
        $alert->enabled = ! $alert->enabled;
        $alert->save();
    }

    public function toggleServiceEnabled(Service $service): void
    {
        $service->enabled = ! $service->enabled;
        $service->save();
    }

    public function updateAlert(Alert $alert, array $attributes, array $providerIds): void
    {
        $alert->update($attributes);
        $alert->notificationProviders()->sync($providerIds);
    }

    public function updateAlertLog(AlertLog $log, array $attributes): void
    {
        $log->update($attributes);
    }

    public function updateNotificationProvider(NotificationProvider $provider, array $attributes): void
    {
        $provider->update($attributes);
    }

    public function updateService(Service $service, array $attributes, array $headers): void
    {
        $service->update($attributes);
        $service->headers()->delete();
        $this->private__storeHeaders($service, $headers);
    }

    public function updateServiceConnectionState(Service $service, string $status, ?Carbon $lastSeenAt = null): void
    {
        $service->update([
            'status' => $status,
            'last_seen_at' => $lastSeenAt,
        ]);
    }

    private function private__storeHeaders(Service $service, array $headers): void
    {
        foreach ($headers as $header) {
            if (! \is_array($header)) {
                continue;
            }

            $name = \trim((string) ($header['name'] ?? ''));

            if (blank($name)) {
                continue;
            }

            $value = isset($header['value']) ? \trim((string) $header['value']) : '';

            $service->headers()->create([
                'name' => $name,
                'value' => blank($value) ? null : $value,
            ]);
        }
    }
}
