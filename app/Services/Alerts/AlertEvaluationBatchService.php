<?php

namespace App\Services\Alerts;

use App\Enums\EvaluationStatus;
use App\Jobs\EvaluateAlertJob;
use App\Models\Alert;
use App\Services\Alerts\Engine\AlertBatchStore;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;

class AlertEvaluationBatchService
{
    /**
     * The batch store.
     */
    private AlertBatchStore $batchStore;

    /**
     * The constructor.
     *
     * @param AlertBatchStore $batchStore The batch store.
     */
    public function __construct(AlertBatchStore $batchStore)
    {
        $this->batchStore = $batchStore;
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
        return [
            'evaluation_id' => $evaluationId,
            'status' => $this->batchStore->getStatus($evaluationId),
            'total_alerts' => $this->batchStore->getTotalAlerts($evaluationId),
            'evaluated_count' => $this->batchStore->getEvaluatedCount($evaluationId),
            'triggered_count' => $this->batchStore->getTriggeredCount($evaluationId),
            'delivered_count' => $this->batchStore->getDeliveredCount($evaluationId),
            'error_count' => $this->batchStore->getErrorCount($evaluationId),
            'first_error_message' => $this->batchStore->getFirstErrorMessage($evaluationId),
            'error_message' => $this->batchStore->getErrorMessage($evaluationId),
        ];
    }

    /**
     * Start evaluating all alerts.
     *
     * @return array{evaluation_id: string, status: string, total_alerts: int}
     */
    public function startEvaluateAll(): array
    {
        $alertIds = Alert::enabled()
            ->pluck('id')
            ->all();

        $total = \count($alertIds);
        $evaluationId = (string) Str::uuid();
        $this->batchStore->putStatus($evaluationId, $total > 0 ? EvaluationStatus::Running : EvaluationStatus::Completed);
        $this->batchStore->putTotalAlerts($evaluationId, $total);
        $this->batchStore->initializeCounters($evaluationId);

        if ($total === 0) {
            return [
                'evaluation_id' => $evaluationId,
                'status' => EvaluationStatus::Completed->value,
                'total_alerts' => 0,
            ];
        }

        $this->batchStore->forgetBatchErrors($evaluationId);

        $jobs = [];

        foreach ($alertIds as $alertId) {
            $jobs[] = new EvaluateAlertJob((int) $alertId, $evaluationId);
        }

        $store = $this->batchStore;

        Bus::batch($jobs)
            ->name('HorizonHub: Evaluate all alerts')
            ->onConnection('deferred')
            ->then(function (Batch $batch) use ($store, $evaluationId): void {
                $store->markCompleted($evaluationId);
            })
            ->catch(function (Batch $batch, \Throwable $e) use ($store, $evaluationId): void {
                $store->markBatchFailed($evaluationId, $e->getMessage());
            })
            ->dispatch();

        return [
            'evaluation_id' => $evaluationId,
            'status' => EvaluationStatus::Running->value,
            'total_alerts' => $total,
        ];
    }
}
