<?php

namespace App\Http\Controllers\Stream\Concerns;

use App\Enums\JobSection;
use App\Models\Service;
use Illuminate\Pagination\LengthAwarePaginator;

trait BuildsJobListSectionStreams
{
    /**
     * Turbo streams for the job list section tbodies, badge counts, and pagination (no thead replace).
     *
     * @param array<string, LengthAwarePaginator> $jobsIndex keyed by JobSection value
     */
    protected function streamsForJobListSections(array $jobsIndex, string $resizablePrefix, bool $showServiceColumn, ?Service $pageService): string
    {
        $operations = [];

        foreach (JobSection::cases() as $section) {
            $paginator = $jobsIndex[$section->value];
            $bodyKey = "$resizablePrefix-{$section->value}";
            $operations[] = ['update', "tbody-$bodyKey", \view('horizon.jobs.partials.index.list-tbody-rows', [
                'section' => $section,
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
