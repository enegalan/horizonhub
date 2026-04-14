<?php

namespace Tests\Unit;

use App\Models\Service;
use App\Services\Horizon\HorizonApiProxyService;
use App\Services\Horizon\HorizonJobListService;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HorizonJobListServiceTest extends TestCase
{
    #[Test]
    public function it_fetches_multiple_horizon_pages_before_paginating_processing_jobs(): void
    {
        $service = new Service;
        $service->forceFill([
            'id' => 1,
            'name' => 'Svc',
            'base_url' => 'http://example.test',
        ]);

        $pendingCall = 0;
        $api = $this->mock(HorizonApiProxyService::class);
        $api->shouldReceive('getPendingJobs')->andReturnUsing(function () use (&$pendingCall): array {
            $pendingCall++;
            if ($pendingCall === 1) {
                $jobs = [];
                for ($i = 0; $i < 50; $i++) {
                    $jobs[] = [
                        'id' => 'job-'.$i,
                        'queue' => 'default',
                        'name' => 'Job',
                        'payload' => ['pushedAt' => (string) \microtime(true)],
                        'index' => $i,
                    ];
                }

                return ['success' => true, 'data' => ['jobs' => $jobs]];
            }
            if ($pendingCall === 2) {
                return [
                    'success' => true,
                    'data' => [
                        'jobs' => [[
                            'id' => 'job-50',
                            'queue' => 'default',
                            'name' => 'Job',
                            'payload' => ['pushedAt' => (string) \microtime(true)],
                            'index' => 50,
                        ]],
                    ],
                ];
            }

            return ['success' => true, 'data' => ['jobs' => []]];
        });

        $api->shouldReceive('getCompletedJobs')->andReturn(['success' => true, 'data' => ['jobs' => []]]);
        $api->shouldReceive('getFailedJobs')->andReturn(['success' => true, 'data' => ['jobs' => []]]);

        $list = new HorizonJobListService($api);
        $paginators = $list->buildAggregatedStatusPaginators(
            \collect([$service]),
            '',
            1,
            1,
            1,
            20,
            'http://localhost/horizon',
            [],
        );

        $this->assertSame(51, $paginators['processing']->total());
        $this->assertCount(20, $paginators['processing']->items());
        $this->assertSame(2, $pendingCall);
    }

    #[Test]
    public function it_filters_by_search_across_fetched_jobs(): void
    {
        $service = new Service;
        $service->forceFill([
            'id' => 1,
            'name' => 'Svc',
            'base_url' => 'http://example.test',
        ]);

        $api = $this->mock(HorizonApiProxyService::class);
        $api->shouldReceive('getPendingJobs')->andReturn([
            'success' => true,
            'data' => [
                'jobs' => [
                    [
                        'id' => 'aaa',
                        'queue' => 'q1',
                        'name' => 'Alpha',
                        'payload' => ['pushedAt' => (string) \microtime(true)],
                        'index' => 0,
                    ],
                    [
                        'id' => 'bbb',
                        'queue' => 'q2',
                        'name' => 'Beta',
                        'payload' => ['pushedAt' => (string) \microtime(true)],
                        'index' => 1,
                    ],
                ],
            ],
        ]);
        $api->shouldReceive('getCompletedJobs')->andReturn(['success' => true, 'data' => ['jobs' => []]]);
        $api->shouldReceive('getFailedJobs')->andReturn(['success' => true, 'data' => ['jobs' => []]]);

        $list = new HorizonJobListService($api);
        $paginators = $list->buildAggregatedStatusPaginators(
            \collect([$service]),
            'Beta',
            1,
            1,
            1,
            20,
            'http://localhost/horizon',
            [],
        );

        $this->assertSame(1, $paginators['processing']->total());
        $this->assertSame('bbb', $paginators['processing']->items()[0]->uuid);
    }

    #[Test]
    public function it_exposes_delay_and_available_at_for_delayed_pending_jobs(): void
    {
        $service = new Service;
        $service->forceFill([
            'id' => 1,
            'name' => 'Svc',
            'base_url' => 'http://example.test',
        ]);

        $pushedAt = '1711111111.000';

        $api = $this->mock(HorizonApiProxyService::class);
        $api->shouldReceive('getPendingJobs')->andReturn([
            'success' => true,
            'data' => [
                'jobs' => [
                    [
                        'id' => 'delayed-job',
                        'queue' => 'default',
                        'name' => 'App\\Jobs\\DelayedJob',
                        'payload' => ['pushedAt' => $pushedAt, 'delay' => 300],
                        'index' => 0,
                    ],
                    [
                        'id' => 'immediate-job',
                        'queue' => 'default',
                        'name' => 'App\\Jobs\\ImmediateJob',
                        'payload' => ['pushedAt' => $pushedAt],
                        'index' => 1,
                    ],
                ],
            ],
        ]);
        $api->shouldReceive('getCompletedJobs')->andReturn(['success' => true, 'data' => ['jobs' => []]]);
        $api->shouldReceive('getFailedJobs')->andReturn(['success' => true, 'data' => ['jobs' => []]]);

        $list = new HorizonJobListService($api);
        $paginators = $list->buildAggregatedStatusPaginators(
            \collect([$service]),
            '',
            1,
            1,
            1,
            20,
            'http://localhost/horizon',
            [],
        );

        $items = $paginators['processing']->items();
        $this->assertCount(2, $items);

        $delayed = \collect($items)->first(fn ($j) => $j->uuid === 'delayed-job');
        $immediate = \collect($items)->first(fn ($j) => $j->uuid === 'immediate-job');

        $this->assertSame(300, $delayed->delay);
        $this->assertInstanceOf(Carbon::class, $delayed->available_at);
        $expectedAvailableAt = Carbon::createFromTimestampMs(1711111111000)->addSeconds(300);
        $this->assertSame($expectedAvailableAt->toIso8601String(), $delayed->available_at->toIso8601String());

        $this->assertNull($immediate->delay);
        $this->assertNull($immediate->available_at);
    }

    #[Test]
    public function it_uses_reserved_at_for_failed_runtime_fallback(): void
    {
        $service = new Service;
        $service->forceFill([
            'id' => 1,
            'name' => 'Svc',
            'base_url' => 'http://example.test',
        ]);

        $api = $this->mock(HorizonApiProxyService::class);
        $api->shouldReceive('getPendingJobs')->andReturn(['success' => true, 'data' => ['jobs' => []]]);
        $api->shouldReceive('getCompletedJobs')->andReturn(['success' => true, 'data' => ['jobs' => []]]);
        $api->shouldReceive('getFailedJobs')->andReturn([
            'success' => true,
            'data' => [
                'jobs' => [
                    [
                        'id' => 'failed-1',
                        'queue' => 'q1',
                        'name' => 'FailingJob',
                        'payload' => [
                            'pushedAt' => '1711111111.000',
                        ],
                        'reserved_at' => '1711111111.100',
                        'failed_at' => '1711111111.140',
                        'index' => 0,
                    ],
                ],
            ],
        ]);

        $list = new HorizonJobListService($api);
        $paginators = $list->buildAggregatedStatusPaginators(
            \collect([$service]),
            '',
            1,
            1,
            1,
            20,
            'http://localhost/horizon',
            [],
        );

        $this->assertSame(1, $paginators['failed']->total());
        $this->assertSame('0.04 s', $paginators['failed']->items()[0]->runtime);
    }
}
