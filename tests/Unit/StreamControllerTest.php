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

    public function test_turbo_stream_tag_omits_unchanged_payload_for_same_target(): void
    {
        $controller = new class extends StreamController
        {
            public function public__tag(string $action, string $target, string $content, ?string $method = null): ?string
            {
                return $this->turboStreamTag($action, $target, $content, $method);
            }

            public function public__push(array &$streams, array $updates): void
            {
                $this->pushStreamUpdates($streams, $updates);
            }
        };

        $first = $controller->public__tag('update', 'metrics-value-jobs-minute', '42');
        $second = $controller->public__tag('update', 'metrics-value-jobs-minute', '42');
        $third = $controller->public__tag('update', 'metrics-value-jobs-minute', '43');

        $this->assertNotNull($first);
        $this->assertStringContainsString('target="metrics-value-jobs-minute"', $first);
        $this->assertNull($second);
        $this->assertNotNull($third);

        $streams = [];
        $controller->public__push($streams, ['dashboard-value-jobs-minute' => '1']);
        $controller->public__push($streams, ['dashboard-value-jobs-minute' => '1']);
        $controller->public__push($streams, ['dashboard-value-jobs-minute' => '2']);

        $this->assertCount(2, $streams);
    }
}
