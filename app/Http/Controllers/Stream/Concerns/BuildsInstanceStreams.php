<?php

namespace App\Http\Controllers\Stream\Concerns;

use App\Services\Services\ServiceFilterService;

trait BuildsInstanceStreams
{
    /**
     * Build the instances index streams.
     *
     * @param string $query The query.
     */
    protected function buildInstances(string $query): string
    {
        $overview = $this->queueOverview->masterGroups(
            ServiceFilterService::resolveServiceIdsFromQuery($query),
        );
        $masterGroups = $overview['masterGroups'];
        $supervisors = 0;
        $processes = 0;

        foreach ($masterGroups as $group) {
            foreach ($group['supervisors'] as $supervisor) {
                $supervisors++;
                $processes += (int) ($supervisor['processes'] ?? 0);
            }
        }

        $statsHtml = \view('pages.instances.partials.index.stats', [
            'masters' => \count($masterGroups),
            'processes' => $processes,
            'services' => $overview['services']->count(),
            'supervisors' => $supervisors,
        ])->render();

        $groupsHtml = \view('pages.partials.master-groups', [
            'emptyDescription' => 'No masters were reported by the connected Horizon services.',
            'emptyTitle' => 'No instances',
            'masterGroups' => $masterGroups,
            'showServiceLink' => true,
        ])->render();

        return $this->buildStreams([
            ['update', 'instances-stats', $statsHtml, 'morph'],
            ['update', 'instances-content', $groupsHtml, 'morph'],
        ]);
    }
}
