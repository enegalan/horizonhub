<?php

namespace App\Services\Alerts;

use App\Models\Alert;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class AlertBatchStoreService
{
    /**
     * The cache prefix for pending alerts.
     *
     * @var string
     */
    private const PENDING_CACHE_PREFIX = 'horizonhub_alert_pending_';

    /**
     * The cache prefix for sent alerts.
     *
     * @var string
     */
    private const SENT_AT_CACHE_PREFIX = 'horizonhub_alert_sent_at_';

    /**
     * Clear the pending events for the given alert.
     *
     * @param Alert $alert The alert.
     */
    public function clearPending(Alert $alert): void
    {
        $this->setPending($alert, []);
    }

    /**
     * Get the last sent at time for the given alert.
     *
     * @param Alert $alert The alert.
     */
    public function getLastSentAt(Alert $alert): ?Carbon
    {
        $value = Cache::get(self::SENT_AT_CACHE_PREFIX . $alert->id);

        if ($value === null) {
            return null;
        }

        return $value instanceof Carbon ? $value : Carbon::parse($value);
    }

    /**
     * Get the pending events for the given alert.
     *
     * @param Alert $alert The alert.
     *
     * @return array<int, array{service_id: int, job_uuid: string|null, triggered_at: string}>
     */
    public function getPending(Alert $alert): array
    {
        $key = self::PENDING_CACHE_PREFIX . $alert->id;
        $raw = Cache::get($key);

        if (! \is_array($raw)) {
            return [];
        }

        return $raw;
    }

    /**
     * Set the last sent at time for the given alert.
     *
     * @param Alert $alert The alert.
     * @param Carbon|null $time The time to set.
     */
    public function setLastSentAt(Alert $alert, ?Carbon $time = null): void
    {
        $expiresAt = \now()->addMinutes(config('horizonhub.alerts.pending_ttl_minutes'));
        Cache::put(self::SENT_AT_CACHE_PREFIX . $alert->id, $time ?: \now(), $expiresAt);
    }

    /**
     * Set the pending events for the given alert.
     *
     * @param Alert $alert The alert.
     * @param array<int, array{service_id: int, job_uuid: string|null, triggered_at: string}> $pending The pending events.
     */
    public function setPending(Alert $alert, array $pending): void
    {
        $key = self::PENDING_CACHE_PREFIX . $alert->id;

        if (empty($pending)) {
            Cache::forget($key);

            return;
        }
        Cache::put($key, $pending, \now()->addMinutes(config('horizonhub.alerts.pending_ttl_minutes')));
    }

    /**
     * Determine if the alert should send now based on interval and last sent time.
     *
     * @param Alert $alert The alert.
     */
    public function shouldSendNow(Alert $alert): bool
    {
        $intervalMinutes = $alert->email_interval_minutes !== null ? (int) $alert->email_interval_minutes : 0;
        $lastSentAt = $this->getLastSentAt($alert);

        if ($intervalMinutes === 0) {
            return true;
        }

        if ($lastSentAt === null) {
            return true;
        }

        return \now()->gte($lastSentAt->copy()->addMinutes($intervalMinutes));
    }
}
