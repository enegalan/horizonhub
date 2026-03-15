<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\StreamedResponse;

abstract class StreamController extends Controller {

    /**
     * Get the stream headers.
     *
     * @return array<string, string>
     */
    protected function streamHeaders(): array {
        return [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ];
    }

    /**
     * Get the interval for the stream.
     *
     * @return int
     */
    protected function getInterval(): int {
        $interval = (int) \config('horizonhub.hot_reload_interval');
        return $interval >= 1 ? $interval : 1;
    }

    /**
     * Run a streaming SSE loop for the given event type.
     *
     * @param callable(): array<string, mixed> $payloadCallback
     * @param string $eventType
     * @return StreamedResponse
     */
    protected function runStream(callable $payloadCallback, string $eventType): StreamedResponse {
        $interval = $this->getInterval();
        return \response()->stream(function () use ($interval, $payloadCallback, $eventType): void {
            while (true) {
                if (\connection_aborted()) {
                    break;
                }

                $payload = $payloadCallback();

                $json = \json_encode($payload);
                if ($json === false) {
                    $json = '{}';
                }

                echo "event: $eventType\n";
                echo "data: $json\n\n";

                if (\function_exists('ob_flush')) {
                    @\ob_flush();
                }
                if (\function_exists('flush')) {
                    @\flush();
                }

                if (\connection_aborted()) {
                    break;
                }

                \sleep($interval);
            }
        }, 200, $this->streamHeaders());
    }
}
