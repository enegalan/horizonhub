<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\StreamedResponse;

abstract class StreamController extends Controller
{
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
}
