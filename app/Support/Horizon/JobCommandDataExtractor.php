<?php

namespace App\Support\Horizon;

class JobCommandDataExtractor
{
    /**
     * Extract command data from job payload.
     *
     * @param array<string, mixed> $payload The payload.
     *
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
     * Convert private/protected serialized property names to plain keys.
     *
     * @param string|int $key The key.
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
     * Normalize unserialized data to scalar/array values.
     *
     * @param mixed $value The value.
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
}
