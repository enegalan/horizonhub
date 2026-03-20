<?php

namespace Tests\Unit;

use App\Events\HorizonEventReceived;
use App\Models\Service;
use App\Services\AlertEngine;
use App\Services\HorizonEventProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class HorizonEventProcessorTest extends TestCase {
    use RefreshDatabase;

    public function test_job_failed_event_persists_models_and_triggers_alert_engine(): void {
        Event::fake([HorizonEventReceived::class]);

        $service = Service::create([
            'name' => 'svc',
            'api_key' => 'key',
            'base_url' => 'https://a.com',
            'status' => 'online',
        ]);

        $engine = $this->createMock(AlertEngine::class);
        $engine->expects($this->once())
            ->method('evaluateAfterEvent')
            ->with($service->id, 'JobFailed', 'uuid-1');

        $processor = new HorizonEventProcessor($engine);

        $event = [
            'event_type' => 'JobFailed',
            'job_uuid' => 'uuid-1',
            'queue' => 'default',
            'name' => 'SomeJob',
            'payload' => ['displayName' => 'SomeJob'],
            'attempts' => 1,
            'failed_at' => \now()->toIso8601String(),
            'exception' => 'boom',
        ];

        $processor->process($service, $event);

        Event::assertDispatched(HorizonEventReceived::class, 1);
    }

    public function test_queue_paused_event_creates_or_updates_horizon_queue_state(): void {
        Event::fake([HorizonEventReceived::class]);

        $service = Service::create([
            'name' => 'svc',
            'api_key' => 'key',
            'base_url' => 'https://a.com',
            'status' => 'online',
        ]);

        $engine = $this->createMock(AlertEngine::class);
        $engine->expects($this->once())
            ->method('evaluateAfterEvent')
            ->with($service->id, 'QueuePaused', null);

        $processor = new HorizonEventProcessor($engine);

        $processor->process($service, [
            'event_type' => 'QueuePaused',
            'queue' => 'redis.default',
            'status' => 'paused',
        ]);
        Event::assertDispatched(HorizonEventReceived::class, 1);
    }

    public function test_queue_resumed_event_updates_horizon_queue_state_to_not_paused(): void {
        Event::fake([HorizonEventReceived::class]);

        $service = Service::create([
            'name' => 'svc',
            'api_key' => 'key',
            'base_url' => 'https://a.com',
            'status' => 'online',
        ]);

        $engine = $this->createMock(AlertEngine::class);
        $engine->expects($this->once())
            ->method('evaluateAfterEvent')
            ->with($service->id, 'QueueResumed', null);

        $processor = new HorizonEventProcessor($engine);

        $processor->process($service, [
            'event_type' => 'QueueResumed',
            'queue' => 'redis.default',
            'status' => 'resumed',
        ]);
        Event::assertDispatched(HorizonEventReceived::class, 1);
    }
}
