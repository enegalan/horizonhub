<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\StreamedResponse;

abstract class StreamController extends Controller
{
    const MODE_APPEND_CONTENT = 'append_content';

    const MODE_PUSH_STREAM = 'push_stream';

    const MODE_PUSH_VIEW = 'push_view';

    /**
     * Last emitted payload fingerprint per stream target.
     *
     * @var array<string, string>
     */
    private array $private__streamFingerprintsByTarget = [];

    /**
     * Append a turbo-stream tag to the streams array.
     *
     * @param-out list<string> $streams
     *
     * @param list<string> $streams
     */
    // TODO: Privateize
    protected function appendTurboStream(array &$streams, string $action, string $target, string $content, ?string $streamMethod = null): void
    {
        $tag = $this->turboStreamTag($action, $target, $content, $streamMethod);

        if (! empty($tag)) {
            $streams[] = $tag;
        }
    }

    /**
     * Build the stream operations array into a string.
     *
     * @param list<string> $operations
     */
    protected function buildStreams(array $operations): string
    {
        // TODO: Define operations as a class (StreamOperation)
        $result = [];

        foreach ($operations as $operation) {
            [$mode] = $operation;
            match ($mode) {
                self::MODE_APPEND_CONTENT => (function () use ($operation, &$result): void {
                    [, $action, $target, $content] = $operation;
                    $streamMethod = $operation[4] ?? null;
                    $this->appendTurboStream($result, $action, $target, $content, $streamMethod);
                })(),
                self::MODE_PUSH_VIEW => (function () use ($operation, &$result): void {
                    [, $views, $streamMethod] = $operation;
                    $this->pushViewStreams($result, $views, $streamMethod);
                })(),
                self::MODE_PUSH_STREAM => (function () use ($operation, &$result): void {
                    [, $updates, $streamMethod] = $operation;
                    $this->pushStreamUpdates($result, $updates, $streamMethod);
                })(),
                default => throw new \InvalidArgumentException("Invalid mode: $mode"),
            };
        }

        return \implode("\n", $result);
    }

    /**
     * Push stream updates to the streams array.
     *
     * @param-out list<string> $streams
     *
     * @param list<string> $streams
     * @param array<string, string> $updates
     */
    // TODO: Privateize
    protected function pushStreamUpdates(array &$streams, array $updates, ?string $streamMethod = null): void
    {
        foreach ($updates as $target => $content) {
            $tag = $this->turboStreamTag('update', $target, $content, $streamMethod);

            if (! empty($tag)) {
                $streams[] = $tag;
            }
        }
    }

    /**
     * Push view streams to the streams array.
     *
     * @param-out list<string> $streams
     *
     * @param list<string> $streams
     * @param array<string, array<string, mixed>> $views
     */
    // TODO: Privateize
    protected function pushViewStreams(array &$streams, array $views, ?string $streamMethod = null): void
    {
        $updates = [];

        foreach ($views as $target => $viewData) {
            $updates[$target] = \view($viewData['view'], $viewData['data'] ?? [])->render();
        }

        $this->pushStreamUpdates($streams, $updates, $streamMethod);
    }

    /**
     * Run a streaming SSE.
     *
     * The callback must return a Turbo Stream HTML string (one or more
     * <turbo-stream> elements) or null when there is nothing to push.
     *
     * @param callable(): ?string $turboStreamCallback
     */
    protected function runStream(callable $turboStreamCallback): StreamedResponse
    {
        $intervalSeconds = (float) config('horizonhub.hot_reload_interval');
        $intervalMicroseconds = (int) ($intervalSeconds * 1_000_000);

        return \response()->stream(function () use ($intervalMicroseconds, $turboStreamCallback): void {
            echo ": stream-open\n\n";

            if (\function_exists('ob_flush')) {
                @\ob_flush();
            }

            if (\function_exists('flush')) {
                @\flush();
            }

            while (true) {
                if (\connection_aborted()) {
                    break;
                }

                $turboHtml = $turboStreamCallback();

                if (! empty($turboHtml)) {
                    $lines = \explode("\n", $turboHtml);

                    foreach ($lines as $line) {
                        echo "data: $line\n";
                    }
                    echo "\n";

                    if (\function_exists('ob_flush')) {
                        @\ob_flush();
                    }

                    if (\function_exists('flush')) {
                        @\flush();
                    }
                }

                if (\connection_aborted()) {
                    break;
                }

                \usleep($intervalMicroseconds);
            }
        }, 200, $this->streamHeaders());
    }

    /**
     * Get the stream headers.
     *
     * @return array<string, string>
     */
    // TODO: Privateize
    protected function streamHeaders(): array
    {
        return [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ];
    }

    /**
     * Build a turbo-stream tag, or null when the payload is unchanged since the last emit for this target.
     */
    // TODO: Privateize
    protected function turboStreamTag(string $action, string $target, string $content, ?string $streamMethod = null): ?string
    {
        $methodKey = $streamMethod ?? '';
        $fingerprint = \hash('sha256', "$action\0$target\0$methodKey\0$content");

        if (($this->private__streamFingerprintsByTarget[$target] ?? '') === $fingerprint) {
            return null;
        }

        $this->private__streamFingerprintsByTarget[$target] = $fingerprint;

        $open = '<turbo-stream action="' . e($action) . '" target="' . e($target) . '"';

        if (! empty($streamMethod)) {
            $open .= ' method="' . e($streamMethod) . '"';
        }

        return "$open><template>$content</template></turbo-stream>";
    }
}
