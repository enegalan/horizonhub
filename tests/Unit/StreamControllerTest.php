<?php

namespace Tests\Unit;

use App\Http\Controllers\StreamController;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class StreamControllerTest extends TestCase
{
    public function test_stream_headers_and_response_type_from_run_stream(): void
    {
        $controller = new class extends StreamController
        {
            /**
             * Expose the protected SSE runner so the test can assert the response headers.
             *
             * @param callable(): ?string $callback The Turbo Stream producer polled on every tick.
             */
            public function public__run(callable $callback): StreamedResponse
            {
                return $this->runStream($callback);
            }
        };

        config()->set('horizonhub.hot_reload_interval', 0.000001);
        $response = $controller->public__run(static fn (): ?string => null);
        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertSame('text/event-stream', $response->headers->get('Content-Type'));
        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('no-cache', $cacheControl);
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('must-revalidate', $cacheControl);
    }

    public function test_turbo_stream_tag_omits_unchanged_payload_for_same_target(): void
    {
        $controller = new class extends StreamController
        {
            /**
             * Expose the protected stream builder so the test can emit from one instance.
             *
             * Reusing a single controller instance is what makes the per-target
             * fingerprint suppression inside `buildStreams()` observable across
             * repeated calls.
             *
             * @param list<array<int, mixed>> $operations The [action, target, content, streamMethod?] operations.
             */
            public function public__build(array $operations): string
            {
                return $this->buildStreams($operations);
            }
        };

        $first = $controller->public__build([
            ['update', 'metrics-value-jobs-minute', '42', null],
        ]);
        $second = $controller->public__build([
            ['update', 'metrics-value-jobs-minute', '42', null],
        ]);
        $third = $controller->public__build([
            ['update', 'metrics-value-jobs-minute', '43', null],
        ]);

        $this->assertStringContainsString('target="metrics-value-jobs-minute"', $first);
        $this->assertSame('', $second);
        $this->assertStringContainsString('target="metrics-value-jobs-minute"', $third);
    }
}
