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
     * The job UUID.
     *
     * @var string|null
     */
    public ?string $jobUuid;

    /**
     * The payload.
     *
     * @var array
     */
    public array $payload;

    /**
     * Construct the Horizon event received event.
     *
     * @param string $eventType
     * @param int $serviceId
     * @param string|null $jobUuid
     * @param array $payload
     */
    public function __construct(
        string $eventType,
        int $serviceId,
        ?string $jobUuid,
        array $payload
    ) {
        $this->eventType = $eventType;
        $this->serviceId = $serviceId;
        $this->jobUuid = $jobUuid;
        $this->payload = $payload;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array
     */
    public function broadcastOn(): array {
        return [
            new Channel('horizonhub.dashboard'),
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
            'job_uuid' => $this->jobUuid,
            'payload' => $this->payload,
        ];
    }
}
