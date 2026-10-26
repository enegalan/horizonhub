<?php

namespace App\Http\Controllers\Stream\Concerns;

use App\Models\Service;
use App\Services\Horizon\HorizonClientApiService;
use App\Services\Services\ServiceFilterService;
use App\Support\Horizon\ClientResponse;
use App\Support\Horizon\MasterReader;

trait BuildsInstanceStreams
{
    /**
     * Build the instances index streams.
     *
     * @param string $query The query.
     */
    protected function buildInstances(string $query): string
    {
        $services = Service::getServices(
            ServiceFilterService::resolveServiceIdsFromQuery($query),
            true,
            true,
        );
        $masterGroups = [];
        $supervisors = 0;
        $processes = 0;

        /** @var Service $service */
        foreach ($services as $service) {
            $mastersData = ClientResponse::data(HorizonClientApiService::getMasters($service));

            if ($mastersData === null) {
                continue;
            }

            foreach (MasterReader::groupsFromMastersPayload($mastersData) as $group) {
                foreach ($group['supervisors'] as $supervisor) {
                    $supervisors++;
                    $processes += (int) ($supervisor['processes'] ?? 0);
                }

                $masterGroups[] = [
                    ...$group,
                    'service_id' => (int) $service->id,
                    'service' => (string) $service->name,
                ];
            }
        }

        $statsHtml = \view('pages.instances.partials.index.stats', [
            'masters' => \count($masterGroups),
            'processes' => $processes,
            'services' => $services->count(),
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
