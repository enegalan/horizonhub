<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreEventRequest;
use App\Models\Service;
use App\Services\HorizonEventProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class EventController extends Controller {
    /**
     * The horizon event processor.
     *
     * @var HorizonEventProcessor
     */
    private HorizonEventProcessor $processor;

    /**
     * Construct the event controller.
     *
     * @param HorizonEventProcessor $processor
     */
    public function __construct(
        HorizonEventProcessor $processor
    ) {
        $this->processor = $processor;
    }

    /**
     * Store an event.
     *
     * @param StoreEventRequest $request
     * @return JsonResponse
     */
    public function store(StoreEventRequest $request): JsonResponse {
        /** @var Service $service */
        $service = $request->attributes->get('horizonhub_service');
        if (! $service) {
            return \response()->json(['message' => 'Service not resolved'], 401);
        }

        $service->update(['last_seen_at' => \now(), 'status' => 'online']);
        $events = $request->getEvents();

        foreach ($events as $event) {
            try {
                $this->processor->process($service, $event);
            } catch (\Throwable $e) {
                Log::error('Horizon Hub: failed to process event', [
                    'service_id' => $service->id,
                    'event' => $event,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return \response()->json(['accepted' => \count($events)], 202);
    }
}
