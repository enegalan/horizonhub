<?php

namespace HorizonHub\Agent\Listeners;

use HorizonHub\Agent\Support\EventPayloadBuilder;
use HorizonHub\Agent\Support\HubClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\Events\JobFailed as IlluminateJobFailed;
use Illuminate\Queue\Events\JobProcessed as IlluminateJobProcessed;
use Illuminate\Queue\Events\JobProcessing as IlluminateJobProcessing;
use Laravel\Horizon\Events\JobDeleted;
use Laravel\Horizon\Events\JobFailed;
use Laravel\Horizon\Events\JobProcessed;
use Laravel\Horizon\Events\JobProcessing;
use Laravel\Horizon\Events\JobReserved;
use Laravel\Horizon\Events\QueuePaused;
use Laravel\Horizon\Events\QueueResumed;
use Laravel\Horizon\Events\SupervisorLooped;

class PushHorizonEventToHub {
    public function __construct(
        private readonly HubClient $client
    ) {}

    public function handle(object $event): void {
        $payload = $this->buildPayload($event);
        if ($payload === null) {
            return;
        }

        $payload['service_name'] = config('horizon_hub.service_name');

        try {
            $this->client->push($payload);
        } catch (\Throwable $e) {
            Log::warning('Horizon Hub Agent: failed to push event', [
                'event' => $event::class,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function buildPayload(object $event): ?array {
        return match ($event::class) {
            JobProcessed::class, IlluminateJobProcessed::class => EventPayloadBuilder::fromJobProcessed($event),
            JobDeleted::class => EventPayloadBuilder::fromJobDeleted($event),
            JobFailed::class, IlluminateJobFailed::class => EventPayloadBuilder::fromJobFailed($event),
            JobProcessing::class, IlluminateJobProcessing::class => EventPayloadBuilder::fromJobProcessing($event),
            JobReserved::class => EventPayloadBuilder::fromJobReserved($event),
            SupervisorLooped::class => EventPayloadBuilder::fromSupervisorLooped($event),
            QueuePaused::class => EventPayloadBuilder::fromQueuePaused($event),
            QueueResumed::class => EventPayloadBuilder::fromQueueResumed($event),
            default => null,
        };
    }
}
