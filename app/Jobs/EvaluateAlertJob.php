<?php

namespace App\Jobs;

use App\Models\Alert;
use App\Services\Alerts\Engine\AlertEngine;
use App\Support\Alerts\AlertEvaluationBatchCache;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class EvaluateAlertJob implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The alert ID.
     */
    public int $alertId;

    /**
     * The evaluation ID.
     */
    public string $evaluationId;

    /**
     * The constructor.
     *
     * @param int $alertId The alert ID.
     * @param string $evaluationId The evaluation ID.
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
        $cache = new AlertEvaluationBatchCache($this->evaluationId);

        try {
            $alert = Alert::query()
                ->find($this->alertId);

            if (! $alert) {
                $cache->recordEvaluationError('Alert not found');

                return;
            }

            $cache->recordEvaluationResult($engine->evaluateAlert($alert));
        } catch (\Throwable $e) {
            Log::channel('hub')->error('alert evaluation job failed', [
                'alert_id' => $this->alertId,
                'evaluation_id' => $this->evaluationId,
                'error' => $e->getMessage(),
            ]);

            $cache->recordEvaluationError($e->getMessage());
        }
    }
}
