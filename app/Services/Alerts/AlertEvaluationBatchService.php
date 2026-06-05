<?php

namespace App\Services\Alerts;

use App\Contracts\HorizonHubStore;
use App\Jobs\EvaluateAlertJob;
use App\Support\Alerts\AlertEvaluationBatchCache;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;

class AlertEvaluationBatchService
{
    /**
     * The horizon hub store.
     */
    private HorizonHubStore $store;

    /**
     * The constructor.
     *
     * @param HorizonHubStore $store The horizon hub store.
     */
    public function __construct(HorizonHubStore $store)
    {
        $this->store = $store;
    }

    /**
     * Get the evaluation status.
     *
     * @param string $evaluationId The evaluation ID.
     *
     * @return array<string, mixed>
     */
    public function getEvaluationStatus(string $evaluationId): array
    {
        $cache = new AlertEvaluationBatchCache($evaluationId);

        return [
            'evaluation_id' => $evaluationId,
            'status' => $cache->getStatus(),
            'total_alerts' => $cache->getTotalAlerts(),
            'evaluated_count' => $cache->getEvaluatedCount(),
            'triggered_count' => $cache->getTriggeredCount(),
            'delivered_count' => $cache->getDeliveredCount(),
            'error_count' => $cache->getErrorCount(),
            'first_error_message' => $cache->getFirstErrorMessage(),
            'error_message' => $cache->getErrorMessage(),
        ];
    }

    /**
     * Start evaluating all alerts.
     *
     * @return array{evaluation_id: string, status: string, total_alerts: int}
     */
    public function startEvaluateAll(): array
    {
        $alertIds = $this->store->enabledAlertIds();

        $total = \count($alertIds);
        $evaluationId = (string) Str::uuid();
        $cache = new AlertEvaluationBatchCache($evaluationId);

        $cache->putStatus($total > 0 ? 'running' : 'completed');
        $cache->putTotalAlerts($total);
        $cache->initializeCounters();

        if ($total === 0) {
            return [
                'evaluation_id' => $evaluationId,
                'status' => 'completed',
                'total_alerts' => 0,
            ];
        }

        $cache->forgetBatchErrors();

        $jobs = [];

        foreach ($alertIds as $alertId) {
            $jobs[] = new EvaluateAlertJob((int) $alertId, $evaluationId);
        }

        Bus::batch($jobs)
            ->name('HorizonHub: Evaluate all alerts')
            ->onConnection('deferred')
            ->then(function (Batch $batch) use ($cache): void {
                $cache->markCompleted();
            })
            ->catch(function (Batch $batch, \Throwable $e) use ($cache): void {
                $cache->markBatchFailed($e->getMessage());
            })
            ->dispatch();

        return [
            'evaluation_id' => $evaluationId,
            'status' => 'running',
            'total_alerts' => $total,
        ];
    }
}
