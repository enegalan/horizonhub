<?php

namespace App\Services\Metrics\Calculators;

use App\Enums\ServiceStatus;
use App\Models\Service;
use App\Services\Horizon\HorizonClientApiService;
use App\Support\Horizon\ClientResponse;
use App\Support\Horizon\MasterReader;
use App\Support\Queues\QueueNameNormalizer;

final class WorkloadMetricsCalculator extends AbstractMetricsCalculator
{
    /**
     * Get supervisors aggregated across services (optionally filtered by service).
     *
     * @param list<int> $serviceIds The service IDs.
     *
     * @return array<int, array{
     *     service_id: int,
     *     service: string,
     *     name: string,
     *     status: ServiceStatus,
     *     jobs: int,
     *     processes: int|null
     * }>
     */
    public function getSupervisorsData(array $serviceIds = []): array
    {
        $services = Service::getServices($serviceIds);

        $result = [];

        /** @var Service $service */
        foreach ($services as $service) {
            $workloadRows = $this->getWorkloadForService($service);
            $jobsByQueue = [];

            foreach ($workloadRows as $wr) {
                $jobsByQueue[$wr['queue']] = ($jobsByQueue[$wr['queue']] ?? 0) + $wr['jobs'];
            }

            $mastersData = ClientResponse::data(HorizonClientApiService::getMasters($service));

            if ($mastersData === null) {
                continue;
            }

            foreach (MasterReader::supervisorsFromMastersPayload($mastersData) as $supervisor) {
                $jobs = $this->private__sumJobsByQueueNames($supervisor['queueNames'], $jobsByQueue);

                $result[] = [
                    'service_id' => (int) $service->id,
                    'service' => (string) $service->name,
                    'name' => $supervisor['name'],
                    'status' => $service->status,
                    'jobs' => $jobs,
                    'processes' => $supervisor['processes'],
                ];
            }
        }

        return $result;
    }

    /**
     * Get current workload aggregated across services (optionally filtered by service).
     *
     * @param list<int> $serviceIds The service IDs.
     *
     * @return array<int, array{
     *     service_id: int,
     *     service: string,
     *     queue: string,
     *     jobs: int,
     *     processes: int|null,
     *     wait: float|null
     * }>
     */
    public function getWorkloadData(array $serviceIds = []): array
    {
        $services = Service::getServices($serviceIds, true);

        $result = [];

        /** @var Service $service */
        foreach ($services as $service) {
            $rows = $this->getWorkloadForService($service);

            foreach ($rows as $row) {
                $result[] = [
                    'service_id' => (int) $service->id,
                    'service' => (string) $service->name,
                    'queue' => $row['queue'],
                    'jobs' => $row['jobs'],
                    'processes' => $row['processes'],
                    'wait' => $row['wait'],
                ];
            }
        }

        return $result;
    }

    /**
     * Get the current workload rows for a single service.
     *
     * @param Service $service The service.
     *
     * @return array<int, array{queue: string, jobs: int, processes: int|null, wait: float|null}>
     */
    public function getWorkloadForService(Service $service): array
    {
        $payload = ClientResponse::data(HorizonClientApiService::getWorkload($service));

        if (! \is_array($payload) || empty($payload)) {
            return [];
        }

        $rows = [];

        foreach ($payload as $row) {
            if (! \is_array($row)) {
                continue;
            }

            $queueName = (string) ($row['name'] ?? '');

            if ($queueName === '') {
                continue;
            }

            $queueName = QueueNameNormalizer::normalize($queueName) ?? $queueName;

            $processes = null;

            if (isset($row['processes']) && \is_numeric($row['processes'])) {
                $processes = (int) $row['processes'];
            }

            $wait = null;

            if (isset($row['wait']) && \is_numeric($row['wait'])) {
                $wait = (float) $row['wait'];
            }

            $rows[] = [
                'queue' => $queueName,
                'jobs' => (int) ($row['length'] ?? 0),
                'processes' => $processes,
                'wait' => $wait,
            ];
        }

        \usort($rows, static fn (array $a, array $b): int => \strcmp($a['queue'], $b['queue']));

        return $rows;
    }
}
