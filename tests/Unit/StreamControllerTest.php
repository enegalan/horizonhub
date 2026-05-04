<?php

namespace Tests\Unit;

use App\Http\Controllers\StreamController;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class StreamControllerTest extends TestCase
{
    public function test_stream_headers_and_response_type_from_run_stream(): void
    {
        $controller = new class extends StreamController {
            public function public__headers(): array
            {
                return $this->streamHeaders();
            }

            public function public__run(callable $callback): StreamedResponse
            {
                return $this->runStream($callback);
            }
        };

        config()->set('horizonhub.hot_reload_interval', 0.000001);
        $headers = $controller->public__headers();
        $this->assertSame('text/event-stream', $headers['Content-Type']);
        $this->assertSame('no-cache, no-store, must-revalidate', $headers['Cache-Control']);

        $response = $controller->public__run(static fn (): ?string => null);
        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertSame('text/event-stream', $response->headers->get('Content-Type'));
    }
}
