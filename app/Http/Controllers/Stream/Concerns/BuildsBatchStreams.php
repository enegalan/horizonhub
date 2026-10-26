<?php

namespace App\Http\Controllers\Stream\Concerns;

use App\Models\Service;
use App\Services\Horizon\HorizonClientApiService;
use App\Services\Services\ServiceFilterService;
use App\Support\ArrayFormatter;
use App\Support\Horizon\ClientResponse;
use App\Support\Jobs\JobRuntime;
use App\Support\NumberFormatter;
use App\Support\StringFormatter;
use Carbon\Carbon;

trait BuildsBatchStreams
{
    /**
     * Build the batches index streams.
     *
     * Horizon returns a limited number of recent batches per service. Search
     * is forwarded upstream so older batches can still be found by name or ID.
     *
     * @param string $query The query.
     */
    protected function buildBatches(string $query): string
    {
        $serviceFilterIds = ServiceFilterService::resolveServiceIdsFromQuery($query);
        $search = ServiceFilterService::searchFromQuery($query);
        $upstreamQuery = $search !== '' ? ['query' => $search] : [];
        $rows = [];
        $mayBeTruncated = false;
        $limit = (int) config('horizonhub.batches_recent_limit');

        /** @var Service $service */
        foreach (Service::getServices($serviceFilterIds, true, true) as $service) {
            $data = ClientResponse::data(HorizonClientApiService::getBatches($service, $upstreamQuery));

            if ($data === null) {
                continue;
            }

            $batches = $this->private__batchRows($data);
            $mayBeTruncated = $mayBeTruncated || \count($batches) >= $limit;

            foreach ($batches as $batch) {
                $rows[] = [
                    ...$batch,
                    'service_id' => (int) $service->id,
                    'service' => $service,
                    'service_name' => (string) $service->name,
                ];
            }
        }

        \usort($rows, static function (array $left, array $right): int {
            $leftTs = ($left['created_at'] instanceof \DateTimeInterface) ? $left['created_at']->getTimestamp() : 0;
            $rightTs = ($right['created_at'] instanceof \DateTimeInterface) ? $right['created_at']->getTimestamp() : 0;

            return $rightTs <=> $leftTs ?: \strcmp($right['id'], $left['id']);
        });

        $active = 0;
        $failed = 0;

        foreach ($rows as $row) {
            $active += $row['status'] === 'pending' ? 1 : 0;
            $failed += $row['failed_jobs'] > 0 ? 1 : 0;
        }

        $statsHtml = \view('pages.batches.partials.index.stats', [
            'activeCount' => $active,
            'batchCount' => \count($rows),
            'failedCount' => $failed,
            'mayBeTruncated' => $mayBeTruncated,
        ])->render();

        $tbodyHtml = \view('pages.batches.partials.index.tbody', [
            'batches' => $rows,
            'defer' => false,
            'search' => $search,
        ])->render();

        return $this->buildStreams([
            ['update', 'horizon-batch-stats', $statsHtml, 'morph'],
            ['update', 'tbody-horizon-batch-list', $tbodyHtml, 'morph'],
        ]);
    }

    /**
     * Build one service-scoped batch detail stream.
     */
    protected function buildBatchShow(Service $service, string $batch): string
    {
        $detail = ['batch' => null, 'failedJobs' => []];

        if ($service->enabled) {
            $data = ClientResponse::data(HorizonClientApiService::getBatch($service, $batch));

            if ($data !== null) {
                $detail = $this->private__batchDetail($data);
            }
        }

        $html = \view('pages.batches.partials.show.body', [
            'batch' => $detail['batch'],
            'batchId' => $batch,
            'failedJobs' => $detail['failedJobs'],
            'service' => $service,
        ])->render();

        return $this->buildStreams([
            ['update', 'batch-show-body', $html, 'morph'],
        ]);
    }

    /**
     * Read one batch and its failed jobs from the Horizon API payload.
     *
     * @param array<mixed> $payload The decoded batch response.
     *
     * @return array{batch: array<string, mixed>|null, failedJobs: list<array<string, mixed>>}
     */
    private function private__batchDetail(array $payload): array
    {
        return [
            'batch' => $this->private__normalizeBatch($payload['batch'] ?? null),
            'failedJobs' => $this->private__failedJobs($payload['failedJobs'] ?? null),
        ];
    }

    /**
     * Read and normalize the batches returned by the Horizon API.
     *
     * @param array<mixed> $payload The decoded batches response.
     *
     * @return list<array<string, mixed>>
     */
    private function private__batchRows(array $payload): array
    {
        $batches = $payload['batches'] ?? $payload;

        if (! \is_array($batches)) {
            return [];
        }

        $rows = [];

        foreach ($batches as $batch) {
            $row = $this->private__normalizeBatch($batch);

            if ($row !== null) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * Normalize the failed jobs attached to a batch.
     *
     * @return list<array<string, mixed>>
     */
    private function private__failedJobs(mixed $value): array
    {
        if (! \is_array($value)) {
            return [];
        }

        $jobs = [];

        foreach ($value as $job) {
            if (! \is_array($job)) {
                continue;
            }

            $id = StringFormatter::trim($job['id'] ?? '');

            if ($id === '') {
                continue;
            }

            $reservedAt = JobRuntime::parseJobTimestamp($job['reserved_at'] ?? null);
            $failedAt = JobRuntime::parseJobTimestamp($job['failed_at'] ?? null);

            $jobs[] = [
                'id' => $id,
                'name' => StringFormatter::trim($job['name'] ?? ''),
                'queue' => StringFormatter::trim($job['queue'] ?? ''),
                'failed_at' => $failedAt,
                'reserved_at' => $reservedAt,
                'runtime' => JobRuntime::getFormattedRuntime(
                    JobRuntime::getRuntimeSeconds(
                        NumberFormatter::floatOrNull($job['runtime'] ?? null),
                        $reservedAt,
                        null,
                        $failedAt,
                    ),
                ),
            ];
        }

        return $jobs;
    }

    /**
     * Normalize one batch payload.
     *
     * @param array<string, mixed>|null $batch The raw batch.
     *
     * @return array<string, mixed>|null
     */
    private function private__normalizeBatch(?array $batch): ?array
    {
        if (! \is_array($batch)) {
            return null;
        }

        $id = StringFormatter::trim($batch['id'] ?? '');

        if ($id === '') {
            return null;
        }

        $totalJobs = NumberFormatter::nonNegativeInt($batch['totalJobs'] ?? null);
        $pendingJobs = NumberFormatter::nonNegativeInt($batch['pendingJobs'] ?? null);
        $failedJobs = NumberFormatter::nonNegativeInt($batch['failedJobs'] ?? null);
        $processedJobs = NumberFormatter::intOrNull($batch['processedJobs'] ?? null);
        $processedJobs = $processedJobs !== null
            ? \max(0, $processedJobs)
            : \max(0, $totalJobs - $pendingJobs);
        $createdAt = JobRuntime::parseJobTimestamp($batch['createdAt'] ?? null);
        $finishedAt = JobRuntime::parseJobTimestamp($batch['finishedAt'] ?? null);
        $cancelledAt = JobRuntime::parseJobTimestamp($batch['cancelledAt'] ?? null);
        $progressRaw = NumberFormatter::floatOrNull($batch['progress'] ?? null);
        $progress = $progressRaw !== null
            ? (int) \round($progressRaw)
            : ($totalJobs > 0 ? (int) \round(($processedJobs / $totalJobs) * 100) : 0);

        return [
            'id' => $id,
            'name' => \trim((string) ($batch['name'] ?? '')),
            'total_jobs' => $totalJobs,
            'pending_jobs' => $pendingJobs,
            'processed_jobs' => $processedJobs,
            'failed_jobs' => $failedJobs,
            'progress' => \max(0, \min(100, $progress)),
            'status' => $this->private__status($pendingJobs, $failedJobs, $finishedAt, $cancelledAt),
            'options' => ArrayFormatter::orEmpty($batch['options'] ?? null),
            'created_at' => $createdAt,
            'finished_at' => $finishedAt,
            'cancelled_at' => $cancelledAt,
        ];
    }

    /**
     * Derive the batch state shown in the UI.
     *
     * @return 'cancelled'|'failed'|'finished'|'pending'
     */
    private function private__status(
        int $pendingJobs,
        int $failedJobs,
        ?Carbon $finishedAt,
        ?Carbon $cancelledAt,
    ): string {
        if ($cancelledAt !== null) {
            return 'cancelled';
        }

        if ($finishedAt !== null || $pendingJobs === 0) {
            return 'finished';
        }

        return $failedJobs > 0 ? 'failed' : 'pending';
    }
}
