<?php

namespace Tests\Unit;

use App\Events\HorizonEventReceived;
use App\Models\HorizonFailedJob;
use App\Models\HorizonJob;
use App\Models\HorizonQueueState;
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

        $service = Service::create([
            'name' => 'svc',
            'api_key' => 'key',
            'base_url' => 'https://a.com',
            'status' => 'online',
        ]);

        $engine = $this->createMock(AlertEngine::class);
        $engine->expects($this->once())
            ->method('evaluateAfterEvent')
            ->with($service->id, 'JobFailed', $this->isType('int'));

        $processor = new HorizonEventProcessor($engine);

        $event = [
            'event_type' => 'JobFailed',
            'job_id' => 'uuid-1',
            'queue' => 'default',
            'name' => 'SomeJob',
            'payload' => ['displayName' => 'SomeJob'],
            'attempts' => 1,
            'failed_at' => \now()->toIso8601String(),
            'exception' => 'boom',
        ];

        $processor->process($service, $event);

        $this->assertSame(1, HorizonFailedJob::count());
        $this->assertSame(1, HorizonJob::count());

        Event::assertDispatched(HorizonEventReceived::class, 1);
    }

    public function test_queue_paused_event_creates_or_updates_horizon_queue_state(): void {
        Event::fake();

        $service = Service::create([
            'name' => 'svc',
            'api_key' => 'key',
            'base_url' => 'https://a.com',
            'status' => 'online',
        ]);

        $processor = new HorizonEventProcessor($this->createMock(AlertEngine::class));

        $processor->process($service, [
            'event_type' => 'QueuePaused',
            'queue' => 'redis.default',
            'status' => 'paused',
        ]);

        $state = HorizonQueueState::where('service_id', $service->id)->where('queue', 'redis.default')->first();
        $this->assertNotNull($state);
        $this->assertTrue($state->is_paused);
    }

    public function test_queue_resumed_event_updates_horizon_queue_state_to_not_paused(): void {
        Event::fake();

        $service = Service::create([
            'name' => 'svc',
            'api_key' => 'key',
            'base_url' => 'https://a.com',
            'status' => 'online',
        ]);
        HorizonQueueState::create([
            'service_id' => $service->id,
            'queue' => 'redis.default',
            'is_paused' => true,
        ]);

        $processor = new HorizonEventProcessor($this->createMock(AlertEngine::class));

        $processor->process($service, [
            'event_type' => 'QueueResumed',
            'queue' => 'redis.default',
            'status' => 'resumed',
        ]);

        $state = HorizonQueueState::where('service_id', $service->id)->where('queue', 'redis.default')->first();
        $this->assertNotNull($state);
        $this->assertFalse($state->is_paused);
    }
}
