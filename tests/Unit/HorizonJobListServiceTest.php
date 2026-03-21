<?php

namespace Tests\Unit;

use App\Models\Service;
use App\Services\HorizonApiProxyService;
use App\Services\HorizonJobListService;
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
}
