<?php

namespace App\Services\Alerts;

use App\Models\Alert;
use App\Support\ConfigHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class AlertBatchStore
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
     * Get the pending events for the given alert.
     *
     * @return array<int, array{service_id: int, job_uuid: string|null, triggered_at: string}>
     */
    public function getPending(Alert $alert): array
    {
        $key = self::PENDING_CACHE_PREFIX.$alert->id;
        $raw = Cache::get($key);
        if (! \is_array($raw)) {
            return [];
        }

        return $raw;
    }

    /**
     * Set the pending events for the given alert.
     *
     * @param  array<int, array{service_id: int, job_uuid: string|null, triggered_at: string}>  $pending
     */
    public function setPending(Alert $alert, array $pending): void
    {
        $key = self::PENDING_CACHE_PREFIX.$alert->id;
        if (empty($pending)) {
            Cache::forget($key);

            return;
        }
        Cache::put($key, $pending, \now()->addMinutes(ConfigHelper::get('horizonhub.alerts.pending_ttl_minutes')));
    }

    /**
     * Clear the pending events for the given alert.
     */
    public function clearPending(Alert $alert): void
    {
        $this->setPending($alert, []);
    }

    /**
     * Get the last sent at time for the given alert.
     */
    public function getLastSentAt(Alert $alert): ?Carbon
    {
        $key = self::SENT_AT_CACHE_PREFIX.$alert->id;
        $value = Cache::get($key);
        if ($value === null) {
            return null;
        }

        return $value instanceof Carbon ? $value : Carbon::parse($value);
    }

    /**
     * Set the last sent at time for the given alert.
     */
    public function setLastSentAt(Alert $alert, ?Carbon $time = null): void
    {
        $expiresAt = \now()->addMinutes(ConfigHelper::get('horizonhub.alerts.pending_ttl_minutes'));
        $value = $time ?: \now();
        Cache::put(self::SENT_AT_CACHE_PREFIX.$alert->id, $value, $expiresAt);
    }

    /**
     * Get the interval minutes for the given alert.
     */
    public function getIntervalMinutes(Alert $alert): int
    {
        if ($alert->email_interval_minutes !== null) {
            return (int) $alert->email_interval_minutes;
        }

        return 0;
    }

    /**
     * Determine if the alert should send now based on interval and last sent time.
     */
    public function shouldSendNow(Alert $alert): bool
    {
        $intervalMinutes = $this->getIntervalMinutes($alert);
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
