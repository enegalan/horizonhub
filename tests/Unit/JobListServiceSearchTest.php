<?php

namespace Tests\Unit;

use App\Models\Service;
use App\Services\Jobs\JobListService;
use App\Support\Jobs\SearchResultsPaginator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class JobListServiceSearchTest extends TestCase
{
    use RefreshDatabase;

    private array $requests = [];

    public function test_aggregated_search_reports_truncation_for_any_truncated_service(): void
    {
        $this->private__configurePaging();
        config()->set('horizonhub.job_search_match_cap', 2);

        $capped = $this->private__service('svc-agg-capped');
        $complete = $this->private__service('svc-agg-complete');

        $request = Request::create('/horizon/jobs', 'GET', [
            'search' => 'Invoice',
            'service_id' => [$capped->id, $complete->id],
        ]);

        $aggregated = JobListService::buildAggregatedJobsIndexFromRequest($request);

        $this->assertSame(4, $aggregated['failed']->total());
        $this->assertTrue($aggregated['failed']->resultsMayBeTruncated());
    }

    public function test_match_cap_does_not_apply_without_a_search(): void
    {
        $this->private__configurePaging();
        config()->set('horizonhub.job_search_match_cap', 1);

        $service = $this->private__service('svc-unsearched');

        $paginator = JobListService::buildServiceStatusPaginators($service, '', 1, 1, 1, 10, '/horizon/services/' . $service->id, [])['failed'];

        $this->assertSame(5, $paginator->total());
        $this->assertFalse($paginator->resultsMayBeTruncated());
        $this->assertSame(3, $this->requests['failed']);
    }

    public function test_match_cap_never_truncates_the_failed_jobs_retry_modal(): void
    {
        $this->private__configurePaging();
        config()->set('horizonhub.job_search_match_cap', 1);

        $service = $this->private__service('svc-retry-modal-cap');

        $page = JobListService::buildFailedJobsRetryModalPage(new Collection([$service]), 'Invoice', null, null, 1, \PHP_INT_MAX);

        $this->assertSame(2, $page['total']);
        $this->assertSame(['failed-invoice-1', 'failed-invoice-2'], \array_column($page['rows'], 'uuid'));
        $this->assertSame(3, $this->requests['failed']);
    }

    public function test_search_filters_the_raw_payload_before_rows_are_mapped(): void
    {
        $this->private__configurePaging();

        $service = $this->private__service('svc-search');

        $paginators = JobListService::buildServiceStatusPaginators($service, 'Invoice', 1, 1, 1, 10, '/horizon/services/' . $service->id, []);

        $this->assertSame(2, $paginators['failed']->total());
        $this->assertSame(['failed-invoice-1', 'failed-invoice-2'], $paginators['failed']->pluck('uuid')->all());
        $this->assertSame(1, $paginators['processed']->total());
        $this->assertSame(0, $paginators['processing']->total());
    }

    public function test_search_stops_paginating_at_the_match_cap_and_flags_the_total(): void
    {
        $this->private__configurePaging();
        config()->set('horizonhub.job_search_match_cap', 2);

        $service = $this->private__service('svc-capped');

        $paginator = JobListService::buildServiceStatusPaginators($service, 'Invoice', 1, 1, 1, 10, '/horizon/services/' . $service->id, [])['failed'];

        $this->assertSame(2, $paginator->total());
        $this->assertSame(1, $paginator->lastPage());
        $this->assertInstanceOf(SearchResultsPaginator::class, $paginator);
        $this->assertTrue($paginator->resultsMayBeTruncated());
        $this->assertSame(2, $this->requests['failed']);
    }

    public function test_search_without_reaching_the_match_cap_keeps_an_exact_total(): void
    {
        $this->private__configurePaging();
        config()->set('horizonhub.job_search_match_cap', 10);

        $service = $this->private__service('svc-exact');

        $paginator = JobListService::buildServiceStatusPaginators($service, 'Invoice', 1, 1, 1, 10, '/horizon/services/' . $service->id, [])['failed'];

        $this->assertSame(2, $paginator->total());
        $this->assertFalse($paginator->resultsMayBeTruncated());
        $this->assertSame(3, $this->requests['failed']);
    }

    /**
     * Shrink the paging window to two rows per Horizon request, ten requests and ten rows per page.
     */
    private function private__configurePaging(): void
    {
        config()->set('horizonhub.horizon_api_job_list_page_size', 2);
        config()->set('horizonhub.max_horizon_pages', 10);
        config()->set('horizonhub.jobs_per_page', 10);
    }

    /**
     * Build the raw Horizon payload rows the fake serves for a job status.
     *
     * IDs containing "invoice" get the `App\Jobs\SendInvoice` job name, so only
     * they match the "Invoice" search; timestamps count one minute back per
     * position so the newest row sorts first.
     *
     * @param list<string> $ids The job UUIDs, newest first as Horizon returns them.
     * @param string $status The job status the payload belongs to.
     *
     * @return list<array<string, mixed>>
     */
    private function private__jobList(array $ids, string $status): array
    {
        $pageSize = (int) config('horizonhub.horizon_api_job_list_page_size');

        return \array_map(static function (string $id, int $position) use ($status): array {
            $timestamp = (string) (1717249200 - ($position * 60));
            $name = \str_contains($id, 'invoice') ? 'App\\Jobs\\SendInvoice' : 'App\\Jobs\\SendReport';

            return match ($status) {
                'failed' => ['id' => $id, 'queue' => 'redis.default', 'name' => $name, 'failed_at' => $timestamp, 'index' => $position + 1],
                'completed' => ['id' => $id, 'queue' => 'redis.default', 'name' => $name, 'completed_at' => $timestamp, 'index' => $position + 1],
                default => ['id' => $id, 'queue' => 'redis.default', 'name' => $name, 'pushedAt' => (int) $timestamp, 'index' => $position + 1],
            };
        }, $ids, \array_keys($ids));
    }

    /**
     * Serve one Horizon page of job rows and count the request in the shared counter.
     *
     * @param list<string> $ids The job UUIDs to slice.
     * @param string $status The job status the request targeted.
     * @param int $startingAt The `starting_at` cursor sent by the client.
     * @param array<string, int> $requests The per-status request counter, incremented by reference.
     *
     * @return list<array<string, mixed>>
     */
    private function private__page(array $ids, string $status, int $startingAt, array &$requests): array
    {
        $requests[$status] = ($requests[$status] ?? 0) + 1;

        $pageSize = (int) config('horizonhub.horizon_api_job_list_page_size');

        return \array_slice($this->private__jobList($ids, $status), \max(0, $startingAt), $pageSize);
    }

    /**
     * Create a service and fake its Horizon job list endpoints.
     *
     * The fake serves a fixed set of failed, completed and pending job UUIDs and
     * records how many requests each status received in `$requests`, which is
     * what the match-cap assertions inspect.
     *
     * @param string $name The service name, also used as its test host.
     */
    private function private__service(string $name): Service
    {
        $service = Service::create([
            'name' => $name,
            'base_url' => 'https://' . $name . '.test',
            'status' => 'online',
        ]);

        $requests = &$this->requests;

        $lists = [
            'failed' => ['failed-invoice-1', 'failed-a', 'failed-invoice-2', 'failed-b', 'failed-c'],
            'completed' => ['completed-invoice', 'completed-a', 'completed-b'],
            'pending' => ['pending-a', 'pending-b'],
        ];

        Http::fake(function ($request) use ($lists, &$requests) {
            $status = match (true) {
                str_contains($request->url(), '/jobs/failed') => 'failed',
                str_contains($request->url(), '/jobs/completed') => 'completed',
                str_contains($request->url(), '/jobs/pending') => 'pending',
                default => null,
            };

            if ($status === null) {
                return Http::response('unexpected', 500);
            }

            $startingAt = (int) ($request->data()['starting_at'] ?? -1);

            return Http::response([
                'jobs' => $this->private__page($lists[$status], $status, $startingAt, $requests),
            ], 200);
        });

        return $service;
    }
}
