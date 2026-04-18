<?php

namespace App\Jobs;

use App\Models\Alert;
use App\Services\Alerts\AlertEngine;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class EvaluateAlertJob implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The cache TTL in seconds.
     *
     * @var int
     */
    private const CACHE_TTL_SECONDS = 1800;

    /**
     * The alert ID.
     */
    public int $alertId;

    /**
     * The evaluation ID.
     */
    public string $evaluationId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $alertId, string $evaluationId)
    {
        $this->alertId = $alertId;
        $this->evaluationId = $evaluationId;
    }

    /**
     * Execute the job.
     */
    public function handle(AlertEngine $engine): void
    {
        try {
            $alert = Alert::query()
                ->with('notificationProviders')
                ->find($this->alertId);

            if (! $alert) {
                Cache::put(
                    $this->private__cacheKeyForAlertResult(),
                    [
                        'alert_id' => $this->alertId,
                        'pending_flushed' => false,
                        'triggered' => false,
                        'triggered_service_id' => null,
                        'error_message' => 'Alert not found',
                        'delivered' => false,
                        'pending_flush_error_message' => null,
                    ],
                    self::CACHE_TTL_SECONDS
                );
                Cache::increment($this->private__cacheKeyForEvaluatedCount(), 1);
                Cache::increment($this->private__cacheKeyForErrorCount(), 1);

                return;
            }

            $result = $engine->evaluateAlert($alert);

            Cache::put(
                $this->private__cacheKeyForAlertResult(),
                $result,
                self::CACHE_TTL_SECONDS
            );

            Cache::increment($this->private__cacheKeyForEvaluatedCount(), 1);
            if (! empty($result['triggered'])) {
                Cache::increment($this->private__cacheKeyForTriggeredCount(), 1);
            }
            if (! empty($result['delivered'])) {
                Cache::increment($this->private__cacheKeyForDeliveredCount(), 1);
            }
            if (! empty($result['error_message'])) {
                Cache::increment($this->private__cacheKeyForErrorCount(), 1);
                $this->private__cacheFirstErrorMessage($result['error_message']);
            }
        } catch (\Throwable $e) {
            Log::error('Horizon Hub: alert evaluation job failed', [
                'alert_id' => $this->alertId,
                'evaluation_id' => $this->evaluationId,
                'error' => $e->getMessage(),
            ]);

            Cache::increment($this->private__cacheKeyForEvaluatedCount(), 1);
            Cache::increment($this->private__cacheKeyForErrorCount(), 1);
            $this->private__cacheFirstErrorMessage($e->getMessage());
            Cache::put(
                $this->private__cacheKeyForAlertResult(),
                [
                    'alert_id' => $this->alertId,
                    'pending_flushed' => false,
                    'triggered' => false,
                    'triggered_service_id' => null,
                    'error_message' => $e->getMessage(),
                    'delivered' => false,
                    'pending_flush_error_message' => null,
                ],
                self::CACHE_TTL_SECONDS
            );
        }
    }

    /**
     * Get the cache key namespace.
     */
    private function private__cacheKeyNamespace(): string
    {
        return "horizonhub.alert_evaluation_batches.$this->evaluationId";
    }

    /**
     * Get the cache key for the alert result.
     */
    private function private__cacheKeyForAlertResult(): string
    {
        return $this->private__cacheKeyNamespace().'.results.'.$this->alertId;
    }

    /**
     * Get the cache key for the evaluated count.
     */
    private function private__cacheKeyForEvaluatedCount(): string
    {
        return $this->private__cacheKeyNamespace().'.evaluated_count';
    }

    /**
     * Get the cache key for the triggered count.
     */
    private function private__cacheKeyForTriggeredCount(): string
    {
        return $this->private__cacheKeyNamespace().'.triggered_count';
    }

    /**
     * Get the cache key for the delivered count.
     */
    private function private__cacheKeyForDeliveredCount(): string
    {
        return $this->private__cacheKeyNamespace().'.delivered_count';
    }

    /**
     * Get the cache key for the error count.
     */
    private function private__cacheKeyForErrorCount(): string
    {
        return $this->private__cacheKeyNamespace().'.error_count';
    }

    /**
     * Cache the first error message.
     */
    private function private__cacheFirstErrorMessage(string $message): void
    {
        $key = $this->private__cacheKeyNamespace().'.first_error_message';
        if (Cache::get($key) !== null) {
            return;
        }
        Cache::put($key, $message, self::CACHE_TTL_SECONDS);
    }
}
