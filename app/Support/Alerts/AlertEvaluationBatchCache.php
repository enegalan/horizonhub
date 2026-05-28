<?php

namespace App\Support\Alerts;

use Illuminate\Support\Facades\Cache;

final class AlertEvaluationBatchCache
{
    /**
     * The cache prefix for alert evaluation batches.
     *
     * @var string
     */
    private const CACHE_PREFIX = 'horizonhub.alert_evaluation_batches';

    /**
     * The cache TTL in seconds.
     *
     * @var int
     */
    private const TTL_SECONDS = 1800;

    /**
     * The cache namespace.
     */
    private readonly string $namespace;

    /**
     * The constructor.
     *
     * @param string $evaluationId The evaluation ID.
     */
    public function __construct(string $evaluationId)
    {
        $this->namespace = self::CACHE_PREFIX . '.' . $evaluationId;
    }

    /**
     * Forget the batch errors.
     */
    public function forgetBatchErrors(): void
    {
        Cache::forget($this->private__key('error_message'));
        Cache::forget($this->private__key('first_error_message'));
    }

    /**
     * Get the delivered count.
     */
    public function getDeliveredCount(): int
    {
        return (int) (Cache::get($this->private__key('delivered_count')) ?? 0);
    }

    /**
     * Get the error count.
     */
    public function getErrorCount(): int
    {
        return (int) (Cache::get($this->private__key('error_count')) ?? 0);
    }

    /**
     * Get the error message.
     */
    public function getErrorMessage(): ?string
    {
        $message = Cache::get($this->private__key('error_message'));

        return \is_string($message) ? $message : null;
    }

    /**
     * Get the evaluated count.
     */
    public function getEvaluatedCount(): int
    {
        return (int) (Cache::get($this->private__key('evaluated_count')) ?? 0);
    }

    /**
     * Get the first error message.
     */
    public function getFirstErrorMessage(): ?string
    {
        $message = Cache::get($this->private__key('first_error_message'));

        return \is_string($message) ? $message : null;
    }

    /**
     * Get the status.
     */
    public function getStatus(): string
    {
        return (string) (Cache::get($this->private__key('status')) ?? 'running');
    }

    /**
     * Get the total alerts.
     */
    public function getTotalAlerts(): int
    {
        return (int) (Cache::get($this->private__key('total_alerts')) ?? 0);
    }

    /**
     * Get the triggered count.
     */
    public function getTriggeredCount(): int
    {
        return (int) (Cache::get($this->private__key('triggered_count')) ?? 0);
    }

    /**
     * Initialize the counters.
     */
    public function initializeCounters(): void
    {
        $this->private__put('delivered_count', 0);
        $this->private__put('error_count', 0);
        $this->private__put('evaluated_count', 0);
        $this->private__put('triggered_count', 0);
    }

    /**
     * Mark the batch as failed.
     *
     * @param string $message The error message.
     */
    public function markBatchFailed(string $message): void
    {
        $this->private__put('error_message', $message);
        $this->private__put('status', 'failed');
    }

    /**
     * Mark the batch as completed.
     */
    public function markCompleted(): void
    {
        $this->private__put('status', 'completed');
    }

    /**
     * Put the status.
     *
     * @param string $status The status.
     */
    public function putStatus(string $status): void
    {
        $this->private__put('status', $status);
    }

    /**
     * Put the total alerts.
     *
     * @param int $total The total alerts.
     */
    public function putTotalAlerts(int $total): void
    {
        $this->private__put('total_alerts', $total);
    }

    /**
     * Record the evaluation error.
     *
     * @param string $errorMessage The error message.
     */
    public function recordEvaluationError(string $errorMessage): void
    {
        Cache::increment($this->private__key('evaluated_count'), 1);
        Cache::increment($this->private__key('error_count'), 1);
        $this->private__putFirstErrorMessageIfAbsent($errorMessage);
    }

    /**
     * Record the evaluation result.
     *
     * @param array<string, mixed> $result
     */
    public function recordEvaluationResult(array $result): void
    {
        Cache::increment($this->private__key('evaluated_count'), 1);

        if (! empty($result['triggered'])) {
            Cache::increment($this->private__key('triggered_count'), 1);
        }

        if (! empty($result['delivered'])) {
            Cache::increment($this->private__key('delivered_count'), 1);
        }

        if (! empty($result['error_message'])) {
            Cache::increment($this->private__key('error_count'), 1);
            $this->private__putFirstErrorMessageIfAbsent((string) $result['error_message']);
        }
    }

    /**
     * Get the cache key.
     *
     * @param string $suffix The suffix.
     */
    private function private__key(string $suffix): string
    {
        return "{$this->namespace}.{$suffix}";
    }

    /**
     * Put the value.
     *
     * @param string $suffix The suffix.
     * @param mixed $value The value.
     */
    private function private__put(string $suffix, mixed $value): void
    {
        Cache::put($this->private__key($suffix), $value, self::TTL_SECONDS);
    }

    /**
     * Put the first error message if absent.
     *
     * @param string $message The error message.
     */
    private function private__putFirstErrorMessageIfAbsent(string $message): void
    {
        $key = $this->private__key('first_error_message');

        if (Cache::get($key) !== null) {
            return;
        }

        $this->private__put('first_error_message', $message);
    }
}
