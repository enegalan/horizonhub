<?php

namespace App\Support\Horizon;

class QueueNameNormalizer
{
    /**
     * Separators between Laravel queue connection name and queue name in Horizon payloads.
     *
     * @var list<string>
     */
    private const CONNECTION_QUEUE_SEPARATORS = ['.', ':'];

    /**
     * Fallback: Horizon-style `{connection}.{queue}` / `{connection}:{queue}` where the connection
     * segment matches a typical Laravel `config/queue.php` connection key (letter-first identifier).
     *
     * @var non-empty-string
     */
    private const FALLBACK_CONNECTION_PREFIX_PATTERN = '/^([a-zA-Z][a-zA-Z0-9_-]*)[.:](.+)$/D';

    /**
     * Normalize queue name to avoid duplicates caused by different connection prefixes.
     *
     * Internal rules:
     * 1. Strip when the prefix matches a key of `config('queue.connections')` plus `.` or `:`.
     *    Longer keys are tried first (e.g. `redis_cluster` before `redis`).
     * 2. Otherwise strip one segment if the whole string matches a standard Laravel Horizon
     *    `connection.queue` / `connection:queue` pattern (letter-first connection identifier).
     */
    public static function normalize(?string $queue): ?string
    {
        if ($queue === null || $queue === '') {
            return $queue;
        }

        foreach (self::connectionNamesFromConfig() as $connectionName) {
            foreach (self::CONNECTION_QUEUE_SEPARATORS as $separator) {
                $fullPrefix = "$connectionName$separator";
                if (! \str_starts_with($queue, $fullPrefix)) {
                    continue;
                }

                $suffix = \substr($queue, \strlen($fullPrefix));

                return $suffix !== '' ? $suffix : $queue;
            }
        }

        if (\preg_match(self::FALLBACK_CONNECTION_PREFIX_PATTERN, $queue, $matches) === 1) {
            return $matches[2];
        }

        return $queue;
    }

    /**
     * @return list<string>
     */
    private static function connectionNamesFromConfig(): array
    {
        $fromConfig = \array_keys(ConfigHelper::get('queue.connections'));
        $names = [];
        foreach ($fromConfig as $name) {
            $name = (string) $name;
            if (empty($name)) {
                continue;
            }

            $names[$name] = true;
        }

        $list = \array_keys($names);
        \usort($list, static fn (string $a, string $b): int => \strlen($b) <=> \strlen($a));

        return $list;
    }
}
