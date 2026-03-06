<?php

namespace Tests\Unit;

use App\Events\HorizonEventReceived;
use App\Models\HorizonFailedJob;
use App\Models\HorizonJob;
use App\Models\Service;
use App\Services\AlertEngine;
use App\Services\HorizonEventProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class HorizonEventProcessorTest extends TestCase {
    use RefreshDatabase;

    public function test_job_failed_event_persists_models_and_triggers_alert_engine(): void {
        Event::fake();

        $service = Service::create(array(
            'name' => 'svc',
            'api_key' => 'key',
            'base_url' => 'https://a.com',
            'status' => 'online',
        ));

        $engine = $this->createMock(AlertEngine::class);
        $engine->expects($this->once())
            ->method('evaluateAfterEvent')
            ->with($service->id, 'JobFailed', $this->isType('int'));

        $processor = new HorizonEventProcessor($engine);

        $event = array(
            'event_type' => 'JobFailed',
            'job_id' => 'uuid-1',
            'queue' => 'default',
            'name' => 'SomeJob',
            'payload' => array('displayName' => 'SomeJob'),
            'attempts' => 1,
            'failed_at' => now()->toIso8601String(),
            'exception' => 'boom',
        );

        $processor->process($service, $event);

        $this->assertSame(1, HorizonFailedJob::count());
        $this->assertSame(1, HorizonJob::count());

        Event::assertDispatched(HorizonEventReceived::class, 1);
    }
}
