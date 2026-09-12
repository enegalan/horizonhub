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
     * Get the evaluation status.
     *
     * @param string $evaluationId The evaluation ID.
     *
     * @return array<string, mixed>
     */
    public function getEvaluationStatus(string $evaluationId): array
    {
        $store = new AlertBatchStore;

        return [
            'evaluation_id' => $evaluationId,
            'status' => $store->getStatus($evaluationId),
            'total_alerts' => $store->getTotalAlerts($evaluationId),
            'evaluated_count' => $store->getEvaluatedCount($evaluationId),
            'triggered_count' => $store->getTriggeredCount($evaluationId),
            'delivered_count' => $store->getDeliveredCount($evaluationId),
            'error_count' => $store->getErrorCount($evaluationId),
            'first_error_message' => $store->getFirstErrorMessage($evaluationId),
            'error_message' => $store->getErrorMessage($evaluationId),
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
        $store = new AlertBatchStore;

        $store->putStatus($evaluationId, $total > 0 ? EvaluationStatus::Running : EvaluationStatus::Completed);
        $store->putTotalAlerts($evaluationId, $total);
        $store->initializeCounters($evaluationId);

        if ($total === 0) {
            return [
                'evaluation_id' => $evaluationId,
                'status' => EvaluationStatus::Completed->value,
                'total_alerts' => 0,
            ];
        }

        $store->forgetBatchErrors($evaluationId);

        $jobs = [];

        foreach ($alertIds as $alertId) {
            $jobs[] = new EvaluateAlertJob((int) $alertId, $evaluationId);
        }

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
