<?php

namespace App\Support\Jobs;

final class JobSearchFilter
{
    /**
     * Check whether a raw Horizon job payload matches the search term.
     *
     * The search runs against the raw payload fields Horizon returns (`queue`,
     * `name` and `id`) so non-matching jobs can be discarded before any row
     * mapping, timestamp parsing or sorting takes place.
     *
     * @param array<string, mixed> $job The raw job payload.
     * @param string $search The search term.
     */
    public static function matches(array $job, string $search): bool
    {
        if ($search === '') {
            return true;
        }

        $haystack = (string) ($job['queue'] ?? '')
            . ' ' . (string) ($job['name'] ?? '')
            . ' ' . (string) ($job['id'] ?? '');

        return \stripos($haystack, $search) !== false;
    }
}
