<?php

namespace App\Support\Horizon;

use App\Support\ArrayFormatter;
use App\Support\BoolFormatter;
use App\Support\NumberFormatter;
use App\Support\Queues\QueueNameNormalizer;

final class MasterReader
{
    /**
     * Yield each supervisor array from a masters payload.
     *
     * @param list<mixed>|array<int, mixed> $mastersData The masters data.
     *
     * @return \Generator<int, array<string, mixed>>
     */
    public static function eachSupervisor(array $mastersData): \Generator
    {
        foreach ($mastersData as $master) {
            if (! \is_array($master)) {
                continue;
            }

            $supervisorsData = $master['supervisors'] ?? null;

            if (! \is_array($supervisorsData)) {
                continue;
            }

            foreach ($supervisorsData as $supervisor) {
                if (\is_array($supervisor)) {
                    yield $supervisor;
                }
            }
        }
    }

    /**
     * Find a supervisor by name inside normalised master groups.
     *
     * @param list<array<string, mixed>> $groups The master groups.
     *
     * @return array{master: array<string, mixed>, supervisor: array<string, mixed>}|null
     */
    public static function findSupervisor(array $groups, string $name): ?array
    {
        foreach ($groups as $group) {
            foreach ($group['supervisors'] as $supervisor) {
                if (($supervisor['name'] ?? null) === $name) {
                    return ['master' => $group, 'supervisor' => $supervisor];
                }
            }
        }

        return null;
    }

    /**
     * Extract master groups with their full supervisor configuration.
     *
     * @param list<mixed>|array<int|string, mixed> $mastersData The masters data.
     *
     * @return list<array{
     *     name: string,
     *     environment: string,
     *     pid: string,
     *     status: string,
     *     supervisors: list<array<string, mixed>>
     * }>
     */
    public static function groupsFromMastersPayload(array $mastersData): array
    {
        $groups = [];

        foreach ($mastersData as $masterKey => $master) {
            if (\is_array($master)) {
                $groups[] = self::private__masterGroup($master, $masterKey);
            }
        }

        return $groups;
    }

    /**
     * Extract the supervisors from the masters payload (flat legacy shape).
     *
     * @param list<mixed>|array<int, mixed> $mastersData The masters data.
     *
     * @return list<array{
     *     name: string,
     *     groupName: string,
     *     connection: string,
     *     queues: string,
     *     processes: int|null,
     *     balancing: string,
     *     apiStatus: string,
     *     queueNames: list<string>
     * }>
     */
    public static function supervisorsFromMastersPayload(array $mastersData): array
    {
        $supervisors = [];

        foreach (self::groupsFromMastersPayload($mastersData) as $group) {
            foreach ($group['supervisors'] as $supervisor) {
                $name = (string) ($supervisor['name'] ?? '');

                if (blank($name)) {
                    continue;
                }

                $groupParts = \explode(':', $name, 2);

                $supervisors[] = [
                    'name' => $name,
                    'groupName' => $groupParts[0] !== '' ? $groupParts[0] : $name,
                    'connection' => (string) ($supervisor['connection'] ?? ''),
                    'queues' => (string) ($supervisor['queues'] ?? ''),
                    'processes' => NumberFormatter::intOrNull($supervisor['processes'] ?? null),
                    'balancing' => (string) ($supervisor['balance'] ?? ''),
                    'apiStatus' => (string) ($supervisor['status'] ?? ''),
                    'queueNames' => \array_values(\array_map('strval', $supervisor['queueNames'] ?? [])),
                ];
            }
        }

        return $supervisors;
    }

    /**
     * Normalise one master and its supervisors.
     *
     * @param array<string, mixed> $master The raw master.
     * @param int|string $masterKey The array key the master was read from.
     *
     * @return array{name: string, environment: string, pid: string, status: string, supervisors: list<array<string, mixed>>}
     */
    private static function private__masterGroup(array $master, int|string $masterKey): array
    {
        $name = (string) ($master['name'] ?? '');
        $name = $name !== '' ? $name : (\is_string($masterKey) && $masterKey !== '' ? $masterKey : 'master');
        $status = (string) ($master['status'] ?? '');
        $supervisors = [];

        foreach (ArrayFormatter::orEmpty($master['supervisors'] ?? null) as $supervisor) {
            if (! \is_array($supervisor)) {
                continue;
            }

            $normalized = self::private__supervisor($supervisor, $status);

            if ($normalized !== null) {
                $supervisors[] = $normalized;
            }
        }

        return [
            'name' => $name,
            'environment' => (string) ($master['environment'] ?? ''),
            'pid' => (string) ($master['pid'] ?? ''),
            'status' => $status,
            'supervisors' => $supervisors,
        ];
    }

    /**
     * Sum the processes and keep the per-queue breakdown.
     *
     * @param mixed $processes The raw processes value.
     *
     * @return array{0: int|null, 1: array<string, int>}
     */
    private static function private__processes(mixed $processes): array
    {
        if (! \is_array($processes)) {
            return [null, []];
        }

        $total = 0;
        $byQueue = [];

        foreach ($processes as $queue => $value) {
            $count = NumberFormatter::intOrNull($value);

            if ($count === null) {
                continue;
            }

            $total += $count;
            $queue = QueueNameNormalizer::normalize((string) $queue);
            $byQueue[$queue] = $count;
        }

        return [$total, $byQueue];
    }

    /**
     * Normalise one supervisor, or null when it has no name.
     *
     * @param array<string, mixed> $supervisor The raw supervisor.
     * @param string $masterStatus The parent master status.
     *
     * @return array<string, mixed>|null
     */
    private static function private__supervisor(array $supervisor, string $masterStatus): ?array
    {
        $name = (string) ($supervisor['name'] ?? '');

        if ($name === '') {
            return null;
        }

        $options = ArrayFormatter::orEmpty($supervisor['options'] ?? null);
        $queueNames = QueueNameNormalizer::normalizeListFromOptions($options);
        $connection = (string) ($options['connection'] ?? '');
        $connection = $connection !== '' ? $connection : (string) ($supervisor['connection'] ?? '');
        [$processes, $processesByQueue] = self::private__processes($supervisor['processes'] ?? null);
        $status = (string) ($supervisor['status'] ?? '');
        $minProcesses = NumberFormatter::intOrNull($options['minProcesses'] ?? null);
        $maxProcesses = NumberFormatter::intOrNull($options['maxProcesses'] ?? null);

        return [
            'name' => $name,
            'pid' => (string) ($supervisor['pid'] ?? ''),
            'status' => $status,
            'connection' => $connection,
            'queues' => \implode(', ', $queueNames),
            'queueNames' => $queueNames,
            'processes' => $processes,
            'processesByQueue' => $processesByQueue,
            'balance' => (string) ($options['balance'] ?? ''),
            'autoScalingStrategy' => (string) ($options['autoScalingStrategy'] ?? ''),
            'minProcesses' => $minProcesses,
            'maxProcesses' => $maxProcesses,
            'maxTries' => NumberFormatter::intOrNull($options['maxTries'] ?? null),
            'maxTime' => NumberFormatter::intOrNull($options['maxTime'] ?? null),
            'maxJobs' => NumberFormatter::intOrNull($options['maxJobs'] ?? null),
            'memory' => NumberFormatter::intOrNull($options['memory'] ?? null),
            'timeout' => NumberFormatter::intOrNull($options['timeout'] ?? null),
            'nice' => NumberFormatter::intOrNull($options['nice'] ?? null),
            'sleep' => NumberFormatter::floatOrNull($options['sleep'] ?? null),
            'rest' => NumberFormatter::floatOrNull($options['rest'] ?? null),
            'backoff' => $options['backoff'] ?? null,
            'balanceCooldown' => NumberFormatter::intOrNull($options['balanceCooldown'] ?? null),
            'balanceMaxShift' => NumberFormatter::intOrNull($options['balanceMaxShift'] ?? null),
            'force' => BoolFormatter::orNull($options['force'] ?? null),
            'options' => $options,
            'warnings' => self::private__warnings($masterStatus, $status, $processes, $minProcesses, $maxProcesses, $queueNames),
        ];
    }

    /**
     * Build the warnings shown for one supervisor.
     *
     * @param list<string> $queueNames The normalised queue names.
     *
     * @return list<string>
     */
    private static function private__warnings(
        string $masterStatus,
        string $supervisorStatus,
        ?int $processes,
        ?int $minProcesses,
        ?int $maxProcesses,
        array $queueNames,
    ): array {
        $warnings = [];
        $healthyStatuses = ['running', 'active'];

        if ($masterStatus !== '' && ! \in_array(\strtolower($masterStatus), $healthyStatuses, true)) {
            $warnings[] = "Master is {$masterStatus}.";
        }

        if ($supervisorStatus !== '' && ! \in_array(\strtolower($supervisorStatus), $healthyStatuses, true)) {
            $warnings[] = "Supervisor is {$supervisorStatus}.";
        }

        if ($processes !== null && $minProcesses !== null && $processes < $minProcesses) {
            $warnings[] = "Processes ({$processes}) are below the configured minimum ({$minProcesses}).";
        }

        if ($processes !== null && $maxProcesses !== null && $processes > $maxProcesses) {
            $warnings[] = "Processes ({$processes}) exceed the configured maximum ({$maxProcesses}).";
        }

        if ($queueNames === []) {
            $warnings[] = 'No queues are configured for this supervisor.';
        }

        return $warnings;
    }
}
