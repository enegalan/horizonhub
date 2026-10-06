<?php

namespace App\Services\Jobs;

use App\Models\Service;
use App\Services\Horizon\HorizonClientApiService;
use App\Services\Services\ServiceFilterService;
use App\Support\DatetimeBoundaryParser;
use App\Support\Jobs\JobRuntime;
use App\Support\Jobs\JobsPaginator;
use App\Support\Jobs\SearchResultsPaginator;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class JobListService
{
    /**
     * Aggregated jobs index paginators from the current request query.
     *
     * @param Request $request The request.
     *
     * @return array{
     *     processing: SearchResultsPaginator,
     *     processed: SearchResultsPaginator,
     *     failed: SearchResultsPaginator,
     *     serviceFilterIds: list<int>,
     *     search: string
     * }
     */
    public static function buildAggregatedJobsIndexFromRequest(Request $request): array
    {
        $serviceFilterIds = ServiceFilterService::resolveServiceIds($request);
        $search = (string) $request->query('search', '');

        $servicesQuery = Service::enabled();

        if (! empty($serviceFilterIds)) {
            $servicesQuery->whereIn('id', $serviceFilterIds);
        }

        /** @var Collection<int, Service> $servicesWithApi */
        $servicesWithApi = $servicesQuery->get();

        $pageProcessing = \max(1, (int) $request->query('page_processing', 1));
        $pageProcessed = \max(1, (int) $request->query('page_processed', 1));
        $pageFailed = \max(1, (int) $request->query('page_failed', 1));

        $paginators = self::buildAggregatedStatusPaginators(
            $servicesWithApi,
            $search,
            $pageProcessing,
            $pageProcessed,
            $pageFailed,
            config('horizonhub.jobs_per_page'),
            $request->url(),
            $request->query(),
        );

        return [
            'processing' => $paginators['processing'],
            'processed' => $paginators['processed'],
            'failed' => $paginators['failed'],
            'serviceFilterIds' => $serviceFilterIds,
            'search' => $search,
        ];
    }

    /**
     * Build paginators for processing, processed, and failed job lists (aggregated across services).
     *
     * @param Collection<int, Service> $services
     * @param string $search The search.
     * @param int $pageProcessing The page processing.
     * @param int $pageProcessed The page processed.
     * @param int $pageFailed The page failed.
     * @param int $perPage The per page.
     * @param string $path The path.
     * @param array<string, mixed> $query The query.
     *
     * @return array{processing: SearchResultsPaginator, processed: SearchResultsPaginator, failed: SearchResultsPaginator}
     */
    public static function buildAggregatedStatusPaginators(
        Collection $services,
        string $search,
        int $pageProcessing,
        int $pageProcessed,
        int $pageFailed,
        int $perPage,
        string $path,
        array $query,
    ): array {
        $processing = self::private__collectAndSortJobsForServices($services, 'processing', $search);
        $processed = self::private__collectAndSortJobsForServices($services, 'processed', $search);
        $failed = self::private__collectAndSortJobsForServices($services, 'failed', $search);

        return [
            'processing' => self::private__makePaginator($processing, $perPage, $pageProcessing, $path, $query, 'page_processing'),
            'processed' => self::private__makePaginator($processed, $perPage, $pageProcessed, $path, $query, 'page_processed'),
            'failed' => self::private__makePaginator($failed, $perPage, $pageFailed, $path, $query, 'page_failed'),
        ];
    }

    /**
     * Fetch failed jobs from one or more services, apply filters, sort, and slice for HTTP pagination.
     *
     * @param Collection<int, Service> $services
     * @param string $search The search.
     * @param mixed $dateFrom The date from.
     * @param mixed $dateTo The date to.
     * @param int $page The page.
     * @param int $perPage The per page.
     *
     * @return array{rows: list<array<string, mixed>>, total: int, last_page: int}
     */
    public static function buildFailedJobsRetryModalPage(
        Collection $services,
        string $search,
        mixed $dateFrom,
        mixed $dateTo,
        int $page,
        int $perPage,
    ): array {
        $rows = self::private__buildRetryModalFailedRows($services, $search, $dateFrom, $dateTo);

        $total = \count($rows);
        $lastPage = $perPage > 0 ? (int) \max(1, (int) \ceil($total / $perPage)) : 1;
        $offset = ($page - 1) * $perPage;
        $pageRows = $perPage > 0 ? \array_slice($rows, $offset, $perPage) : $rows;

        $data = [];

        foreach ($pageRows as $row) {
            unset($row['failed_at']);
            $data[] = $row;
        }

        return [
            'rows' => $data,
            'total' => $total,
            'last_page' => $lastPage,
        ];
    }

    /**
     * Build paginators for a single service dashboard (same three sections).
     *
     * @param Service $service The service.
     * @param string $search The search.
     * @param int $pageProcessing The page processing.
     * @param int $pageProcessed The page processed.
     * @param int $pageFailed The page failed.
     * @param int $perPage The per page.
     * @param string $path The path.
     * @param array<string, mixed> $query The query.
     *
     * @return array{processing: SearchResultsPaginator, processed: SearchResultsPaginator, failed: SearchResultsPaginator}
     */
    public static function buildServiceStatusPaginators(
        Service $service,
        string $search,
        int $pageProcessing,
        int $pageProcessed,
        int $pageFailed,
        int $perPage,
        string $path,
        array $query,
    ): array {
        $merged = \collect();
        $merged->push($service);
        $processing = self::private__collectAndSortJobsForServices($merged, 'processing', $search);
        $processed = self::private__collectAndSortJobsForServices($merged, 'processed', $search);
        $failed = self::private__collectAndSortJobsForServices($merged, 'failed', $search);

        return [
            'processing' => self::private__makePaginator($processing, $perPage, $pageProcessing, $path, $query, 'page_processing'),
            'processed' => self::private__makePaginator($processed, $perPage, $pageProcessed, $path, $query, 'page_processed'),
            'failed' => self::private__makePaginator($failed, $perPage, $pageFailed, $path, $query, 'page_failed'),
        ];
    }

    /**
     * Get the API fetcher for a given status.
     *
     * @param Service $service The service.
     * @param string $status The status.
     *
     * @return callable(array<string, mixed>): array{success: bool, data?: array<string, mixed>}
     */
    private static function private__apiFetcherForStatus(Service $service, string $status): callable
    {
        return match ($status) {
            'processing' => fn (array $query): array => HorizonClientApiService::getPendingJobs($service, $query),
            'processed' => fn (array $query): array => HorizonClientApiService::getCompletedJobs($service, $query),
            'failed' => fn (array $query): array => HorizonClientApiService::getFailedJobs($service, $query),
            default => throw new \InvalidArgumentException("Unsupported job list status [$status]."),
        };
    }

    /**
     * Build filtered, sorted failed-job rows for the retry modal (before pagination).
     *
     * The search match cap never applies here: the batch retry action must act on
     * every matching failed job, so the whole window is always read.
     *
     * @param Collection<int, Service> $services The services.
     * @param string $search The search.
     * @param mixed $dateFrom The date from.
     * @param mixed $dateTo The date to.
     *
     * @return list<array<string, mixed>>
     */
    private static function private__buildRetryModalFailedRows(
        Collection $services,
        string $search,
        mixed $dateFrom,
        mixed $dateTo,
    ): array {
        $rows = [];

        $dateFromStr = \is_string($dateFrom) ? $dateFrom : null;
        $dateToStr = \is_string($dateTo) ? $dateTo : null;
        $dateFromCarbon = DatetimeBoundaryParser::parseLower($dateFromStr);
        $dateToCarbon = DatetimeBoundaryParser::parseUpper($dateToStr);

        foreach ($services as $service) {
            $fetch = JobsPaginator::fetchFiltered(
                fn (array $query): array => HorizonClientApiService::getFailedJobs($service, $query),
                $search,
            );

            foreach ($fetch['jobs'] as $job) {
                $jobUuid = (string) ($job['id'] ?? '');

                if (empty($jobUuid)) {
                    continue;
                }

                $failedAtCarbon = JobRuntime::parseJobTimestamp($job['failed_at'] ?? null);

                if ($dateFromCarbon !== null && $failedAtCarbon !== null && $failedAtCarbon->lt($dateFromCarbon)) {
                    continue;
                }

                if ($dateToCarbon !== null && $failedAtCarbon !== null && $failedAtCarbon->gt($dateToCarbon)) {
                    continue;
                }

                $rows[] = [
                    'uuid' => $jobUuid,
                    'service_id' => $service->id,
                    'service_name' => $service->name,
                    'queue' => $job['queue'] ?? null,
                    'name' => $job['name'] ?? ($job['displayName'] ?? $jobUuid),
                    'failed_at' => $failedAtCarbon,
                    'failed_at_formatted' => $failedAtCarbon?->format('Y-m-d H:i') ?? null,
                    'failed_at_iso' => $failedAtCarbon?->toIso8601String() ?? null,
                ];
            }
        }

        \usort($rows, static function (array $a, array $b): int {
            $aTime = $a['failed_at'];
            $bTime = $b['failed_at'];

            if (empty($aTime) && empty($bTime)) {
                return 0;
            }

            if (empty($aTime)) {
                return 1;
            }

            if (empty($bTime)) {
                return -1;
            }

            if ($aTime->eq($bTime)) {
                return 0;
            }

            return $aTime->lt($bTime) ? 1 : -1;
        });

        return $rows;
    }

    /**
     * Collect and sort jobs for one or more services.
     *
     * The search is matched against the raw Horizon payload while pages are
     * being read, so only matching jobs are mapped, sorted and collected. When
     * a search is active the pagination loop stops early once
     * `horizonhub.job_search_match_cap` matches have been collected per service.
     *
     * @param Collection<int, Service> $services The services.
     * @param 'processing'|'processed'|'failed' $status The status.
     * @param string $search The search.
     *
     * @return array{rows: Collection<int, object>, resultsMayBeTruncated: bool}
     */
    private static function private__collectAndSortJobsForServices(Collection $services, string $status, string $search): array
    {
        $merged = \collect();
        $resultsMayBeTruncated = false;
        // The cap only exists to bound searches; an unfiltered list is complete.
        $maxMatches = $search === '' ? null : config('horizonhub.job_search_match_cap');

        foreach ($services as $service) {
            $fetcher = self::private__apiFetcherForStatus($service, $status);
            $fetch = JobsPaginator::fetchFiltered($fetcher, $search, $maxMatches);
            $resultsMayBeTruncated = $resultsMayBeTruncated || ! $fetch['complete'];

            foreach ($fetch['jobs'] as $job) {
                $row = self::private__mapRawJobToListRow($job, $service, $status);

                if ($row === null) {
                    continue;
                }

                $merged->push($row);
            }
        }

        $sorted = $merged->sort(function (object $a, object $b) use ($status): int {
            $timeA = self::private__sortTimeForStatus($a, $status);
            $timeB = self::private__sortTimeForStatus($b, $status);

            if ($timeA === $timeB) {
                $sidA = $a->service->id ?? 0;
                $sidB = $b->service->id ?? 0;

                if ($sidA !== $sidB) {
                    return $sidA <=> $sidB;
                }

                return \strcmp((string) $a->uuid, (string) $b->uuid);
            }

            return $timeA < $timeB ? 1 : -1;
        })->values();

        return [
            'rows' => $sorted,
            'resultsMayBeTruncated' => $resultsMayBeTruncated,
        ];
    }

    /**
     * Make a paginator for a given collection of items.
     *
     * @param array{rows: Collection<int, object>, resultsMayBeTruncated: bool} $result The collected result.
     * @param int $perPage The per page.
     * @param int $page The page.
     * @param string $path The path.
     * @param array<string, mixed> $query The query.
     * @param string $pageName The page name.
     */
    private static function private__makePaginator(
        array $result,
        int $perPage,
        int $page,
        string $path,
        array $query,
        string $pageName,
    ): SearchResultsPaginator {
        $items = $result['rows'];
        $page = \max(1, $page);
        $total = $items->count();
        $options = ['path' => $path, 'query' => $query];

        if ($perPage <= 0) {
            $paginator = new SearchResultsPaginator($items, $total, \max(1, $total), 1, $options);
        } else {
            $paginator = new SearchResultsPaginator(
                $items->slice(($page - 1) * $perPage, $perPage)->values(),
                $total,
                $perPage,
                $page,
                $options,
            );
        }

        $paginator->setPageName($pageName);
        $paginator->setResultsMayBeTruncated($result['resultsMayBeTruncated']);

        return $paginator;
    }

    /**
     * Map a raw job to a list row.
     *
     * @param array<string, mixed> $job The job.
     * @param Service $service The service.
     * @param 'processing'|'processed'|'failed' $status The status.
     */
    private static function private__mapRawJobToListRow(array $job, Service $service, string $status): ?object
    {
        $uuid = (string) ($job['id'] ?? '');

        if ($uuid === '') {
            return null;
        }

        $queue = (string) ($job['queue'] ?? '');
        $name = (string) ($job['name'] ?? '');
        $payload = isset($job['payload']) && \is_array($job['payload']) ? $job['payload'] : [];
        $timing = JobRuntime::resolveJobTimingFields($job, $payload, $status);

        $attemptsRaw = $payload['attempts'] ?? null;
        $attempts = is_numeric($attemptsRaw) && $attemptsRaw >= 1 ? (int) $attemptsRaw : null;

        return (object) [
            'id' => $uuid,
            'job_uuid' => $uuid,
            'uuid' => $uuid,
            'queue' => $queue,
            'name' => $name,
            'status' => $status,
            'attempts' => $attempts,
            'queued_at' => $timing['queued_at'],
            'processed_at' => $timing['processed_at'],
            'failed_at' => $timing['failed_at'],
            'runtime' => $timing['runtime'],
            'available_at' => $timing['available_at'],
            'service' => $service,
        ];
    }

    /**
     * Get the timestamp for a given status.
     *
     * @param object $row The row.
     * @param 'processing'|'processed'|'failed' $status The status.
     */
    private static function private__sortTimeForStatus(object $row, string $status): float
    {
        $carbon = match ($status) {
            'processing' => $row->queued_at,
            'processed' => $row->processed_at ?? $row->queued_at,
            'failed' => $row->failed_at ?? $row->queued_at,
        };

        if ($carbon instanceof Carbon) {
            return (float) $carbon->getTimestamp() * 1000.0;
        }

        return 0.0;
    }
}
