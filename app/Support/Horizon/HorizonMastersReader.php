<?php

namespace App\Support\Horizon;

final class HorizonMastersReader
{
    /**
     * Extract the supervisors from the masters payload.
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

        foreach ($mastersData as $master) {
            if (! \is_array($master)) {
                continue;
            }

            $supervisorsData = $master['supervisors'] ?? null;

            if (! \is_array($supervisorsData)) {
                continue;
            }

            foreach ($supervisorsData as $supervisor) {
                if (! \is_array($supervisor)) {
                    continue;
                }

                $name = isset($supervisor['name']) ? (string) $supervisor['name'] : '';

                if (blank($name)) {
                    continue;
                }

                $groupParts = \explode(':', $name, 2);
                $groupName = $groupParts[0] !== '' ? $groupParts[0] : $name;
                $options = isset($supervisor['options']) && \is_array($supervisor['options']) ? $supervisor['options'] : [];
                $connection = '';

                if (isset($options['connection']) && (string) $options['connection'] !== '') {
                    $connection = (string) $options['connection'];
                } elseif (isset($supervisor['connection']) && (string) $supervisor['connection'] !== '') {
                    $connection = (string) $supervisor['connection'];
                }

                $queues = '';

                if (isset($options['queue'])) {
                    $queuesRaw = $options['queue'];
                    $queues = \is_array($queuesRaw) ? \implode(', ', \array_map('strval', $queuesRaw)) : (string) $queuesRaw;
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

                $balancing = '';

                if (isset($options['balance']) && (string) $options['balance'] !== '') {
                    $balancing = (string) $options['balance'];
                }

                $supervisors[] = [
                    'name' => $name,
                    'groupName' => $groupName,
                    'connection' => $connection,
                    'queues' => $queues,
                    'processes' => $processes,
                    'balancing' => $balancing,
                    'apiStatus' => isset($supervisor['status']) ? (string) $supervisor['status'] : '',
                    'queueNames' => self::private__normalizeQueueNamesFromOptions($options),
                ];
            }
        }

        return $supervisors;
    }

    /**
     * Normalize the queue names from the options.
     *
     * @param array<string, mixed> $options
     *
     * @return list<string>
     */
    private static function private__normalizeQueueNamesFromOptions(array $options): array
    {
        $queues = $options['queue'] ?? null;

        if (! \is_array($queues)) {
            if (empty($queues)) {
                return [];
            }

            $queue = QueueNameNormalizer::normalize((string) $queues);

            return ! empty($queue) ? [$queue] : [];
        }

        $normalizedQueues = [];

        foreach ($queues as $queue) {
            $normalizedQueue = QueueNameNormalizer::normalize((string) $queue);

            if (! empty($normalizedQueue)) {
                $normalizedQueues[$normalizedQueue] = $normalizedQueue;
            }
        }

        return \array_values($normalizedQueues);
    }
}
