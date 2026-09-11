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

        if ($services->isEmpty()) {
            return [];
        }

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

        if ($services->isEmpty()) {
            return [];
        }

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
        $response = HorizonClientApiService::getWorkload($service);
        $payload = ClientResponse::data($response);

        if (empty($payload)) {
            return [];
        }

        // TO-DEPURATE: data or workload?
        $data = $payload['data'] ?? $payload['workload'] ?? null;

        if (empty($data) || ! \is_array($data)) {
            if (isset($payload[0]) && \is_array($payload[0]) && isset($payload[0]['name'])) {
                $data = $payload;
            } else {
                return [];
            }
        }

        $rows = [];

        foreach ($data as $row) {
            if (! \is_array($row)) {
                continue;
            }

            $queueName = '';

            if (! empty($row['name'])) {
                $queueName = (string) $row['name'];
            }

            $queueName = QueueNameNormalizer::normalize($queueName) ?? $queueName;

            if (empty($queueName)) {
                continue;
            }

            // TO-DEPURATE: length, size, pending, jobs?
            $jobs = $row['length'] ?? $row['size'] ?? $row['pending'] ?? $row['jobs'] ?? 0;

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
                'jobs' => $jobs,
                'processes' => $processes,
                'wait' => $wait,
            ];
        }

        \usort($rows, static fn (array $a, array $b): int => \strcmp($a['queue'], $b['queue']));

        return $rows;
    }
}
