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
            'supervisor' => $this->private__normalizeSupervisor($supervisorData),
            'supervisorName' => $supervisor,
        ])->render();

        return $this->buildStreams([
            ['update', 'supervisor-show-body', $html, 'morph'],
        ]);
    }

    /**
     * Normalize supervisor payload fields used by the show view.
     *
     * @param array<string, mixed>|null $supervisor
     *
     * @return array<string, mixed>|null
     */
    private function private__normalizeSupervisor(?array $supervisor): ?array
    {
        if ($supervisor === null) {
            return null;
        }

        $backoff = $supervisor['backoff'] ?? null;

        if (\is_array($backoff)) {
            $backoff = \implode(', ', \array_map(static fn ($value): string => (string) $value, $backoff));
        }

        $supervisor['queueNames'] = \array_values(\array_map(
            'strval',
            \is_array($supervisor['queueNames'] ?? null) ? $supervisor['queueNames'] : [],
        ));
        $supervisor['warnings'] = \array_values(\array_map(
            'strval',
            \is_array($supervisor['warnings'] ?? null) ? $supervisor['warnings'] : [],
        ));
        $supervisor['processesByQueue'] = \is_array($supervisor['processesByQueue'] ?? null)
            ? $supervisor['processesByQueue']
            : [];
        $supervisor['options'] = \is_array($supervisor['options'] ?? null)
            ? $supervisor['options']
            : [];
        $supervisor['backoff'] = $backoff;

        return $supervisor;
    }
}
