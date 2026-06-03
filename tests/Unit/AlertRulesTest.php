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
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AlertRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_evaluation_support_resolves_patterns_and_filters_jobs(): void
    {
        $api = $this->createMock(HorizonClientService::class);
        $support = new AlertRuleEvaluation(new JobsWindowFetcher($api));
        $alert = new Alert([
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
        $service = Service::create([
            'name' => 'svc-a',
            'base_url' => 'https://example.test',
            'status' => 'online',
        ]);
        $alert = Alert::create([
            'name' => 'a1',
            'rule_type' => FailureCount::type(),
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

    public function test_horizon_offline_grace_period_respects_long_threshold(): void
    {
        Cache::flush();

        $service = Service::create([
            'name' => 'svc-long-offline',
            'base_url' => 'https://example.test',
            'status' => 'online',
        ]);
        $alert = Alert::create([
            'name' => 'long-offline',
            'rule_type' => HorizonOffline::type(),
            'threshold' => ['minutes' => 2000],
            'enabled' => true,
        ]);

        $api = $this->createMock(HorizonClientService::class);
        $api->method('getStats')->willReturn(['success' => true, 'data' => ['status' => 'inactive']]);
        $strategy = new HorizonOffline($api);

        $this->assertFalse($strategy->evaluateWithTriggeringJobs($alert, $service->id)['triggered']);

        $this->travel(1999)->minutes();
        $this->assertFalse($strategy->evaluateWithTriggeringJobs($alert, $service->id)['triggered']);
        $this->assertNotNull(Cache::get('horizon_offline_since:' . $service->id));

        $this->travel(2)->minutes();
        $this->assertTrue($strategy->evaluateWithTriggeringJobs($alert, $service->id)['triggered']);
    }

    public function test_other_strategies_cover_normal_and_edge_paths(): void
    {
        $service = Service::create([
            'name' => 'svc-b',
            'base_url' => 'https://example.test',
            'status' => 'online',
            'last_seen_at' => now()->subMinutes(90),
        ]);

        $avgAlert = Alert::create([
            'name' => 'avg',
            'rule_type' => AvgExecutionTime::type(),
            'threshold' => ['seconds' => 20, 'minutes' => 10],
            'enabled' => true,
        ]);
        $queueAlert = Alert::create([
            'name' => 'queue',
            'rule_type' => QueueBlocked::type(),
            'threshold' => ['minutes' => 5],
            'enabled' => true,
        ]);
        $workerAlert = Alert::create([
            'name' => 'worker',
            'rule_type' => WorkerOffline::type(),
            'threshold' => ['minutes' => 30],
            'enabled' => true,
        ]);
        $supAlert = Alert::create([
            'name' => 'sup',
            'rule_type' => SupervisorOffline::type(),
            'threshold' => ['minutes' => 15],
            'enabled' => true,
        ]);
        $horizonAlert = Alert::create([
            'name' => 'hoff',
            'rule_type' => HorizonOffline::type(),
            'threshold' => ['minutes' => 5],
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
        $this->assertFalse($offline->evaluateWithTriggeringJobs($horizonAlert, $service->id)['triggered']);

        $this->travel(6)->minutes();
        $this->assertTrue($offline->evaluateWithTriggeringJobs($horizonAlert, $service->id)['triggered']);

        $apiOnline = $this->createMock(HorizonClientService::class);
        $apiOnline->method('getStats')->willReturn(['success' => true, 'data' => ['status' => 'active']]);
        $onlineStrategy = new HorizonOffline($apiOnline);
        $this->assertFalse($onlineStrategy->evaluateWithTriggeringJobs($horizonAlert, $service->id)['triggered']);
        $this->assertNull(Cache::get('horizon_offline_since:' . $service->id));

        $null = new NullRule;
        $this->assertFalse($null->evaluateWithTriggeringJobs($horizonAlert, $service->id)['triggered']);
    }

    public function test_registry_resolves_known_and_unknown_rules(): void
    {
        $api = $this->createMock(HorizonClientService::class);
        $this->app->instance(HorizonClientService::class, $api);
        $registry = $this->app->make(AlertRuleStrategyRegistry::class);

        $this->assertInstanceOf(FailureCount::class, $registry->resolve(FailureCount::type()));
        $this->assertInstanceOf(NullRule::class, $registry->resolve('unknown-rule'));
    }
}
