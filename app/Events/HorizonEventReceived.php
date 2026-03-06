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

    /**
     * The event type.
     *
     * @var string
     */
    public string $eventType;

    /**
     * The service ID.
     *
     * @var int
     */
    public int $serviceId;

    /**
     * The job ID.
     *
     * @var int|null
     */
    public ?int $jobId;

    /**
     * The payload.
     *
     * @var array
     */
    public array $payload;

    /**
     * Construct the horizon event received event.
     *
     * @param string $eventType
     * @param int $serviceId
     * @param int|null $jobId
     * @param array $payload
     */
    public function __construct(
        string $eventType,
        int $serviceId,
        ?int $jobId,
        array $payload
    ) {
        $this->eventType = $eventType;
        $this->serviceId = $serviceId;
        $this->jobId = $jobId;
        $this->payload = $payload;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array
     */
    public function broadcastOn(): array {
        return [
            new Channel('horizon-hub.dashboard'),
        ];
    }

    /**
     * Get the event name.
     *
     * @return string
     */
    public function broadcastAs(): string {
        return 'HorizonEvent';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith(): array {
        return [
            'event_type' => $this->eventType,
            'service_id' => $this->serviceId,
            'job_id' => $this->jobId,
            'payload' => $this->payload,
        ];
    }
}
