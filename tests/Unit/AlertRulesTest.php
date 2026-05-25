<?php

namespace Tests\Unit;

use App\Models\Alert;
use App\Models\Service;
use App\Services\Alerts\Rules\AlertRuleStrategyRegistry;
use App\Services\Alerts\Rules\Strategies\AvgExecutionTime;
use App\Services\Alerts\Rules\Strategies\FailureCount;
use App\Services\Alerts\Rules\Strategies\HorizonOffline;
use App\Services\Alerts\Rules\Strategies\NullRule;
use App\Services\Alerts\Rules\Strategies\QueueBlocked;
use App\Services\Alerts\Rules\Strategies\SupervisorOffline;
use App\Services\Alerts\Rules\Strategies\WorkerOffline;
use App\Services\Horizon\HorizonClientService;
use App\Services\Jobs\JobsWindowFetcher;
use App\Support\Alerts\AlertRuleEvaluation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlertRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_evaluation_support_resolves_patterns_and_filters_jobs(): void
    {
        $api = $this->createMock(HorizonClientService::class);
        $support = new AlertRuleEvaluation(new JobsWindowFetcher($api));
        $alert = new Alert([
            'queue' => 'default',
            'job_type' => 'App\\Jobs\\Sync',
            'threshold' => [
                'queue_patterns' => ['emails', 'default'],
                'job_patterns' => ['App\\Jobs\\Sync', 'App\\Jobs\\Sync'],
            ],
        ]);

        $this->assertSame(['emails', 'default'], $support->resolveQueuePatterns($alert));
        $this->assertSame(['App\\Jobs\\Sync'], $support->resolveJobPatterns($alert));
        $this->assertTrue($support->jobMatchesQueuePatterns($alert, ['queue' => 'emails']));
        $this->assertFalse($support->jobMatchesQueuePatterns($alert, ['queue' => 'other']));

        $jobs = collect([
            ['id' => 'j1', 'failed_at' => now()->subMinute()->toIso8601String(), 'queue' => 'default', 'payload' => ['displayName' => 'App\\Jobs\\Sync']],
            ['id' => '', 'failed_at' => now()->subDays(2)->toIso8601String(), 'queue' => 'default', 'payload' => ['displayName' => 'App\\Jobs\\Sync']],
            'not-array',
        ]);

        $inWindow = $support->filterFailedJobsInWindow($jobs, now()->subMinutes(10));
        $this->assertCount(1, $inWindow);
        $this->assertSame(['j1'], $support->collectTriggeringJobUuids($inWindow->values()));
        $this->assertTrue($support->jobRowMatches($alert, $inWindow->first()));
    }

    public function test_failure_count_strategy_handles_threshold_and_service_guards(): void
    {
        $service = Service::query()->create([
            'name' => 'svc-a',
            'base_url' => 'https://example.test',
            'status' => 'online',
        ]);
        $alert = Alert::query()->create([
            'name' => 'a1',
            'rule_type' => Alert::RULE_FAILURE_COUNT,
            'threshold' => ['count' => 2, 'minutes' => 10],
            'enabled' => true,
        ]);

        $api = $this->createMock(HorizonClientService::class);
        $api->method('getFailedJobs')->willReturn([
            'success' => true,
            'data' => [
                'jobs' => [
                    ['id' => 'x1', 'failed_at' => now()->subMinute()->toIso8601String(), 'queue' => 'default', 'payload' => ['displayName' => 'A']],
                    ['id' => 'x2', 'failed_at' => now()->subMinute()->toIso8601String(), 'queue' => 'default', 'payload' => ['displayName' => 'B']],
                ],
            ],
        ]);
        $support = new AlertRuleEvaluation(new JobsWindowFetcher($api));
        $strategy = new FailureCount($support);
        $result = $strategy->evaluateWithTriggeringJobs($alert, $service->id);

        $this->assertTrue($result['triggered']);
        $this->assertSame(['x1', 'x2'], $result['job_uuids']);

        $this->assertFalse($strategy->evaluateWithTriggeringJobs($alert, 999999)['triggered']);
    }

    public function test_other_strategies_cover_normal_and_edge_paths(): void
    {
        $service = Service::query()->create([
            'name' => 'svc-b',
            'base_url' => 'https://example.test',
            'status' => 'online',
            'last_seen_at' => now()->subMinutes(90),
        ]);

        $avgAlert = Alert::query()->create([
            'name' => 'avg',
            'rule_type' => Alert::RULE_AVG_EXECUTION_TIME,
            'threshold' => ['seconds' => 20, 'minutes' => 10],
            'enabled' => true,
        ]);
        $queueAlert = Alert::query()->create([
            'name' => 'queue',
            'rule_type' => Alert::RULE_QUEUE_BLOCKED,
            'threshold' => ['minutes' => 5],
            'queue' => 'default',
            'enabled' => true,
        ]);
        $workerAlert = Alert::query()->create([
            'name' => 'worker',
            'rule_type' => Alert::RULE_WORKER_OFFLINE,
            'threshold' => ['minutes' => 30],
            'enabled' => true,
        ]);
        $supAlert = Alert::query()->create([
            'name' => 'sup',
            'rule_type' => Alert::RULE_SUPERVISOR_OFFLINE,
            'threshold' => ['minutes' => 15],
            'enabled' => true,
        ]);
        $horizonAlert = Alert::query()->create([
            'name' => 'hoff',
            'rule_type' => Alert::RULE_HORIZON_OFFLINE,
            'enabled' => true,
        ]);

        $api = $this->createMock(HorizonClientService::class);
        $api->method('getCompletedJobs')->willReturn([
            'success' => true,
            'data' => [
                'jobs' => [
                    [
                        'completed_at' => now()->subMinute()->toIso8601String(),
                        'pushedAt' => now()->subMinute()->subSeconds(30)->toIso8601String(),
                        'queue' => 'default',
                        'payload' => ['displayName' => 'X'],
                    ],
                ],
            ],
        ]);
        $api->method('getMasters')->willReturn([
            'success' => true,
            'data' => [[
                'supervisors' => [
                    ['last_heartbeat_at' => now()->subHour()->toIso8601String()],
                ],
            ]],
        ]);
        $api->method('getStats')->willReturn(['success' => true, 'data' => ['status' => 'inactive']]);

        $support = new AlertRuleEvaluation(new JobsWindowFetcher($api));

        $avg = new AvgExecutionTime($support);
        $this->assertTrue($avg->evaluateWithTriggeringJobs($avgAlert, $service->id)['triggered']);

        $queueBlocked = new QueueBlocked($support);
        $this->assertFalse($queueBlocked->evaluateWithTriggeringJobs($queueAlert, $service->id)['triggered']);

        $worker = new WorkerOffline;
        $this->assertTrue($worker->evaluateWithTriggeringJobs($workerAlert, $service->id)['triggered']);

        $supervisor = new SupervisorOffline($api);
        $this->assertTrue($supervisor->evaluateWithTriggeringJobs($supAlert, $service->id)['triggered']);

        $offline = new HorizonOffline($api);
        $this->assertTrue($offline->evaluateWithTriggeringJobs($horizonAlert, $service->id)['triggered']);

        $null = new NullRule;
        $this->assertFalse($null->evaluateWithTriggeringJobs($horizonAlert, $service->id)['triggered']);
    }

    public function test_registry_resolves_known_and_unknown_rules(): void
    {
        $api = $this->createMock(HorizonClientService::class);
        $support = new AlertRuleEvaluation(new JobsWindowFetcher($api));
        $null = new NullRule;
        $registry = new AlertRuleStrategyRegistry(
            $null,
            new FailureCount($support),
            new AvgExecutionTime($support),
            new QueueBlocked($support),
            new WorkerOffline,
            new SupervisorOffline($api),
            new HorizonOffline($api),
        );

        $this->assertInstanceOf(FailureCount::class, $registry->resolve(Alert::RULE_FAILURE_COUNT));
        $this->assertSame($null, $registry->resolve('unknown-rule'));
    }
}
