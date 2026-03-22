<?php

namespace App\Services\Horizon;

use App\Models\Service;
use App\Support\ConfigHelper;
use App\Support\DatetimeBoundaryParser;
use App\Support\Horizon\JobRuntimeHelper;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class HorizonJobListService
{
    /**
     * The Horizon API proxy service.
     */
    private HorizonApiProxyService $horizonApi;

    /**
     * The constructor.
     */
    public function __construct(HorizonApiProxyService $horizonApi)
    {
        $this->horizonApi = $horizonApi;
    }

    /**
     * Build paginators for processing, processed, and failed job lists (aggregated across services).
     *
     * @param  Collection<int, Service>  $services
     * @param  array<string, mixed>  $query
     * @return array{processing: LengthAwarePaginator, processed: LengthAwarePaginator, failed: LengthAwarePaginator}
     */
    public function buildAggregatedStatusPaginators(
        Collection $services,
        string $search,
        int $pageProcessing,
        int $pageProcessed,
        int $pageFailed,
        int $perPage,
        string $path,
        array $query,
    ): array {
        $jobsProcessing = $this->private__collectAndSortJobsAcrossServices($services, 'processing', $search);
        $jobsProcessed = $this->private__collectAndSortJobsAcrossServices($services, 'processed', $search);
        $jobsFailed = $this->private__collectAndSortJobsAcrossServices($services, 'failed', $search);

        return [
            'processing' => $this->private__makePaginator($jobsProcessing, $perPage, $pageProcessing, $path, $query, 'page_processing'),
            'processed' => $this->private__makePaginator($jobsProcessed, $perPage, $pageProcessed, $path, $query, 'page_processed'),
            'failed' => $this->private__makePaginator($jobsFailed, $perPage, $pageFailed, $path, $query, 'page_failed'),
        ];
    }

    /**
     * Build paginators for a single service dashboard (same three sections).
     *
     * @param  array<string, mixed>  $query
     * @return array{processing: LengthAwarePaginator, processed: LengthAwarePaginator, failed: LengthAwarePaginator}
     */
    public function buildServiceStatusPaginators(
        Service $service,
        string $search,
        int $pageProcessing,
        int $pageProcessed,
        int $pageFailed,
        int $perPage,
        string $path,
        array $query,
    ): array {
        $jobsProcessing = $this->private__collectAndSortJobsForService($service, 'processing', $search);
        $jobsProcessed = $this->private__collectAndSortJobsForService($service, 'processed', $search);
        $jobsFailed = $this->private__collectAndSortJobsForService($service, 'failed', $search);

        return [
            'processing' => $this->private__makePaginator($jobsProcessing, $perPage, $pageProcessing, $path, $query, 'page_processing'),
            'processed' => $this->private__makePaginator($jobsProcessed, $perPage, $pageProcessed, $path, $query, 'page_processed'),
            'failed' => $this->private__makePaginator($jobsFailed, $perPage, $pageFailed, $path, $query, 'page_failed'),
        ];
    }

    /**
     * Fetch failed jobs from one or more services, apply filters, sort, and slice for HTTP pagination.
     *
     * @param  Collection<int, Service>  $services
     * @return array{rows: list<array<string, mixed>>, total: int, last_page: int}
     */
    public function buildFailedJobsRetryModalPage(
        Collection $services,
        string $search,
        mixed $dateFrom,
        mixed $dateTo,
        int $page,
        int $perPage,
    ): array {
        $rows = [];

        $dateFromStr = \is_string($dateFrom) ? $dateFrom : null;
        $dateToStr = \is_string($dateTo) ? $dateTo : null;
        $dateFromCarbon = DatetimeBoundaryParser::parseLower($dateFromStr);
        $dateToCarbon = DatetimeBoundaryParser::parseUpper($dateToStr);

        foreach ($services as $service) {
            $rawJobs = $this->private__fetchAllJobsForService(
                fn (array $query): array => $this->horizonApi->getFailedJobs($service, $query),
            );
            foreach ($rawJobs as $job) {
                if (! \is_array($job)) {
                    continue;
                }
                $jobUuid = (string) ($job['id'] ?? '');
                if (empty($jobUuid)) {
                    continue;
                }

                $queue = (string) ($job['queue'] ?? '');
                $name = (string) ($job['name'] ?? '');

                if (! empty($search)) {
                    $haystack = "$queue $name $jobUuid";
                    if (\stripos($haystack, $search) === false) {
                        continue;
                    }
                }

                $failedAtRaw = $job['failed_at'] ?? null;
                $failedAtCarbon = null;
                if (\is_string($failedAtRaw) && $failedAtRaw !== '') {
                    try {
                        $failedAtCarbon = new Carbon($failedAtRaw);
                    } catch (\Throwable) {
                        $failedAtCarbon = null;
                    }
                }

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

            if ($aTime === null && $bTime === null) {
                return 0;
            }
            if ($aTime === null) {
                return 1;
            }
            if ($bTime === null) {
                return -1;
            }

            if ($aTime->eq($bTime)) {
                return 0;
            }

            return $aTime->lt($bTime) ? 1 : -1;
        });

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
     * Collect and sort jobs across multiple services.
     *
     * @param  Collection<int, Service>  $services
     * @param  'processing'|'processed'|'failed'  $status
     * @return Collection<int, object>
     */
    private function private__collectAndSortJobsAcrossServices(Collection $services, string $status, string $search): Collection
    {
        $merged = \collect();
        foreach ($services as $service) {
            $fetcher = $this->private__apiFetcherForStatus($service, $status);
            $rawJobs = $this->private__fetchAllJobsForService($fetcher);
            foreach ($rawJobs as $job) {
                $row = $this->private__mapRawJobToListRow(\is_array($job) ? $job : [], $service, $status);
                if ($row === null) {
                    continue;
                }
                if (! $this->private__matchesSearch($row, $search)) {
                    continue;
                }
                $merged->push($row);
            }
        }

        return $this->private__sortJobRows($merged, $status)->values();
    }

    /**
     * Collect and sort jobs for a single service.
     *
     * @param  'processing'|'processed'|'failed'  $status
     * @return Collection<int, object>
     */
    private function private__collectAndSortJobsForService(Service $service, string $status, string $search): Collection
    {
        $fetcher = $this->private__apiFetcherForStatus($service, $status);
        $rawJobs = $this->private__fetchAllJobsForService($fetcher);
        $merged = \collect();
        foreach ($rawJobs as $job) {
            $row = $this->private__mapRawJobToListRow(\is_array($job) ? $job : [], $service, $status);
            if ($row === null) {
                continue;
            }
            if (! $this->private__matchesSearch($row, $search)) {
                continue;
            }
            $merged->push($row);
        }

        return $this->private__sortJobRows($merged, $status)->values();
    }

    /**
     * Fetch all jobs for a single service.
     *
     * @param  callable(array<string, mixed>): array{success: bool, data?: array<string, mixed>}  $fetcher
     * @return list<mixed>
     */
    private function private__fetchAllJobsForService(callable $fetcher): array
    {
        $maxPages = ConfigHelper::getIntWithMin('horizonhub.max_horizon_pages', 1);

        $jobsPerRequest = ConfigHelper::getIntWithMin('horizonhub.horizon_api_job_list_page_size', 1);
        $accumulated = [];
        $startingAt = -1;

        for ($pageIdx = 0; $pageIdx < $maxPages; $pageIdx++) {
            $response = $fetcher([
                'starting_at' => $startingAt,
                'limit' => $jobsPerRequest,
            ]);

            if (! ($response['success'] ?? false)) {
                break;
            }

            $data = $response['data'] ?? null;
            if (! \is_array($data)) {
                break;
            }

            $batch = $data['jobs'] ?? [];
            if (! \is_array($batch) || $batch === []) {
                break;
            }

            foreach ($batch as $job) {
                $accumulated[] = $job;
            }

            if (\count($batch) < $jobsPerRequest) {
                break;
            }

            $startingAt = $this->private__nextStartingAt($startingAt, $batch);
        }

        return $accumulated;
    }

    /**
     * Get the API fetcher for a given status.
     *
     * @param  'processing'|'processed'|'failed'  $status
     * @return callable(array<string, mixed>): array{success: bool, data?: array<string, mixed>}
     */
    private function private__apiFetcherForStatus(Service $service, string $status): callable
    {
        return match ($status) {
            'processing' => fn (array $query): array => $this->horizonApi->getPendingJobs($service, $query),
            'processed' => fn (array $query): array => $this->horizonApi->getCompletedJobs($service, $query),
            'failed' => fn (array $query): array => $this->horizonApi->getFailedJobs($service, $query),
        };
    }

    /**
     * Get the next starting at value for the next batch.
     *
     * @param  list<mixed>  $batch
     */
    private function private__nextStartingAt(int $startingAt, array $batch): int
    {
        $last = $batch[\array_key_last($batch)];
        if (\is_array($last) && isset($last['index'])) {
            return (int) $last['index'];
        }

        return \max(0, $startingAt + 1) + \count($batch) - 1;
    }

    /**
     * Map a raw job to a list row.
     *
     * @param  array<string, mixed>  $job
     * @param  'processing'|'processed'|'failed'  $status
     */
    private function private__mapRawJobToListRow(array $job, Service $service, string $status): ?object
    {
        $uuid = (string) ($job['id'] ?? '');
        if ($uuid === '') {
            return null;
        }

        $queue = (string) ($job['queue'] ?? '');
        $name = (string) ($job['name'] ?? '');
        $payload = $job['payload'] ?? [];
        if (! \is_array($payload)) {
            $payload = [];
        }

        $pushedAt = $job['pushedAt'] ?? $payload['pushedAt'] ?? null;
        $completedAt = $job['completed_at'] ?? null;
        $failedAtRaw = $job['failed_at'] ?? null;

        $queuedAt = $this->private__parseJobTimestamp($pushedAt);
        $processedAt = $this->private__parseJobTimestamp($completedAt);
        $failedAt = $this->private__parseJobTimestamp($failedAtRaw);
        JobRuntimeHelper::normalizeStatusDates($status, $processedAt, $failedAt);

        $attemptsRaw = $job['attempts'] ?? $payload['attempts'] ?? null;
        $attempts = $attemptsRaw !== null && $attemptsRaw !== '' ? (int) $attemptsRaw : null;
        if ($attempts !== null && $attempts < 1) {
            $attempts = null;
        }

        $runtime = JobRuntimeHelper::getFormattedRuntime(
            JobRuntimeHelper::getRuntimeSeconds(
                isset($job['runtime']) && \is_numeric($job['runtime']) ? (float) $job['runtime'] : null,
                $queuedAt,
                $processedAt,
                $failedAt
            )
        );

        return (object) [
            'id' => $uuid,
            'job_uuid' => $uuid,
            'uuid' => $uuid,
            'queue' => $queue,
            'name' => $name,
            'status' => $status,
            'attempts' => $attempts,
            'queued_at' => $queuedAt,
            'processed_at' => $processedAt,
            'failed_at' => $failedAt,
            'runtime' => $runtime,
            'service' => $service,
        ];
    }

    /**
     * Check if a row matches the search.
     */
    private function private__matchesSearch(object $row, string $search): bool
    {
        if ($search === '') {
            return true;
        }

        $haystack = $row->queue.' '.$row->name.' '.$row->uuid;

        return \stripos($haystack, $search) !== false;
    }

    /**
     * Sort job rows by time for a given status.
     *
     * @param  Collection<int, object>  $rows
     * @param  'processing'|'processed'|'failed'  $status
     * @return Collection<int, object>
     */
    private function private__sortJobRows(Collection $rows, string $status): Collection
    {
        return $rows->sort(function (object $a, object $b) use ($status): int {
            $timeA = $this->private__sortTimeForStatus($a, $status);
            $timeB = $this->private__sortTimeForStatus($b, $status);

            if ($timeA === $timeB) {
                $sidA = $a->service->id ?? 0;
                $sidB = $b->service->id ?? 0;
                if ($sidA !== $sidB) {
                    return $sidA <=> $sidB;
                }

                return \strcmp((string) $a->uuid, (string) $b->uuid);
            }

            return $timeA < $timeB ? 1 : -1;
        });
    }

    /**
     * Get the timestamp for a given status.
     *
     * @param  'processing'|'processed'|'failed'  $status
     */
    private function private__sortTimeForStatus(object $row, string $status): float
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

    /**
     * Make a paginator for a given collection of items.
     *
     * @param  Collection<int, object>  $items
     * @param  array<string, mixed>  $query
     */
    private function private__makePaginator(
        Collection $items,
        int $perPage,
        int $page,
        string $path,
        array $query,
        string $pageName,
    ): LengthAwarePaginator {
        $page = \max(1, $page);
        $total = $items->count();

        if ($perPage <= 0) {
            $paginator = new LengthAwarePaginator(
                $items,
                $total,
                \max(1, $total > 0 ? $total : 1),
                1,
                ['path' => $path, 'query' => $query]
            );
            $paginator->setPageName($pageName);

            return $paginator;
        }

        $offset = ($page - 1) * $perPage;
        $slice = $items->slice($offset, $perPage)->values();

        $paginator = new LengthAwarePaginator(
            $slice,
            $total,
            $perPage,
            $page,
            ['path' => $path, 'query' => $query]
        );
        $paginator->setPageName($pageName);

        return $paginator;
    }

    /**
     * Parse a job timestamp.
     *
     * @param  mixed  $value
     */
    private function private__parseJobTimestamp($value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (\is_numeric($value)) {
            $seconds = (float) $value;

            return Carbon::createFromTimestampMs((int) \round($seconds * 1000));
        }

        return Carbon::parse($value);
    }
}
