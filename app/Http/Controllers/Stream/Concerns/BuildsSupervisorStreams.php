<?php

namespace App\Http\Controllers\Stream\Concerns;

use App\Models\Service;
use App\Services\Horizon\HorizonClientApiService;
use App\Support\Horizon\ClientResponse;
use App\Support\Horizon\MasterReader;
use App\Support\PathBuilder;

trait BuildsSupervisorStreams
{
    /**
     * Build one supervisor detail stream, nested under its service.
     */
    protected function buildSupervisorShow(Service $service, string $supervisor): string
    {
        $master = null;
        $supervisorData = null;

        if ($service->enabled) {
            $mastersData = ClientResponse::data(HorizonClientApiService::getMasters($service));
            $match = $mastersData === null
                ? null
                : MasterReader::findSupervisor(MasterReader::groupsFromMastersPayload($mastersData), $supervisor);
            $master = $match['master'] ?? null;
            $supervisorData = $match['supervisor'] ?? null;
        }

        $html = \view('pages.supervisors.partials.show.body', [
            'dashboardUrl' => PathBuilder::dashboard($service),
            'master' => $master,
            'service' => $service,
            'supervisor' => $supervisorData,
            'supervisorName' => $supervisor,
        ])->render();

        return $this->buildStreams([
            ['update', 'supervisor-show-body', $html, 'morph'],
        ]);
    }
}
