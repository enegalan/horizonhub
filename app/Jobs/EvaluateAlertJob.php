<?php

namespace App\Jobs;

use App\Models\Alert;
use App\Services\AlertEngine;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class EvaluateAlertJob implements ShouldQueue {
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
     * Create a new job instance.
     *
     * @param int $alertId
     * @param string $evaluationId
     */
    public function __construct(
        public int $alertId,
        public string $evaluationId
    ) {}

    /**
     * Execute the job.
     *
     * @param AlertEngine $engine
     * @return void
     */
    public function handle(AlertEngine $engine): void {
        try {
            $alert = Alert::query()
                ->with('notificationProviders')
                ->find($this->alertId);

            if (! $alert) {
                Cache::put(
                    $this->cacheKeyForAlertResult(),
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
                Cache::increment($this->cacheKeyForEvaluatedCount(), 1);
                Cache::increment($this->cacheKeyForErrorCount(), 1);
                return;
            }

            $result = $engine->evaluateAlert($alert);

            Cache::put(
                $this->cacheKeyForAlertResult(),
                $result,
                self::CACHE_TTL_SECONDS
            );

            Cache::increment($this->cacheKeyForEvaluatedCount(), 1);
            if (! empty($result['triggered'])) {
                Cache::increment($this->cacheKeyForTriggeredCount(), 1);
            }
            if (! empty($result['delivered'])) {
                Cache::increment($this->cacheKeyForDeliveredCount(), 1);
            }
            if (! empty($result['error_message'])) {
                Cache::increment($this->cacheKeyForErrorCount(), 1);
                $this->cacheFirstErrorMessage($result['error_message']);
            }
        } catch (\Throwable $e) {
            Log::error('Horizon Hub: alert evaluation job failed', [
                'alert_id' => $this->alertId,
                'evaluation_id' => $this->evaluationId,
                'error' => $e->getMessage(),
            ]);

            Cache::increment($this->cacheKeyForEvaluatedCount(), 1);
            Cache::increment($this->cacheKeyForErrorCount(), 1);
            $this->cacheFirstErrorMessage($e->getMessage());
            Cache::put(
                $this->cacheKeyForAlertResult(),
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
     *
     * @return string
     */
    private function cacheKeyNamespace(): string {
        return "horizonhub.alert_evaluation_batches.$this->evaluationId";
    }

    /**
     * Get the cache key for the alert result.
     *
     * @return string
     */
    private function cacheKeyForAlertResult(): string {
        return $this->cacheKeyNamespace() . '.results.' . $this->alertId;
    }

    /**
     * Get the cache key for the evaluated count.
     *
     * @return string
     */
    private function cacheKeyForEvaluatedCount(): string {
        return $this->cacheKeyNamespace() . '.evaluated_count';
    }

    /**
     * Get the cache key for the triggered count.
     *
     * @return string
     */
    private function cacheKeyForTriggeredCount(): string {
        return $this->cacheKeyNamespace() . '.triggered_count';
    }

    /**
     * Get the cache key for the delivered count.
     *
     * @return string
     */
    private function cacheKeyForDeliveredCount(): string {
        return $this->cacheKeyNamespace() . '.delivered_count';
    }

    /**
     * Get the cache key for the error count.
     *
     * @return string
     */
    private function cacheKeyForErrorCount(): string {
        return $this->cacheKeyNamespace() . '.error_count';
    }

    /**
     * Cache the first error message.
     *
     * @param string $message
     * @return void
     */
    private function cacheFirstErrorMessage(string $message): void {
        $key = $this->cacheKeyNamespace() . '.first_error_message';
        if (Cache::get($key) !== null) {
            return;
        }
        Cache::put($key, $message, self::CACHE_TTL_SECONDS);
    }
}
