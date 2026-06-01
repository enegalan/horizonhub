<?php

namespace Tests\Unit;

use App\Jobs\EvaluateAlertJob;
use App\Models\Alert;
use App\Services\Alerts\AlertEvaluationBatchService;
use App\Services\Alerts\Rules\Strategies\FailureCount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AlertEvaluationBatchServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_evaluation_status_casts_values_and_filters_invalid_strings(): void
    {
        Cache::put('horizonhub.alert_evaluation_batches.ev1.status', 'failed', 1800);
        Cache::put('horizonhub.alert_evaluation_batches.ev1.total_alerts', '2', 1800);
        Cache::put('horizonhub.alert_evaluation_batches.ev1.evaluated_count', '1', 1800);
        Cache::put('horizonhub.alert_evaluation_batches.ev1.triggered_count', '1', 1800);
        Cache::put('horizonhub.alert_evaluation_batches.ev1.delivered_count', '0', 1800);
        Cache::put('horizonhub.alert_evaluation_batches.ev1.error_count', '1', 1800);
        Cache::put('horizonhub.alert_evaluation_batches.ev1.first_error_message', ['bad'], 1800);
        Cache::put('horizonhub.alert_evaluation_batches.ev1.error_message', 'boom', 1800);

        $service = new AlertEvaluationBatchService;
        $status = $service->getEvaluationStatus('ev1');

        $this->assertSame('failed', $status['status']);
        $this->assertSame(2, $status['total_alerts']);
        $this->assertNull($status['first_error_message']);
        $this->assertSame('boom', $status['error_message']);
    }

    public function test_start_evaluate_all_dispatches_batch_for_enabled_alerts(): void
    {
        Cache::flush();
        Bus::fake();

        $a1 = Alert::query()->create(['name' => 'a1', 'rule_type' => FailureCount::type(), 'enabled' => true]);
        $a2 = Alert::query()->create(['name' => 'a2', 'rule_type' => FailureCount::type(), 'enabled' => true]);

        $service = new AlertEvaluationBatchService;
        $result = $service->startEvaluateAll();

        $this->private__assertStartEvaluateAllResponseShape($result);
        $this->assertSame('running', $result['status']);
        $this->assertSame(2, $result['total_alerts']);
        Bus::assertBatched(function ($batch) use ($a1, $a2) {
            if (\count($batch->jobs) !== 2) {
                return false;
            }

            if ($batch->connection() !== 'deferred') {
                return false;
            }

            $ids = array_map(function ($job) {
                return $job instanceof EvaluateAlertJob ? $job->alertId : null;
            }, $batch->jobs->all());
            sort($ids);

            $expected = [$a1->id, $a2->id];
            sort($expected);

            return $ids === $expected;
        });
    }

    public function test_start_evaluate_all_returns_completed_when_no_enabled_alerts(): void
    {
        Cache::flush();
        Bus::fake();

        Alert::query()->create([
            'name' => 'disabled',
            'rule_type' => FailureCount::type(),
            'enabled' => false,
        ]);

        $service = new AlertEvaluationBatchService;
        $result = $service->startEvaluateAll();

        $this->private__assertStartEvaluateAllResponseShape($result);
        $this->assertSame('completed', $result['status']);
        $this->assertSame(0, $result['total_alerts']);
        Bus::assertNothingBatched();
    }

    /**
     * @param array<string, mixed> $result
     */
    private function private__assertStartEvaluateAllResponseShape(array $result): void
    {
        $this->assertSame(['evaluation_id', 'status', 'total_alerts'], array_keys($result));
        $this->assertIsString($result['evaluation_id']);
        $this->assertNotSame('', $result['evaluation_id']);
        $this->assertIsString($result['status']);
        $this->assertIsInt($result['total_alerts']);
    }
}
