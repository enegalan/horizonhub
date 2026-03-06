<?php

namespace Tests\Feature\Api;

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
        $body = json_encode([
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
        $response->assertJson(['accepted' => 1]);
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
        $body = json_encode($payload);
        $timestamp = (string) time();
        $signature = 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $body, $service->api_key);

        $response = $this->postJson('/api/v1/events', $payload, [
            'X-Api-Key' => $service->api_key,
            'X-Hub-Timestamp' => $timestamp,
            'X-Hub-Signature' => $signature,
        ]);

        $response->assertStatus(202);
        $response->assertJson(['accepted' => 1]);

        $state = HorizonSupervisorState::where('service_id', $service->id)->where('name', 'supervisor-default')->first();
        $this->assertNotNull($state);
        $this->assertSame('supervisor-default', $state->name);
        $this->assertTrue($state->last_seen_at->isToday());
    }
}
