<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\StreamedResponse;

abstract class StreamController extends Controller
{
    protected function pushStreamUpdates(array &$streams, array $updates, ?string $streamMethod = null): void
    {
        foreach ($updates as $target => $content) {
            $streams[] = $this->turboStreamTag('update', $target, $content, $streamMethod);
        }
    }

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

                if ($turboHtml !== null && $turboHtml !== '') {
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
    protected function streamHeaders(): array
    {
        return [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ];
    }

    protected function turboStreamTag(string $action, string $target, string $content, ?string $streamMethod = null): string
    {
        $open = '<turbo-stream action="' . e($action) . '" target="' . e($target) . '"';

        if ($streamMethod !== null && $streamMethod !== '') {
            $open .= ' method="' . e($streamMethod) . '"';
        }

        return "$open><template>$content</template></turbo-stream>";
    }
}
