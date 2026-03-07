<?php

namespace Tests\Feature\Api;

use App\Models\HorizonQueueState;
use App\Models\HorizonSupervisorState;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventsApiTest extends TestCase {
    use RefreshDatabase;

    public function test_events_endpoint_requires_signature(): void {
        $response = $this->postJson('/api/v1/events', [
            'event_type' => 'JobProcessed',
            'job_id' => 'abc',
            'queue' => 'default',
        ]);
        $response->assertStatus(401);
    }

    public function test_events_endpoint_accepts_signed_request(): void {
        $service = Service::create([
            'name' => 'test-service',
            'api_key' => 'test-api-key-64-chars-long-enough-to-meet-requirementxxxxxxxx',
            'base_url' => 'https://example.com',
            'status' => 'online',
        ]);
        $body = \json_encode([
            'event_type' => 'JobProcessed',
            'job_id' => 'job-123',
            'queue' => 'redis.default',
            'status' => 'processed',
        ]);
        $timestamp = (string) time();
        $signature = 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $body, $service->api_key);

        $response = $this->postJson('/api/v1/events', json_decode($body, true), [
            'X-Api-Key' => $service->api_key,
            'X-Hub-Timestamp' => $timestamp,
            'X-Hub-Signature' => $signature,
        ]);
        $response->assertStatus(202);
        $response->assertJson(['accepted' => 1, 'processed' => 1]);
    }

    public function test_events_response_includes_processed_and_failed_when_some_events_fail(): void {
        $service = Service::create([
            'name' => 'test-service',
            'api_key' => 'test-api-key-64-chars-long-enough-to-meet-requirementxxxxxxxx',
            'base_url' => 'https://example.com',
            'status' => 'online',
        ]);
        $payload = [
            'events' => [
                [
                    'event_type' => 'JobProcessed',
                    'job_id' => 'job-1',
                    'queue' => 'default',
                    'status' => 'processed',
                ],
                [
                    'event_type' => 'JobProcessed',
                    'job_id' => 'job-2',
                    'queue' => 'default',
                    'status' => 'processed',
                ],
            ],
        ];
        $body = \json_encode($payload);
        $timestamp = (string) \time();
        $signature = 'sha256=' . \hash_hmac('sha256', "$timestamp.$body", $service->api_key);

        $this->mock(\App\Services\HorizonEventProcessor::class, function ($mock) {
            $mock->shouldReceive('process')
                ->once()
                ->andReturn(null);
            $mock->shouldReceive('process')
                ->once()
                ->andThrow(new \RuntimeException('Test failure'));
        });

        $response = $this->postJson('/api/v1/events', $payload, [
            'X-Api-Key' => $service->api_key,
            'X-Hub-Timestamp' => $timestamp,
            'X-Hub-Signature' => $signature,
        ]);

        $response->assertStatus(202);
        $response->assertJson([
            'accepted' => 2,
            'processed' => 1,
            'failed' => [
                ['index' => 1, 'error' => 'Processing failed'],
            ],
        ]);
    }

    public function test_supervisor_looped_creates_or_updates_supervisor_state(): void {
        $service = Service::create([
            'name' => 'demo-service',
            'api_key' => 'test-api-key-64-chars-long-enough-to-meet-requirementxxxxxxxx',
            'base_url' => 'https://demo.example.com',
            'status' => 'online',
        ]);
        $payload = [
            'event_type' => 'SupervisorLooped',
            'queue' => 'supervisor-default',
            'status' => 'looped',
        ];
        $body = \json_encode($payload);
        $timestamp = (string) \time();
        $signature = 'sha256=' . \hash_hmac('sha256', "$timestamp.$body", $service->api_key);

        $response = $this->postJson('/api/v1/events', $payload, [
            'X-Api-Key' => $service->api_key,
            'X-Hub-Timestamp' => $timestamp,
            'X-Hub-Signature' => $signature,
        ]);

        $response->assertStatus(202);
        $response->assertJson(['accepted' => 1, 'processed' => 1]);

        $state = HorizonSupervisorState::where('service_id', $service->id)->where('name', 'supervisor-default')->first();
        $this->assertNotNull($state);
        $this->assertSame('supervisor-default', $state->name);
        $this->assertTrue($state->last_seen_at->isToday());
    }

    public function test_queue_paused_via_api_creates_or_updates_queue_state(): void {
        $service = Service::create([
            'name' => 'demo-service',
            'api_key' => 'test-api-key-64-chars-long-enough-to-meet-requirementxxxxxxxx',
            'base_url' => 'https://demo.example.com',
            'status' => 'online',
        ]);
        $payload = [
            'event_type' => 'QueuePaused',
            'queue' => 'redis.default',
            'status' => 'paused',
        ];
        $body = \json_encode($payload);
        $timestamp = (string) \time();
        $signature = 'sha256=' . \hash_hmac('sha256', "$timestamp.$body", $service->api_key);

        $response = $this->postJson('/api/v1/events', $payload, [
            'X-Api-Key' => $service->api_key,
            'X-Hub-Timestamp' => $timestamp,
            'X-Hub-Signature' => $signature,
        ]);

        $response->assertStatus(202);
        $response->assertJson(['accepted' => 1, 'processed' => 1]);

        $state = HorizonQueueState::where('service_id', $service->id)->where('queue', 'redis.default')->first();
        $this->assertNotNull($state);
        $this->assertTrue($state->is_paused);
    }

    public function test_queue_resumed_via_api_updates_queue_state(): void {
        $service = Service::create([
            'name' => 'demo-service',
            'api_key' => 'test-api-key-64-chars-long-enough-to-meet-requirementxxxxxxxx',
            'base_url' => 'https://demo.example.com',
            'status' => 'online',
        ]);
        HorizonQueueState::create([
            'service_id' => $service->id,
            'queue' => 'redis.default',
            'is_paused' => true,
        ]);
        $payload = [
            'event_type' => 'QueueResumed',
            'queue' => 'redis.default',
            'status' => 'resumed',
        ];
        $body = \json_encode($payload);
        $timestamp = (string) \time();
        $signature = 'sha256=' . \hash_hmac('sha256', "$timestamp.$body", $service->api_key);

        $response = $this->postJson('/api/v1/events', $payload, [
            'X-Api-Key' => $service->api_key,
            'X-Hub-Timestamp' => $timestamp,
            'X-Hub-Signature' => $signature,
        ]);

        $response->assertStatus(202);
        $response->assertJson(['accepted' => 1, 'processed' => 1]);

        $state = HorizonQueueState::where('service_id', $service->id)->where('queue', 'redis.default')->first();
        $this->assertNotNull($state);
        $this->assertFalse($state->is_paused);
    }

    public function test_after_queue_paused_event_queues_page_shows_paused_badge(): void {
        $service = Service::create([
            'name' => 'Demo Orders',
            'api_key' => 'test-api-key-64-chars-long-enough-to-meet-requirementxxxxxxxx',
            'base_url' => 'https://demo.example.com',
            'status' => 'online',
        ]);
        $payload = [
            'event_type' => 'QueuePaused',
            'queue' => 'redis.default',
            'status' => 'paused',
        ];
        $body = \json_encode($payload);
        $timestamp = (string) \time();
        $signature = 'sha256=' . \hash_hmac('sha256', "$timestamp.$body", $service->api_key);

        $this->postJson('/api/v1/events', $payload, [
            'X-Api-Key' => $service->api_key,
            'X-Hub-Timestamp' => $timestamp,
            'X-Hub-Signature' => $signature,
        ])->assertStatus(202);

        $response = $this->get(route('horizon.queues.index'));
        $response->assertStatus(200);
        $response->assertSee('redis.default', false);
        $response->assertSee('Paused', false);
        $response->assertSee('Demo Orders', false);
    }
}
