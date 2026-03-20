<?php

namespace App\Support\Horizon;

class QueueNameNormalizer {

    /**
     * Normalize queue name to avoid duplicates caused by different connection prefixes.
     *
     * Example: `redis.default` => `default`
     *
     * @param string|null $queue
     * @return string|null
     */
    public static function normalize(?string $queue): ?string {
        if ($queue === null || $queue === '') {
            return $queue;
        }

        if (\str_starts_with($queue, 'redis.')) {
            $suffix = \substr($queue, \strlen('redis.'));

            return $suffix !== '' ? $suffix : $queue;
        }

        return $queue;
    }
}
