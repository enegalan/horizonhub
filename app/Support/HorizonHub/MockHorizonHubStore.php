<?php

namespace App\Support\HorizonHub;

use App\Contracts\HorizonHubStore as HorizonHubStoreContract;
use App\Models\Alert;
use App\Models\AlertLog;
use App\Models\NotificationProvider;
use App\Models\Service;
use App\Models\ServiceHeader;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MockHorizonHubStore implements HorizonHubStoreContract
{
    /** @var Collection<int, AlertLog> */
    private Collection $alertLogs;

    /** @var Collection<int, Alert> */
    private Collection $alerts;

    /** @var Collection<int, NotificationProvider> */
    private Collection $providers;

    /** @var Collection<int, Service> */
    private Collection $services;

    public function __construct()
    {
        $catalog = config('demo.catalog', []);
        $this->providers = $this->private__hydrateProviders($catalog['notification_providers'] ?? []);
        $this->services = $this->private__hydrateServices($catalog['services'] ?? [], $catalog['service_headers'] ?? []);
        $this->alerts = $this->private__hydrateAlerts($catalog['alerts'] ?? []);
        $this->alertLogs = $this->private__hydrateAlertLogs($catalog['alert_logs'] ?? []);
    }

    public function alertLogCountsByProviderType(): array
    {
        $counts = [];

        foreach ($this->providers as $provider) {
            $counts[$provider->type] = 0;
        }

        foreach ($this->alertLogs as $log) {
            $alert = $this->findAlert((int) $log->alert_id);

            if ($alert === null) {
                continue;
            }

            foreach ($alert->notificationProviders as $provider) {
                $counts[$provider->type] = ($counts[$provider->type] ?? 0) + 1;
            }
        }

        return $counts;
    }

    public function alertLogsSinceForChart(int $alertId, Carbon $since): Collection
    {
        return $this->alertLogs
            ->filter(static function (AlertLog $log) use ($alertId, $since): bool {
                return (int) $log->alert_id === $alertId
                    && $log->sent_at->gte($since);
            })
            ->values();
    }

    public function alertLogTotalCount(): int
    {
        return $this->alertLogs->count();
    }

    public function alertsIndexStreamData(): array
    {
        $alerts = $this->alerts->sortByDesc(static fn (Alert $alert) => $alert->getAttribute('created_at'))->values();
        $serviceNamesById = $this->pluckServiceNames();
        $labelsByAlertId = [];

        foreach ($alerts as $alert) {
            $labels = [];

            foreach ($alert->service_ids as $serviceId) {
                $name = $serviceNamesById[(int) $serviceId] ?? null;

                if ($name !== null && $name !== '') {
                    $labels[] = $name;
                }
            }

            $labelsByAlertId[(int) $alert->id] = $labels;
            $alert->setAttribute('alert_logs_count', $this->alertLogs->where('alert_id', $alert->id)->count());
            $maxSent = $this->alertLogs->where('alert_id', $alert->id)->max('sent_at');
            $alert->setAttribute('alert_logs_max_sent_at', $maxSent);
        }

        return [
            'alerts' => $alerts,
            'serviceNamesById' => $serviceNamesById,
            'labelsByAlertId' => $labelsByAlertId,
        ];
    }

    public function allServiceIds(): array
    {
        return $this->services->pluck('id')->map(static fn ($id): int => (int) $id)->all();
    }

    public function allServiceTags(): array
    {
        return $this->private__collectTags($this->services);
    }

    public function createAlert(array $attributes, array $providerIds): Alert
    {
        throw new \RuntimeException('Mock mode is read-only.');
    }

    public function createAlertLog(array $attributes): AlertLog
    {
        throw new \RuntimeException('Mock mode is read-only.');
    }

    public function createNotificationProvider(array $attributes): NotificationProvider
    {
        throw new \RuntimeException('Mock mode is read-only.');
    }

    public function createService(array $attributes, array $headers): Service
    {
        throw new \RuntimeException('Mock mode is read-only.');
    }

    public function deleteAlert(Alert $alert): void
    {
        throw new \RuntimeException('Mock mode is read-only.');
    }

    public function deleteNotificationProvider(NotificationProvider $provider): void
    {
        throw new \RuntimeException('Mock mode is read-only.');
    }

    public function deleteService(Service $service): void
    {
        throw new \RuntimeException('Mock mode is read-only.');
    }

    public function enabledAlertIds(): array
    {
        return $this->enabledAlerts()->pluck('id')->map(static fn ($id): int => (int) $id)->all();
    }

    public function enabledAlerts(): Collection
    {
        return $this->alerts->filter(static fn (Alert $alert): bool => $alert->enabled)->values();
    }

    public function enabledAlertsExist(): bool
    {
        return $this->alerts->contains(static fn (Alert $alert): bool => $alert->enabled);
    }

    public function enabledServices(): Collection
    {
        return $this->services->filter(static fn (Service $service): bool => $service->enabled)->values();
    }

    public function enabledServicesForStaleCheck(): Collection
    {
        return $this->enabledServices();
    }

    public function enabledServicesOrdered(): Collection
    {
        return $this->enabledServices()->sortBy('name')->values();
    }

    public function enabledServiceTags(): array
    {
        return $this->private__collectTags($this->enabledServices());
    }

    public function existingServiceIds(array $candidateIds): array
    {
        $existing = [];

        foreach ($candidateIds as $id) {
            if ($this->findService((int) $id) !== null) {
                $existing[] = (int) $id;
            }
        }

        \sort($existing);

        return $existing;
    }

    public function findAlert(int $id): ?Alert
    {
        return $this->alerts->get($id);
    }

    public function findAlertLog(int $id): ?AlertLog
    {
        return $this->alertLogs->get($id);
    }

    public function findAlertLogForAlert(int $alertId, int|string $logId): ?AlertLog
    {
        $log = $this->findAlertLog((int) $logId);

        if ($log === null || (int) $log->alert_id !== $alertId) {
            return null;
        }

        return $log;
    }

    public function findAlertLogOrFail(int|string $value): AlertLog
    {
        $log = $this->findAlertLog((int) $value);

        if ($log === null) {
            throw new NotFoundHttpException('Alert log not found.');
        }

        return $log;
    }

    public function findAlertOrFail(int|string $value): Alert
    {
        $alert = $this->findAlert((int) $value);

        if ($alert === null) {
            throw new NotFoundHttpException('Alert not found.');
        }

        return $alert;
    }

    public function findEnabledService(int $id): ?Service
    {
        $service = $this->findService($id);

        return $service !== null && $service->enabled ? $service : null;
    }

    public function findNotificationProvider(int $id): ?NotificationProvider
    {
        return $this->providers->get($id);
    }

    public function findNotificationProviderOrFail(int|string $value): NotificationProvider
    {
        $provider = $this->findNotificationProvider((int) $value);

        if ($provider === null) {
            throw new NotFoundHttpException('Notification provider not found.');
        }

        return $provider;
    }

    public function findService(int $id): ?Service
    {
        return $this->services->get($id);
    }

    public function findServiceOrFail(int|string $value): Service
    {
        $service = $this->findService((int) $value);

        if ($service === null) {
            throw new NotFoundHttpException('Service not found.');
        }

        return $service;
    }

    public function markServicesStaleOffline(): void {}

    public function matchingTagServiceIds(array $tags): array
    {
        $ids = [];

        foreach ($this->services as $service) {
            foreach ($tags as $tag) {
                if (\in_array($tag, $service->tags, true)) {
                    $ids[] = (int) $service->id;

                    break;
                }
            }
        }

        \sort($ids);

        return $ids;
    }

    public function paginateAlertLogsForAlert(Alert $alert, array $filters): LengthAwarePaginator
    {
        $logs = $this->alertLogs
            ->filter(static fn (AlertLog $log): bool => (int) $log->alert_id === (int) $alert->id)
            ->sortByDesc(static fn (AlertLog $log) => $log->sent_at->timestamp)
            ->values();

        if (! empty($filters['service_id'])) {
            $serviceId = (int) $filters['service_id'];
            $logs = $logs->filter(static fn (AlertLog $log): bool => (int) $log->service_id === $serviceId)->values();
        }

        if (! empty($filters['status'])) {
            $status = (string) $filters['status'];
            $logs = $logs->filter(static fn (AlertLog $log): bool => (string) $log->status === $status)->values();
        }

        $perPage = (int) max(1, $filters['per_page'] ?? config('horizonhub.jobs_per_page'));
        $page = (int) max(1, $filters['page'] ?? 1);
        $total = $logs->count();
        $items = $logs->slice(($page - 1) * $perPage, $perPage)->values();

        return new Paginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()],
        );
    }

    public function pluckServiceNames(): array
    {
        $names = [];

        foreach ($this->services as $service) {
            $names[(int) $service->id] = (string) $service->name;
        }

        return $names;
    }

    public function providersOrdered(): Collection
    {
        return $this->providers
            ->sortBy(static fn (NotificationProvider $provider): string => $provider->type . $provider->name)
            ->values();
    }

    public function recentAlertLogs(int $limit, ?array $serviceFilterIds = null): Collection
    {
        $logs = $this->alertLogs->sortByDesc(static fn (AlertLog $log) => $log->sent_at->timestamp)->values();

        if ($serviceFilterIds !== null && $serviceFilterIds !== []) {
            $ids = \array_map('intval', $serviceFilterIds);
            $logs = $logs->filter(static fn (AlertLog $log): bool => \in_array((int) $log->service_id, $ids, true))->values();
        }

        return $logs->take($limit)->values();
    }

    public function resolveEnabledServiceIds(array $serviceIds): array
    {
        if ($serviceIds === []) {
            return [];
        }

        $ids = [];

        foreach ($serviceIds as $serviceId) {
            $service = $this->findEnabledService((int) $serviceId);

            if ($service !== null) {
                $ids[] = (int) $service->id;
            }
        }

        \sort($ids);

        return $ids;
    }

    public function servicesByIds(array $ids): Collection
    {
        $ids = \array_map('intval', $ids);

        return $this->services
            ->filter(static fn (Service $service): bool => \in_array((int) $service->id, $ids, true))
            ->sortBy('name')
            ->values();
    }

    public function servicesForAlertForm(array $selectedIds): Collection
    {
        $selectedIds = \array_map('intval', $selectedIds);

        return $this->services
            ->filter(static function (Service $service) use ($selectedIds): bool {
                if ($service->enabled) {
                    return true;
                }

                return \in_array((int) $service->id, $selectedIds, true);
            })
            ->sortBy('name')
            ->values();
    }

    public function servicesOrdered(?array $idsFilter = null): Collection
    {
        $services = $this->services->sortBy('name')->values();

        if ($idsFilter === null || $idsFilter === []) {
            return $services;
        }

        $ids = \array_map('intval', $idsFilter);

        return $services->filter(static fn (Service $service): bool => \in_array((int) $service->id, $ids, true))->values();
    }

    public function toggleAlertEnabled(Alert $alert): void
    {
        throw new \RuntimeException('Mock mode is read-only.');
    }

    public function toggleServiceEnabled(Service $service): void
    {
        throw new \RuntimeException('Mock mode is read-only.');
    }

    public function updateAlert(Alert $alert, array $attributes, array $providerIds): void
    {
        throw new \RuntimeException('Mock mode is read-only.');
    }

    public function updateAlertLog(AlertLog $log, array $attributes): void
    {
        throw new \RuntimeException('Mock mode is read-only.');
    }

    public function updateNotificationProvider(NotificationProvider $provider, array $attributes): void
    {
        throw new \RuntimeException('Mock mode is read-only.');
    }

    public function updateService(Service $service, array $attributes, array $headers): void
    {
        throw new \RuntimeException('Mock mode is read-only.');
    }

    public function updateServiceConnectionState(Service $service, string $status, ?Carbon $lastSeenAt = null): void
    {
        throw new \RuntimeException('Mock mode is read-only.');
    }

    /**
     * @param Collection<int, Service> $services
     *
     * @return list<string>
     */
    private function private__collectTags(Collection $services): array
    {
        return $services
            ->pluck('tags')
            ->flatten()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return Collection<int, AlertLog>
     */
    private function private__hydrateAlertLogs(array $rows): Collection
    {
        $logs = [];

        foreach ($rows as $row) {
            $log = new AlertLog($row);
            $log->id = (int) $row['id'];
            $log->exists = true;
            $service = $this->services->get((int) $row['service_id']);
            $alert = $this->alerts->get((int) $row['alert_id']);

            if ($service !== null) {
                $log->setRelation('service', $service);
            }

            if ($alert !== null) {
                $log->setRelation('alert', $alert);
            }

            $logs[(int) $row['id']] = $log;
        }

        return new Collection($logs);
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return Collection<int, Alert>
     */
    private function private__hydrateAlerts(array $rows): Collection
    {
        $alerts = [];

        foreach ($rows as $row) {
            $providerIds = $row['provider_ids'] ?? [];
            unset($row['provider_ids']);
            $alert = new Alert($row);
            $alert->id = (int) $row['id'];
            $alert->exists = true;
            $linked = [];

            foreach ($providerIds as $providerId) {
                $provider = $this->providers->get((int) $providerId);

                if ($provider !== null) {
                    $linked[] = $provider;
                }
            }

            $alert->setRelation('notificationProviders', new Collection($linked));
            $alerts[(int) $row['id']] = $alert;
        }

        return new Collection($alerts);
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return Collection<int, NotificationProvider>
     */
    private function private__hydrateProviders(array $rows): Collection
    {
        $providers = [];

        foreach ($rows as $row) {
            $provider = new NotificationProvider($row);
            $provider->id = (int) $row['id'];
            $provider->exists = true;
            $providers[(int) $row['id']] = $provider;
        }

        return new Collection($providers);
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return Collection<int, Service>
     */
    private function private__hydrateServices(array $rows, array $headerRows): Collection
    {
        $headersByService = [];

        foreach ($headerRows as $row) {
            $serviceId = (int) $row['service_id'];
            $headersByService[$serviceId] ??= [];
            $header = new ServiceHeader([
                'name' => $row['name'],
                'value' => $row['value'] ?? null,
            ]);
            $header->service_id = $serviceId;
            $headersByService[$serviceId][] = $header;
        }

        $services = [];

        foreach ($rows as $row) {
            $service = new Service($row);
            $service->id = (int) $row['id'];
            $service->exists = true;
            $service->setRelation(
                'headers',
                new Collection($headersByService[(int) $row['id']] ?? []),
            );
            $services[(int) $row['id']] = $service;
        }

        return new Collection($services);
    }
}
