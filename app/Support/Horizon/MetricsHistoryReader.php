<?php

namespace App\Support\Horizon;

use App\Support\NumberFormatter;

final class MetricsHistoryReader
{
    /**
     * Combine queue history summaries from several services.
     *
     * Throughput is summed, wait is maxed, and runtime is averaged.
     *
     * @param list<array<string, mixed>|null> $summaries
     *
     * @return array{
     *     throughput: float|null,
     *     runtime: float|null,
     *     wait: float|null,
     *     series: array{time: list<int>, throughput: list<float>, runtime: list<float>, wait: list<float>}
     * }|null
     */
    public static function combine(array $summaries): ?array
    {
        $summaries = \array_values(\array_filter($summaries, 'is_array'));

        if ($summaries === []) {
            return null;
        }

        if (\count($summaries) === 1) {
            return $summaries[0];
        }

        $throughput = null;
        $runtimes = [];
        $wait = null;
        $points = [];

        foreach ($summaries as $summary) {
            $summaryThroughput = NumberFormatter::floatOrNull($summary['throughput'] ?? null);
            $summaryRuntime = NumberFormatter::floatOrNull($summary['runtime'] ?? null);
            $summaryWait = NumberFormatter::floatOrNull($summary['wait'] ?? null);

            if ($summaryThroughput !== null) {
                $throughput = ($throughput ?? 0.0) + $summaryThroughput;
            }

            if ($summaryRuntime !== null) {
                $runtimes[] = $summaryRuntime;
            }

            if ($summaryWait !== null && ($wait === null || $summaryWait > $wait)) {
                $wait = $summaryWait;
            }

            foreach (self::private__seriesPoints($summary['series'] ?? null) as $point) {
                $time = $point['time'];
                $existing = $points[$time] ?? [
                    'time' => $time,
                    'throughput' => 0.0,
                    'runtimes' => [],
                    'wait' => 0.0,
                ];
                $existing['throughput'] += $point['throughput'];
                $existing['wait'] = \max($existing['wait'], $point['wait']);

                if ($point['runtime'] !== null) {
                    $existing['runtimes'][] = $point['runtime'];
                }

                $points[$time] = $existing;
            }
        }

        \ksort($points, SORT_NUMERIC);

        return [
            'throughput' => $throughput,
            'runtime' => $runtimes === [] ? null : \array_sum($runtimes) / \count($runtimes),
            'wait' => $wait,
            'series' => self::private__seriesFromPoints($points),
        ];
    }

    /**
     * Summarize one queue metrics response.
     *
     * Horizon returns hourly buckets with `time`, `throughput`, `runtime` and
     * `wait`. The last bucket is the headline; the last N buckets feed sparklines
     * (`horizonhub.queue_metrics_history_points`).
     *
     * @param array<mixed>|null $payload The decoded metrics response.
     *
     * @return array{
     *     throughput: float|null,
     *     runtime: float|null,
     *     wait: float|null,
     *     series: array{time: list<int>, throughput: list<float>, runtime: list<float>, wait: list<float>}
     * }|null
     */
    public static function summarize(?array $payload): ?array
    {
        $buckets = $payload ?? [];

        foreach (['data', 'metrics'] as $key) {
            if (isset($payload[$key]) && \is_array($payload[$key])) {
                $buckets = $payload[$key];
                break;
            }
        }

        $points = [];

        foreach ($buckets as $index => $bucket) {
            if (! \is_array($bucket)) {
                continue;
            }

            $time = NumberFormatter::intOrNull($bucket['time'] ?? null) ?? $index;
            $points[$time] = [
                'time' => $time,
                'throughput' => NumberFormatter::floatOrNull($bucket['throughput'] ?? null),
                'runtime' => NumberFormatter::floatOrNull($bucket['runtime'] ?? null),
                'wait' => NumberFormatter::floatOrNull($bucket['wait'] ?? null),
            ];
        }

        if ($points === []) {
            return null;
        }

        \ksort($points, SORT_NUMERIC);
        $points = \array_slice(\array_values($points), -self::private__maxPoints());
        $latest = $points[\array_key_last($points)];

        return [
            'throughput' => $latest['throughput'],
            'runtime' => $latest['runtime'],
            'wait' => $latest['wait'],
            'series' => [
                'time' => \array_map(static fn (array $point): int => $point['time'], $points),
                'throughput' => \array_map(static fn (array $point): float => $point['throughput'] ?? 0.0, $points),
                'runtime' => \array_map(static fn (array $point): float => $point['runtime'] ?? 0.0, $points),
                'wait' => \array_map(static fn (array $point): float => $point['wait'] ?? 0.0, $points),
            ],
        ];
    }

    /**
     * Number of history points kept for sparklines.
     */
    private static function private__maxPoints(): int
    {
        return \max(1, (int) config('horizonhub.queue_metrics_history_points', 24));
    }

    /**
     * Build the public series shape from merged points.
     *
     * @param array<int, array{time: int, throughput: float, runtimes: list<float>, wait: float}> $points
     *
     * @return array{time: list<int>, throughput: list<float>, runtime: list<float>, wait: list<float>}
     */
    private static function private__seriesFromPoints(array $points): array
    {
        $series = [
            'time' => [],
            'throughput' => [],
            'runtime' => [],
            'wait' => [],
        ];

        foreach (\array_slice(\array_values($points), -self::private__maxPoints()) as $point) {
            $series['time'][] = $point['time'];
            $series['throughput'][] = $point['throughput'];
            $series['runtime'][] = $point['runtimes'] === []
                ? 0.0
                : \array_sum($point['runtimes']) / \count($point['runtimes']);
            $series['wait'][] = $point['wait'];
        }

        return $series;
    }

    /**
     * Read aligned series points from one summary.
     *
     * @return list<array{time: int, throughput: float, runtime: float|null, wait: float}>
     */
    private static function private__seriesPoints(mixed $series): array
    {
        if (! \is_array($series)) {
            return [];
        }

        $points = [];

        foreach (\array_keys((array) ($series['time'] ?? [])) as $index) {
            $points[] = [
                'time' => (int) ($series['time'][$index] ?? $index),
                'throughput' => NumberFormatter::floatOrNull($series['throughput'][$index] ?? null) ?? 0.0,
                'runtime' => NumberFormatter::floatOrNull($series['runtime'][$index] ?? null),
                'wait' => NumberFormatter::floatOrNull($series['wait'][$index] ?? null) ?? 0.0,
            ];
        }

        return $points;
    }
}
