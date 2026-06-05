<?php

namespace App\Contracts;

use App\Models\Alert;
use App\Models\AlertLog;
use App\Models\NotificationProvider;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface HorizonHubStore
{
    /**
     * Get the number of alert logs by provider type.
     *
     * @return array<string, int>
     */
    public function alertLogCountsByProviderType(): array;

    /**
     * Get the alert logs since the given date for the given alert.
     *
     * @return Collection<int, AlertLog>
     */
    public function alertLogsSinceForChart(int $alertId, Carbon $since): Collection;

    /**
     * Get the total number of alert logs.
     */
    public function alertLogTotalCount(): int;

    /**
     * Get the data for the alerts index stream.
     *
     * @return array{
     *     alerts: Collection<int, Alert>,
     *     serviceNamesById: array<int, string>,
     *     labelsByAlertId: array<int, list<string>>
     * }
     */
    public function alertsIndexStreamData(): array;

    /**
     * Get all service IDs.
     *
     * @return list<int>
     */
    public function allServiceIds(): array;

    /**
     * Get all service tags.
     *
     * @return list<string>
     */
    public function allServiceTags(): array;

    /**
     * Create a new alert.
     *
     * @param array<string, mixed> $attributes
     * @param list<int> $providerIds
     */
    public function createAlert(array $attributes, array $providerIds): Alert;

    /**
     * Create a new alert log.
     *
     * @param array<string, mixed> $attributes
     */
    public function createAlertLog(array $attributes): AlertLog;

    /**
     * Create a new notification provider.
     *
     * @param array<string, mixed> $attributes
     */
    public function createNotificationProvider(array $attributes): NotificationProvider;

    /**
     * Create a new service.
     *
     * @param array<string, mixed> $attributes
     * @param list<string, string> $headers
     */
    public function createService(array $attributes, array $headers): Service;

    /**
     * Delete an alert.
     */
    public function deleteAlert(Alert $alert): void;

    /**
     * Delete a notification provider.
     */
    public function deleteNotificationProvider(NotificationProvider $provider): void;

    /**
     * Delete a service.
     */
    public function deleteService(Service $service): void;

    /**
     * Get the IDs of all enabled alerts.
     *
     * @return list<int>
     */
    public function enabledAlertIds(): array;

    /**
     * Get all enabled alerts.
     *
     * @return Collection<int, Alert>
     */
    public function enabledAlerts(): Collection;

    /**
     * Check if any enabled alerts exist.
     */
    public function enabledAlertsExist(): bool;

    /**
     * Get all enabled services.
     *
     * @return Collection<int, Service>
     */
    public function enabledServices(): Collection;

    /**
     * Get all enabled services for stale check.
     *
     * @return Collection<int, Service>
     */
    public function enabledServicesForStaleCheck(): Collection;

    /**
     * Get all enabled services ordered by name.
     *
     * @return Collection<int, Service>
     */
    public function enabledServicesOrdered(): Collection;

    /**
     * Get all enabled service tags.
     *
     * @return list<string>
     */
    public function enabledServiceTags(): array;

    /**
     * Get the IDs of all existing services.
     *
     * @param list<int|string> $candidateIds
     *
     * @return list<int>
     */
    public function existingServiceIds(array $candidateIds): array;

    /**
     * Find an alert by ID.
     */
    public function findAlert(int $id): ?Alert;

    /**
     * Find an alert log by ID.
     */
    public function findAlertLog(int $id): ?AlertLog;

    /**
     * Find an alert log for an alert by ID.
     */
    public function findAlertLogForAlert(int $alertId, int|string $logId): ?AlertLog;

    /**
     * Find an alert log by ID or fail.
     */
    public function findAlertLogOrFail(int|string $value): AlertLog;

    /**
     * Find an alert by ID or fail.
     */
    public function findAlertOrFail(int|string $value): Alert;

    /**
     * Find an enabled service by ID.
     */
    public function findEnabledService(int $id): ?Service;

    /**
     * Find a notification provider by ID.
     */
    public function findNotificationProvider(int $id): ?NotificationProvider;

    /**
     * Find a notification provider by ID or fail.
     */
    public function findNotificationProviderOrFail(int|string $value): NotificationProvider;

    /**
     * Find a service by ID.
     */
    public function findService(int $id): ?Service;

    /**
     * Find a service by ID or fail.
     */
    public function findServiceOrFail(int|string $value): Service;

    /**
     * Mark services as stale offline.
     */
    public function markServicesStaleOffline(int $standByMinutes, int $deadMinutes): void;

    /**
     * Get the service IDs matching the given tags.
     *
     * @param list<string> $tags
     *
     * @return list<int>
     */
    public function matchingTagServiceIds(array $tags): array;

    /**
     * Paginate the alert logs for the given alert.
     *
     * @param array{status?: string, service_id?: int|string, per_page?: int} $filters
     */
    public function paginateAlertLogsForAlert(Alert $alert, array $filters): LengthAwarePaginator;

    /**
     * Pluck the service names by ID.
     *
     * @return array<int, string>
     */
    public function pluckServiceNames(): array;

    /**
     * Get all notification providers ordered by type and name.
     *
     * @return Collection<int, NotificationProvider>
     */
    public function providersOrdered(): Collection;

    /**
     * Get the recent alert logs for the given service IDs.
     *
     * @param list<int>|null $serviceFilterIds
     *
     * @return Collection<int, AlertLog>
     */
    public function recentAlertLogs(int $limit, ?array $serviceFilterIds = null): Collection;

    /**
     * Resolve the enabled service IDs from the given service IDs.
     *
     * @param list<int> $serviceIds
     *
     * @return list<int>
     */
    public function resolveEnabledServiceIds(array $serviceIds): array;

    /**
     * Get the services by IDs.
     *
     * @param list<int> $ids
     *
     * @return Collection<int, Service>
     */
    public function servicesByIds(array $ids): Collection;

    /**
     * Get the services for the alert form.
     *
     * @param list<int> $selectedIds
     *
     * @return Collection<int, Service>
     */
    public function servicesForAlertForm(array $selectedIds): Collection;

    /**
     * Get the services ordered by name.
     *
     * @param list<int>|null $idsFilter
     *
     * @return Collection<int, Service>
     */
    public function servicesOrdered(?array $idsFilter = null): Collection;

    /**
     * Toggle the enabled state of an alert.
     */
    public function toggleAlertEnabled(Alert $alert): void;

    /**
     * Toggle the enabled state of a service.
     */
    public function toggleServiceEnabled(Service $service): void;

    /**
     * Update an existing alert.
     *
     * @param array<string, mixed> $attributes
     * @param list<int> $providerIds
     */
    public function updateAlert(Alert $alert, array $attributes, array $providerIds): void;

    /**
     * Update an existing alert log.
     *
     * @param array<string, mixed> $attributes
     */
    public function updateAlertLog(AlertLog $log, array $attributes): void;

    /**
     * Update an existing notification provider.
     *
     * @param array<string, mixed> $attributes
     */
    public function updateNotificationProvider(NotificationProvider $provider, array $attributes): void;

    /**
     * Update an existing service.
     *
     * @param array<string, mixed> $attributes
     * @param list<string, string> $headers
     */
    public function updateService(Service $service, array $attributes, array $headers): void;

    /**
     * Update the connection state of a service.
     */
    public function updateServiceConnectionState(Service $service, string $status, ?Carbon $lastSeenAt = null): void;
}
