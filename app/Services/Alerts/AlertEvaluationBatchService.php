<?php

namespace App\Services\Alerts;

use App\Jobs\EvaluateAlertJob;
use App\Models\Alert;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class AlertEvaluationBatchService
{
    /**
     * Get the evaluation status.
     *
     * @param  string  $evaluationId  The evaluation ID.
     * @return array<string, mixed>
     */
    public function getEvaluationStatus(string $evaluationId): array
    {
        $namespace = "horizonhub.alert_evaluation_batches.$evaluationId";

        $status = (string) (Cache::get("$namespace.status") ?? 'running');
        $totalAlerts = (int) (Cache::get("$namespace.total_alerts") ?? 0);
        $evaluatedCount = (int) (Cache::get("$namespace.evaluated_count") ?? 0);
        $triggeredCount = (int) (Cache::get("$namespace.triggered_count") ?? 0);
        $deliveredCount = (int) (Cache::get("$namespace.delivered_count") ?? 0);
        $errorCount = (int) (Cache::get("$namespace.error_count") ?? 0);
        $firstErrorMessage = Cache::get("$namespace.first_error_message");
        $errorMessage = Cache::get("$namespace.error_message");

        return [
            'evaluation_id' => $evaluationId,
            'status' => $status,
            'total_alerts' => $totalAlerts,
            'evaluated_count' => $evaluatedCount,
            'triggered_count' => $triggeredCount,
            'delivered_count' => $deliveredCount,
            'error_count' => $errorCount,
            'first_error_message' => \is_string($firstErrorMessage) ? $firstErrorMessage : null,
            'error_message' => \is_string($errorMessage) ? $errorMessage : null,
        ];
    }

    /**
     * Start evaluating all alerts.
     *
     * @return array{evaluation_id: string, status: string, total_alerts: int}
     */
    public function startEvaluateAll(): array
    {
        $alertIds = Alert::query()
            ->where('enabled', true)
            ->pluck('id')
            ->all();

        $total = \count($alertIds);
        $evaluationId = (string) Str::uuid();
        $namespace = "horizonhub.alert_evaluation_batches.$evaluationId";

        Cache::put("$namespace.status", $total > 0 ? 'running' : 'completed', now()->addMinutes(30));
        Cache::put("$namespace.total_alerts", $total, now()->addMinutes(30));
        Cache::put("$namespace.evaluated_count", 0, now()->addMinutes(30));
        Cache::put("$namespace.triggered_count", 0, now()->addMinutes(30));
        Cache::put("$namespace.delivered_count", 0, now()->addMinutes(30));
        Cache::put("$namespace.error_count", 0, now()->addMinutes(30));

        if ($total === 0) {
            return [
                'evaluation_id' => $evaluationId,
                'status' => 'completed',
                'total_alerts' => 0,
                'evaluated_count' => 0,
                'triggered_count' => 0,
                'delivered_count' => 0,
                'error_count' => 0,
            ];
        }

        Cache::forget("$namespace.error_message");
        Cache::forget("$namespace.first_error_message");

        $jobs = [];
        foreach ($alertIds as $alertId) {
            $jobs[] = new EvaluateAlertJob((int) $alertId, $evaluationId);
        }

        Bus::batch($jobs)
            ->name('HorizonHub: Evaluate all alerts')
            ->then(function (Batch $batch) use ($namespace): void {
                Cache::put("$namespace.status", 'completed', now()->addMinutes(30));
            })
            ->catch(function (Batch $batch, \Throwable $e) use ($namespace): void {
                Cache::put("$namespace.status", 'failed', now()->addMinutes(30));
                Cache::put("$namespace.error_message", $e->getMessage(), now()->addMinutes(30));
            })
            ->dispatch();

        return [
            'evaluation_id' => $evaluationId,
            'status' => 'running',
            'total_alerts' => $total,
        ];
    }
}
