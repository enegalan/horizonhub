<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\StreamedResponse;

abstract class StreamController extends Controller
{
    /**
     * Last emitted payload fingerprint per stream target.
     *
     * @var array<string, string>
     */
    private array $private__streamFingerprintsByTarget = [];

    /**
     * Build turbo-stream content from stream operations.
     *
     * Each operation is [action, target, content, streamMethod?].
     *
     * @param list<array<int, mixed>> $operations
     */
    protected function buildStreams(array $operations): string
    {
        $result = [];

        foreach ($operations as $operation) {
            if (\count($operation) < 3) {
                throw new \InvalidArgumentException('Stream operation must have action, target, and content.');
            }

            $streamMethod = $operation[3] ?? null;

            $this->private__appendTurboStream(
                $result,
                (string) $operation[0],
                (string) $operation[1],
                (string) $operation[2],
                $streamMethod !== null ? (string) $streamMethod : null,
            );
        }

        return \implode("\n", $result);
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
        }, 200, $this->private__streamHeaders());
    }

    /**
     * Append a turbo-stream tag to the streams array.
     *
     * @param-out list<string> $streams
     *
     * @param list<string> $streams
     */
    private function private__appendTurboStream(array &$streams, string $action, string $target, string $content, ?string $streamMethod = null): void
    {
        $tag = $this->private__turboStreamTag($action, $target, $content, $streamMethod);

        if (! empty($tag)) {
            $streams[] = $tag;
        }
    }

    /**
     * Get the stream headers.
     *
     * @return array<string, string>
     */
    private function private__streamHeaders(): array
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
    private function private__turboStreamTag(string $action, string $target, string $content, ?string $streamMethod = null): ?string
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
