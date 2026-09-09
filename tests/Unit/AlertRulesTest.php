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
use App\Services\Jobs\JobsWindowFetcherService;
use App\Support\Alerts\AlertRuleEvaluation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AlertRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_evaluation_support_resolves_patterns_and_filters_jobs(): void
    {
        $support = new AlertRuleEvaluation(new JobsWindowFetcherService);
        $alert = new Alert([
            'threshold' => [
                'queue_patterns' => ['emails', 'default'],
                'job_patterns' => ['App\\Jobs\\Sync', 'App\\Jobs\\Sync'],
            ],
        ]);

        $this->assertSame(['emails', 'default'], $support->resolveQueuePatterns($alert));
        $this->assertSame(['App\\Jobs\\Sync'], $support->resolveJobPatterns($alert));

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

        Http::fake(function ($request) {
            if (\str_contains($request->url(), '/horizon/api/jobs/failed')) {
                return Http::response(['jobs' => [
                    ['id' => 'x1', 'failed_at' => now()->subMinute()->toIso8601String(), 'queue' => 'default', 'payload' => ['displayName' => 'A']],
                    ['id' => 'x2', 'failed_at' => now()->subMinute()->toIso8601String(), 'queue' => 'default', 'payload' => ['displayName' => 'B']],
                ]], 200);
            }

            return Http::response('unexpected', 500);
        });

        $support = new AlertRuleEvaluation(new JobsWindowFetcherService);
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

        Http::fake([
            'https://example.test/horizon/api/stats' => Http::response(['status' => 'inactive'], 200),
        ]);
        $strategy = new HorizonOffline;

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

        $statsCalls = 0;

        Http::fake(function ($request) use (&$statsCalls) {
            if ($request->url() === 'https://example.test/horizon/api/stats') {
                $statsCalls++;

                return Http::response(['status' => $statsCalls <= 2 ? 'inactive' : 'active'], 200);
            }

            if ($request->url() === 'https://example.test/horizon/api/masters') {
                return Http::response([[
                    'supervisors' => [
                        ['last_heartbeat_at' => now()->subHour()->toIso8601String()],
                    ],
                ]], 200);
            }

            if (\str_contains($request->url(), '/horizon/api/jobs/completed')) {
                return Http::response(['jobs' => [
                    [
                        'completed_at' => now()->subMinute()->toIso8601String(),
                        'reserved_at' => now()->subMinute()->subSeconds(30)->toIso8601String(),
                        'queue' => 'default',
                        'payload' => [
                            'displayName' => 'X',
                            'pushedAt' => now()->subMinute()->subSeconds(45)->toIso8601String(),
                        ],
                    ],
                ]], 200);
            }

            return Http::response('unexpected', 500);
        });

        \config()->set('horizonhub.hot_reload_interval', 0);

        $support = new AlertRuleEvaluation(new JobsWindowFetcherService);

        $worker = new WorkerOffline;
        $this->assertTrue($worker->evaluateWithTriggeringJobs($workerAlert, $service->id)['triggered']);

        $avg = new AvgExecutionTime($support);
        $this->assertTrue($avg->evaluateWithTriggeringJobs($avgAlert, $service->id)['triggered']);

        $queueBlocked = new QueueBlocked($support);
        $this->assertFalse($queueBlocked->evaluateWithTriggeringJobs($queueAlert, $service->id)['triggered']);

        $supervisor = new SupervisorOffline;
        $this->assertTrue($supervisor->evaluateWithTriggeringJobs($supAlert, $service->id)['triggered']);

        $offline = new HorizonOffline;
        $this->assertFalse($offline->evaluateWithTriggeringJobs($horizonAlert, $service->id)['triggered']);

        $this->travel(6)->minutes();
        $this->assertTrue($offline->evaluateWithTriggeringJobs($horizonAlert, $service->id)['triggered']);

        $onlineStrategy = new HorizonOffline;
        $this->assertFalse($onlineStrategy->evaluateWithTriggeringJobs($horizonAlert, $service->id)['triggered']);
        $this->assertNull(Cache::get('horizon_offline_since:' . $service->id));

        $null = new NullRule;
        $this->assertFalse($null->evaluateWithTriggeringJobs($horizonAlert, $service->id)['triggered']);
    }

    public function test_queue_patterns_match_raw_then_unprefixed_normalized_only(): void
    {
        $support = new AlertRuleEvaluation(new JobsWindowFetcherService);

        $unprefixed = new Alert([
            'threshold' => ['queue_patterns' => ['default']],
        ]);
        $this->assertTrue($support->jobRowMatches($unprefixed, [
            'queue' => 'redis.default',
            'payload' => [],
        ]));
        $this->assertTrue($support->jobRowMatches($unprefixed, [
            'queue' => 'default',
            'payload' => [],
        ]));

        $prefixed = new Alert([
            'threshold' => ['queue_patterns' => ['redis.default']],
        ]);
        $this->assertTrue($support->jobRowMatches($prefixed, [
            'queue' => 'redis.default',
            'payload' => [],
        ]));
        $this->assertFalse($support->jobRowMatches($prefixed, [
            'queue' => 'sqs.default',
            'payload' => [],
        ]));
    }

    public function test_registry_resolves_known_and_unknown_rules(): void
    {
        $registry = $this->app->make(AlertRuleStrategyRegistry::class);

        $this->assertInstanceOf(FailureCount::class, $registry->resolve(FailureCount::type()));
        $this->assertInstanceOf(NullRule::class, $registry->resolve('unknown-rule'));
    }
}
