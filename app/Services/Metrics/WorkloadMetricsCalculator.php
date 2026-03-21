<?php

namespace App\Services\Metrics;

use App\Models\Service;
use App\Support\Horizon\QueueNameNormalizer;

class WorkloadMetricsCalculator extends HorizonMetricsComputation
{
    /**
     * Get the current workload rows for a single service.
     *
     * @return array<int, array{queue: string, jobs: int, processes: int|null, wait: float|null}>
     */
    public function getWorkloadForService(Service $service): array
    {
        if (! $service->base_url) {
            return [];
        }

        $response = $this->horizonApi->getWorkload($service);
        $payload = $response['data'] ?? null;

        if (! ($response['success'] ?? false) || $payload === null) {
            return [];
        }

        $data = $payload['data'] ?? $payload['workload'] ?? null;
        if ($data === null || ! \is_array($data)) {
            if (\is_array($payload) && isset($payload[0]) && \is_array($payload[0]) && isset($payload[0]['name'])) {
                $data = $payload;
            } else {
                return [];
            }
        }
        if ($data === []) {
            return [];
        }

        $rows = [];
        foreach ($data as $row) {
            if (! \is_array($row)) {
                continue;
            }

            $queueName = '';
            if (isset($row['name']) && (string) $row['name'] !== '') {
                $queueName = (string) $row['name'];
            }

            $queueName = QueueNameNormalizer::normalize($queueName) ?? $queueName;
            if ($queueName === '') {
                continue;
            }

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

    /**
     * Get supervisors aggregated across services (optionally filtered by service).
     *
     * @return array<int, array{
     *     service_id: int,
     *     service: string,
     *     name: string,
     *     status: string,
     *     jobs: int,
     *     processes: int|null
     * }>
     */
    public function getSupervisorsData(array $serviceScope = []): array
    {
        $services = $this->private__getServicesForMetrics($serviceScope);
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

            $mastersResponse = $this->horizonApi->getMasters($service);
            $mastersData = $mastersResponse['data'] ?? null;

            if (! ($mastersResponse['success'] ?? false) || ! \is_array($mastersData)) {
                continue;
            }

            foreach ($mastersData as $master) {
                if (! \is_array($master)) {
                    continue;
                }

                $supervisors = $master['supervisors'] ?? null;
                if (! \is_array($supervisors)) {
                    continue;
                }

                foreach ($supervisors as $supervisor) {
                    if (! \is_array($supervisor)) {
                        continue;
                    }

                    $name = isset($supervisor['name']) ? (string) $supervisor['name'] : '';
                    if ($name === '') {
                        continue;
                    }

                    $processes = null;
                    if (isset($supervisor['processes']) && \is_array($supervisor['processes'])) {
                        $sum = 0;
                        foreach ($supervisor['processes'] as $value) {
                            if (\is_numeric($value)) {
                                $sum += (int) $value;
                            }
                        }
                        $processes = $sum;
                    }

                    $options = isset($supervisor['options']) && \is_array($supervisor['options']) ? $supervisor['options'] : [];
                    $queues = $this->private__extractQueuesFromSupervisorOptions($options);
                    $jobs = $this->private__sumJobsByQueueNames($queues, $jobsByQueue);

                    $result[] = [
                        'service_id' => (int) $service->id,
                        'service' => (string) $service->name,
                        'name' => $name,
                        'status' => $service->status,
                        'jobs' => $jobs,
                        'processes' => $processes,
                    ];
                }
            }
        }

        return $result;
    }

    /**
     * Get current workload aggregated across services (optionally filtered by service).
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
    public function getWorkloadData(array $serviceScope = []): array
    {
        $services = $this->private__getServicesForMetrics($serviceScope, true);
        if ($services->isEmpty()) {
            return [];
        }

        $result = [];

        /** @var Service $service */
        foreach ($services as $service) {
            $rows = $this->getWorkloadForService($service);
            if ($rows === []) {
                $rows = $this->private__getWorkloadFallbackFromMasters($service);
            }

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
}
