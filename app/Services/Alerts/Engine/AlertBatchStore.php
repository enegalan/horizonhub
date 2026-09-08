<?php

namespace App\Services\Alerts\Engine;

use App\Models\Alert;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class AlertBatchStore
{
    /**
     * The cache prefix for alert evaluation batches.
     *
     * @var string
     */
    private const EVALUATION_CACHE_PREFIX = 'horizonhub.alert_evaluation_batches';

    /**
     * The evaluation cache TTL in seconds.
     *
     * @var int
     */
    private const EVALUATION_TTL_SECONDS = 1800;

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
     * Forget the batch errors.
     *
     * @param string $evaluationId The evaluation ID.
     */
    public function forgetBatchErrors(string $evaluationId): void
    {
        Cache::forget($this->private__evaluationKey($evaluationId, 'error_message'));
        Cache::forget($this->private__evaluationKey($evaluationId, 'first_error_message'));
    }

    /**
     * Get the delivered count.
     *
     * @param string $evaluationId The evaluation ID.
     */
    public function getDeliveredCount(string $evaluationId): int
    {
        return (int) (Cache::get($this->private__evaluationKey($evaluationId, 'delivered_count')) ?? 0);
    }

    /**
     * Get the error count.
     *
     * @param string $evaluationId The evaluation ID.
     */
    public function getErrorCount(string $evaluationId): int
    {
        return (int) (Cache::get($this->private__evaluationKey($evaluationId, 'error_count')) ?? 0);
    }

    /**
     * Get the error message.
     *
     * @param string $evaluationId The evaluation ID.
     */
    public function getErrorMessage(string $evaluationId): ?string
    {
        $message = Cache::get($this->private__evaluationKey($evaluationId, 'error_message'));

        return \is_string($message) ? $message : null;
    }

    /**
     * Get the evaluated count.
     *
     * @param string $evaluationId The evaluation ID.
     */
    public function getEvaluatedCount(string $evaluationId): int
    {
        return (int) (Cache::get($this->private__evaluationKey($evaluationId, 'evaluated_count')) ?? 0);
    }

    /**
     * Get the first error message.
     *
     * @param string $evaluationId The evaluation ID.
     */
    public function getFirstErrorMessage(string $evaluationId): ?string
    {
        $message = Cache::get($this->private__evaluationKey($evaluationId, 'first_error_message'));

        return \is_string($message) ? $message : null;
    }

    /**
     * Get the last sent at time for the given alert.
     *
     * @param Alert $alert The alert.
     */
    public function getLastSentAt(Alert $alert): ?Carbon
    {
        $value = Cache::get(self::SENT_AT_CACHE_PREFIX . $alert->id);

        if (empty($value)) {
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
     * Get the status.
     *
     * @param string $evaluationId The evaluation ID.
     */
    public function getStatus(string $evaluationId): string
    {
        return (string) (Cache::get($this->private__evaluationKey($evaluationId, 'status')) ?? 'running');
    }

    /**
     * Get the total alerts.
     *
     * @param string $evaluationId The evaluation ID.
     */
    public function getTotalAlerts(string $evaluationId): int
    {
        return (int) (Cache::get($this->private__evaluationKey($evaluationId, 'total_alerts')) ?? 0);
    }

    /**
     * Get the triggered count.
     *
     * @param string $evaluationId The evaluation ID.
     */
    public function getTriggeredCount(string $evaluationId): int
    {
        return (int) (Cache::get($this->private__evaluationKey($evaluationId, 'triggered_count')) ?? 0);
    }

    /**
     * Initialize the counters.
     *
     * @param string $evaluationId The evaluation ID.
     */
    public function initializeCounters(string $evaluationId): void
    {
        $this->private__putEvaluation($evaluationId, 'delivered_count', 0);
        $this->private__putEvaluation($evaluationId, 'error_count', 0);
        $this->private__putEvaluation($evaluationId, 'evaluated_count', 0);
        $this->private__putEvaluation($evaluationId, 'triggered_count', 0);
    }

    /**
     * Mark the batch as failed.
     *
     * @param string $evaluationId The evaluation ID.
     * @param string $message The error message.
     */
    public function markBatchFailed(string $evaluationId, string $message): void
    {
        $this->private__putEvaluation($evaluationId, 'error_message', $message);
        $this->private__putEvaluation($evaluationId, 'status', 'failed');
    }

    /**
     * Mark the batch as completed.
     *
     * @param string $evaluationId The evaluation ID.
     */
    public function markCompleted(string $evaluationId): void
    {
        $this->private__putEvaluation($evaluationId, 'status', 'completed');
    }

    /**
     * Put the status.
     *
     * @param string $evaluationId The evaluation ID.
     * @param string $status The status.
     */
    public function putStatus(string $evaluationId, string $status): void
    {
        $this->private__putEvaluation($evaluationId, 'status', $status);
    }

    /**
     * Put the total alerts.
     *
     * @param string $evaluationId The evaluation ID.
     * @param int $total The total alerts.
     */
    public function putTotalAlerts(string $evaluationId, int $total): void
    {
        $this->private__putEvaluation($evaluationId, 'total_alerts', $total);
    }

    /**
     * Record the evaluation error.
     *
     * @param string $evaluationId The evaluation ID.
     * @param string $errorMessage The error message.
     */
    public function recordEvaluationError(string $evaluationId, string $errorMessage): void
    {
        Cache::increment($this->private__evaluationKey($evaluationId, 'evaluated_count'), 1);
        Cache::increment($this->private__evaluationKey($evaluationId, 'error_count'), 1);
        Cache::add($this->private__evaluationKey($evaluationId, 'first_error_message'), $errorMessage, self::EVALUATION_TTL_SECONDS);
    }

    /**
     * Record the evaluation result.
     *
     * @param string $evaluationId The evaluation ID.
     * @param array<string, mixed> $result
     */
    public function recordEvaluationResult(string $evaluationId, array $result): void
    {
        Cache::increment($this->private__evaluationKey($evaluationId, 'evaluated_count'), 1);

        if (! empty($result['triggered'])) {
            Cache::increment($this->private__evaluationKey($evaluationId, 'triggered_count'), 1);
        }

        if (! empty($result['delivered'])) {
            Cache::increment($this->private__evaluationKey($evaluationId, 'delivered_count'), 1);
        }

        if (! empty($result['error_message'])) {
            Cache::increment($this->private__evaluationKey($evaluationId, 'error_count'), 1);
            Cache::add($this->private__evaluationKey($evaluationId, 'first_error_message'), $result['error_message'], self::EVALUATION_TTL_SECONDS);
        }
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
        $intervalMinutes = ! empty($alert->email_interval_minutes) ? (int) $alert->email_interval_minutes : 0;
        $lastSentAt = $this->getLastSentAt($alert);

        if ($intervalMinutes === 0 || empty($lastSentAt)) {
            return true;
        }

        return \now()->gte($lastSentAt->copy()->addMinutes($intervalMinutes));
    }

    /**
     * Get the evaluation cache key.
     *
     * @param string $evaluationId The evaluation ID.
     * @param string $suffix The suffix.
     */
    private function private__evaluationKey(string $evaluationId, string $suffix): string
    {
        return self::EVALUATION_CACHE_PREFIX . '.' . $evaluationId . '.' . $suffix;
    }

    /**
     * Put the evaluation value.
     *
     * @param string $evaluationId The evaluation ID.
     * @param string $suffix The suffix.
     * @param mixed $value The value.
     */
    private function private__putEvaluation(string $evaluationId, string $suffix, mixed $value): void
    {
        Cache::put($this->private__evaluationKey($evaluationId, $suffix), $value, self::EVALUATION_TTL_SECONDS);
    }
}
