<?php

namespace App\Support\HorizonHub\Mock;

final class HorizonFixtureBuilder
{
    /**
     * @param array<string, mixed> $catalog
     */
    public function __construct(
        private readonly array $catalog,
        private readonly int $jobsPerStatus,
    ) {}

    public static function jobServiceIndex(array $horizon): array
    {
        $index = [];

        foreach ($horizon as $serviceId => $fixture) {
            $serviceId = (int) $serviceId;

            foreach ($fixture['jobs'] ?? [] as $uuid => $job) {
                if (\is_string($uuid) && $uuid !== '') {
                    $index[$uuid] = $serviceId;
                }
            }

            foreach (['pending_jobs', 'completed_jobs', 'failed_jobs'] as $listKey) {
                $jobs = $fixture[$listKey]['jobs'] ?? [];

                if (! \is_array($jobs)) {
                    continue;
                }

                foreach ($jobs as $job) {
                    if (! \is_array($job)) {
                        continue;
                    }

                    $uuid = (string) ($job['id'] ?? '');

                    if ($uuid !== '') {
                        $index[$uuid] = $serviceId;
                    }
                }
            }
        }

        return $index;
    }

    public function build(): array
    {
        $pinnedJobUuid = (string) ($this->catalog['pinned_job_uuid'] ?? MockDataset::PINNED_JOB_UUID);
        $since = now()->subHours(6)->getTimestamp();
        $built = [];

        foreach ($this->catalog['services'] ?? [] as $service) {
            $serviceId = (int) $service['id'];
            $built[$serviceId] = $this->private__horizonFixtureForService(
                $serviceId,
                (string) $service['name'],
                (string) $service['status'],
                $since,
                $this->jobsPerStatus,
                $serviceId === 1 ? $pinnedJobUuid : null,
            );
        }

        return $built;
    }

    private function private__horizonFixtureForService(
        int $serviceId,
        string $serviceName,
        string $serviceStatus,
        int $since,
        int $jobsPerStatus,
        ?string $pinnedJobUuid,
    ): array {
        $queues = $this->private__queues_for_service($serviceId);
        $failedJobs = (int) \round($jobsPerStatus * (0.15 + ($serviceId % 5) * 0.05));
        $pendingJobs = (int) \round($jobsPerStatus * (0.35 + ($serviceId % 3) * 0.1));
        $completedJobs = $jobsPerStatus * 2 + ($serviceId % 7) * 5;
        $recentJobs = $completedJobs + $pendingJobs;
        $processes = match ($serviceStatus) {
            'offline' => 0,
            'stand_by' => 1 + ($serviceId % 3),
            default => 2 + ($serviceId % 8),
        };

        $stats = [
            'failedJobs' => $failedJobs,
            'recentJobs' => $recentJobs,
            'jobsPerMinute' => $serviceStatus === 'offline' ? 0.0 : \round(1.5 + ($serviceId % 40) * 0.35, 1),
            'status' => match ($serviceStatus) {
                'offline' => 'inactive',
                'stand_by' => 'paused',
                default => 'running',
            },
            'processes' => $processes,
            'wait' => $this->private__wait_map_for_queues($queues, $serviceId),
            'queueWithMaxRuntime' => $queues[0] ?? 'default',
            'queueWithMaxThroughput' => $queues[$serviceId % \count($queues)] ?? 'default',
            'periods' => ['recentJobs' => 60],
        ];

        $workloadRows = [];

        foreach ($queues as $index => $queue) {
            $workloadRows[] = [
                'name' => 'redis.' . $queue,
                'length' => ($serviceId + $index * 3) % 80,
                'processes' => \max(0, $processes - $index),
                'wait' => \round(0.1 + ($index + $serviceId % 7) * 0.2, 1),
            ];
        }

        $pending = [];
        $completed = [];
        $failed = [];
        $jobDetails = [];

        for ($i = 1; $i <= $pendingJobs; $i++) {
            $pending[] = $this->private__job_row($serviceId, $i, $this->private__job_class($serviceId, $i), $queues[$i % \count($queues)], $since + $i * 30);
        }

        for ($i = 1; $i <= $completedJobs; $i++) {
            $index = 1000 + $i;
            $reserved = $since - $i * 45;
            $completed[] = $this->private__job_row(
                $serviceId,
                $index,
                $this->private__job_class($serviceId, $index),
                $queues[$i % \count($queues)],
                $reserved,
                $reserved + 20 + ($i % 90),
            );
        }

        for ($i = 1; $i <= $failedJobs; $i++) {
            $index = 2000 + $i;
            $reserved = $since - $i * 120;
            $uuid = ($pinnedJobUuid !== null && $i === 1) ? $pinnedJobUuid : MockDataset::jobUuid($serviceId, $index);
            $failed[] = $this->private__job_row(
                $serviceId,
                $index,
                $this->private__job_class($serviceId, $index),
                $queues[$i % \count($queues)],
                $reserved,
                null,
                $reserved + 60,
                $uuid,
            );
            $jobDetails[$uuid] = [
                'id' => $uuid,
                'name' => $this->private__job_class($serviceId, $index),
                'queue' => $queues[$i % \count($queues)],
                'status' => 'failed',
                'payload' => ['demo' => true, 'service_id' => $serviceId, 'index' => $index],
                'connection' => 'redis',
                'exception' => "RuntimeException: Demo failure on {$serviceName}\n  at mock fixture:" . (40 + $i),
            ];
        }

        return [
            'stats' => $stats,
            'workload' => ['data' => $workloadRows],
            'masters' => $this->private__masters_for_service($serviceId, $queues, $processes, $serviceStatus),
            'pending_jobs' => ['jobs' => $pending],
            'completed_jobs' => ['jobs' => $completed],
            'failed_jobs' => ['jobs' => $failed],
            'jobs' => $jobDetails,
        ];
    }

    private function private__job_class(int $serviceId, int $index): string
    {
        $jobs = [
            'ProcessInvoice', 'SendReceipt', 'SyncCustomer', 'BuildReport', 'IndexRecords',
            'PurgeCache', 'DispatchWebhook', 'ImportCatalog', 'ExportOrders', 'ChargeCard',
            'RefundPayment', 'SendPush', 'SendEmail', 'ResizeImage', 'TrainModel',
        ];

        return 'App\\Jobs\\' . $jobs[($serviceId + $index) % \count($jobs)];
    }

    private function private__job_row(
        int $serviceId,
        int $index,
        string $name,
        string $queue,
        int $reservedAt,
        ?int $completedAt = null,
        ?int $failedAt = null,
        ?string $uuid = null,
    ): array {
        $id = $uuid ?? MockDataset::jobUuid($serviceId, $index);

        return [
            'index' => $index,
            'id' => $id,
            'name' => $name,
            'queue' => $queue,
            'reserved_at' => $reservedAt,
            'completed_at' => $completedAt,
            'failed_at' => $failedAt,
            'status' => $failedAt !== null ? 'failed' : ($completedAt !== null ? 'completed' : 'pending'),
        ];
    }

    private function private__masters_for_service(int $serviceId, array $queues, int $processes, string $serviceStatus = 'online'): array
    {
        if ($processes === 0) {
            return [];
        }

        $supervisorStatus = match ($serviceStatus) {
            'stand_by' => 'paused',
            'offline' => 'inactive',
            default => 'running',
        };
        $supervisorCount = 1 + ($serviceId % 2);
        $masters = [['supervisors' => []]];

        for ($s = 0; $s < $supervisorCount; $s++) {
            $queue = $queues[$s % \count($queues)];
            $masters[0]['supervisors'][] = [
                'name' => "demo-{$serviceId}:supervisor-{$s}",
                'status' => $supervisorStatus,
                'processes' => \range(1, \max(1, $processes - $s)),
                'options' => [
                    'connection' => 'redis',
                    'queue' => ['redis.' . $queue],
                    'balance' => 'auto',
                ],
                'last_heartbeat_at' => now()->subSeconds(30 + $s)->getTimestamp(),
            ];
        }

        return $masters;
    }

    private function private__queues_for_service(int $serviceId): array
    {
        $all = ['default', 'high', 'low', 'billing', 'emails', 'webhooks', 'imports', 'exports', 'reports', 'notifications'];
        $count = 2 + ($serviceId % 5);
        $queues = [];

        for ($i = 0; $i < $count; $i++) {
            $queues[] = $all[($serviceId + $i) % \count($all)];
        }

        return \array_values(\array_unique($queues));
    }

    private function private__wait_map_for_queues(array $queues, int $serviceId): array
    {
        $wait = [];

        foreach ($queues as $index => $queue) {
            $wait['redis:' . $queue] = \round(0.05 + ($index + 1) * 0.15 + ($serviceId % 9) * 0.1, 2);
        }

        return $wait;
    }
}
