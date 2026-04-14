<?php

namespace App\Support\Horizon;

class JobCommandDataExtractor
{
    /**
     * Resolve delay in seconds for a queued job payload.
     *
     * Laravel's Horizon Redis queue calls {@see Queue::createPayload} without
     * the delay argument for {@see RedisQueue::later}, so the payload JSON
     * field "delay" is often null while the serialized "data.command" still carries the job
     * instance delay.
     *
     * @param  array<string, mixed>  $payload
     * @param  mixed  $horizonMetaDelay  Optional top-level delay from Horizon's job hash (e.g. after release).
     * @param  array<string, mixed>|null  $commandData  When already computed (e.g. for job detail), avoids a second unserialize.
     */
    public static function extractDelaySeconds(array $payload, mixed $horizonMetaDelay = null, ?array $commandData = null): ?int
    {
        foreach ([$horizonMetaDelay, $payload['delay'] ?? null] as $candidate) {
            $normalized = self::normalizeDelaySeconds($candidate);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        $resolvedCommand = $commandData ?? self::extract($payload);
        if (! \is_array($resolvedCommand)) {
            return null;
        }

        return self::normalizeDelaySeconds($resolvedCommand['delay'] ?? null);
    }

    /**
     * Extract command data from job payload.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    public static function extract(array $payload): ?array
    {
        if (! isset($payload['data']) || ! \is_array($payload['data'])) {
            return null;
        }

        $data = $payload['data'];
        if (! isset($data['command']) || ! \is_string($data['command']) || $data['command'] === '') {
            return null;
        }

        $unserialized = @\unserialize($data['command'], ['allowed_classes' => false]);
        if ($unserialized === false && $data['command'] !== 'b:0;') {
            return null;
        }

        $normalized = self::normalize($unserialized);
        if (! \is_array($normalized)) {
            return null;
        }

        return $normalized;
    }

    /**
     * Normalize unserialized data to scalar/array values.
     *
     * @param  mixed  $value
     * @return mixed
     */
    private static function normalize(mixed $value): mixed
    {
        if (\is_null($value) || \is_scalar($value)) {
            return $value;
        }

        if (\is_array($value)) {
            $normalized = [];
            foreach ($value as $key => $item) {
                $normalized[$key] = self::normalize($item);
            }

            return $normalized;
        }

        if (\is_object($value)) {
            $casted = (array) $value;
            $normalized = [];
            foreach ($casted as $key => $item) {
                $cleanKey = self::cleanPropertyKey($key);
                if ($cleanKey === '__PHP_Incomplete_Class_Name') {
                    continue;
                }
                $normalized[$cleanKey] = self::normalize($item);
            }

            return $normalized;
        }

        return (string) $value;
    }

    /**
     * Convert private/protected serialized property names to plain keys.
     *
     * @param  string|int  $key
     * @return string|int
     */
    private static function cleanPropertyKey(string|int $key): string|int
    {
        if (! \is_string($key) || $key === '') {
            return $key;
        }

        if (\str_contains($key, "\0")) {
            $parts = \explode("\0", $key);
            $candidate = \end($parts);
            if (\is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
        }

        return $key;
    }

    /**
     * Normalize a delay value to a positive integer number of seconds, or null.
     * 
     * @param  mixed  $delay
     * @return int|null
     */
    private static function normalizeDelaySeconds(mixed $delay): ?int
    {
        if ($delay === null || $delay === '' || $delay === false || $delay <= 0) {
            return null;
        }

        if (\is_int($delay)) {
            return $delay;
        }

        if (\is_float($delay)) {
            return (int) \round($delay);
        }

        if (\is_string($delay) && $delay !== '' && \is_numeric($delay)) {
            return (int) $delay;
        }

        return $delay;
    }
}
