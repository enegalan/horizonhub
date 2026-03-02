<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class HorizonEventReceived implements ShouldBroadcast {
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly string $eventType,
        public readonly int $serviceId,
        public readonly ?int $jobId,
        public readonly array $payload
    ) {}

    public function broadcastOn(): array {
        return [
            new Channel('horizon-hub.dashboard'),
        ];
    }

    public function broadcastAs(): string {
        return 'HorizonEvent';
    }

    public function broadcastWith(): array {
        return [
            'event_type' => $this->eventType,
            'service_id' => $this->serviceId,
            'job_id' => $this->jobId,
            'payload' => $this->payload,
        ];
    }
}
