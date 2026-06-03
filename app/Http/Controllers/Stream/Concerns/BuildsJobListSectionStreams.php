<?php

namespace App\Http\Controllers\Stream\Concerns;

use App\Models\Service;
use Illuminate\Pagination\LengthAwarePaginator;

trait BuildsJobListSectionStreams
{
    /**
     * Turbo streams for the three job list section tbodies, badge counts, and pagination (no thead replace).
     *
     * @param array{processing: LengthAwarePaginator, processed: LengthAwarePaginator, failed: LengthAwarePaginator} $jobsIndex
     */
    protected function streamsForJobListSections(array $jobsIndex, string $resizablePrefix, bool $showServiceColumn, ?Service $pageService): string
    {
        $operations = [];

        foreach (['processing', 'processed', 'failed'] as $kind) {
            $paginator = $jobsIndex[$kind];
            $bodyKey = "$resizablePrefix-$kind";
            $operations[] = ['update', "turbo-tbody-$bodyKey", \view('horizon.jobs.partials.index.list-tbody-rows', [
                'kind' => $kind,
                'paginator' => $paginator,
                'showServiceColumn' => $showServiceColumn,
                'pageService' => $pageService,
            ])->render(), 'morph'];
            $operations[] = ['update', "job-count-$bodyKey", \e((string) $paginator->total()), null];
            $operations[] = ['update', "job-pagination-$bodyKey", \view('horizon.jobs.partials.index.list-section-pagination', [
                'paginator' => $paginator,
            ])->render(), 'morph'];
        }

        return $this->buildStreams($operations);
    }
}
