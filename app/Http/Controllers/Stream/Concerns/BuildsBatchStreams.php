<?php

namespace App\Http\Controllers\Stream\Concerns;

use App\Models\Service;
use App\Services\Horizon\HorizonClientApiService;
use App\Services\Services\ServiceFilterService;
use App\Support\Batches\BatchPayloadReader;
use App\Support\Horizon\ClientResponse;

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

            $batches = BatchPayloadReader::rows($data);
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
                $detail = BatchPayloadReader::detail($data);
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
}
