<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\HorizonQueueState;
use App\Models\Service;
use App\Services\AgentProxyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class QueueActionController extends Controller {
    /**
     * The agent proxy service.
     *
     * @var AgentProxyService
     */
    private AgentProxyService $agentProxy;
    /**
     * Construct the queue action controller.
     *
     * @param AgentProxyService $agentProxy
     */
    public function __construct(AgentProxyService $agentProxy) {
        $this->agentProxy = $agentProxy;
    }

    /**
     * Pause a queue.
     *
     * @param Request $request
     * @param string $name
     * @return JsonResponse
     */
    public function pause(Request $request, string $name): JsonResponse {
        $serviceId = (int) $request->input('service_id');
        if (! $serviceId) {
            return \response()->json(['message' => 'service_id required'], 422);
        }

        $service = Service::find($serviceId);
        if (! $service) {
            return \response()->json(['message' => 'Service not found'], 404);
        }

        $result = $this->agentProxy->pauseQueue($service, $name);
        if (! ($result['success'] ?? false)) {
            return \response()->json(
                ['message' => $result['message'] ?? 'Agent request failed'],
                $result['status'] ?? Response::HTTP_BAD_GATEWAY
            );
        }

        HorizonQueueState::updateOrCreate(
            ['service_id' => $serviceId, 'queue' => $name],
            ['is_paused' => true]
        );

        return \response()->json(['message' => 'Queue pause requested']);
    }

    /**
     * Resume a queue.
     *
     * @param Request $request
     * @param string $name
     * @return JsonResponse
     */
    public function resume(Request $request, string $name): JsonResponse {
        $serviceId = (int) $request->input('service_id');
        if (! $serviceId) {
            return \response()->json(['message' => 'service_id required'], 422);
        }

        $service = Service::find($serviceId);
        if (! $service) {
            return \response()->json(['message' => 'Service not found'], 404);
        }

        $result = $this->agentProxy->resumeQueue($service, $name);
        if (! ($result['success'] ?? false)) {
            return \response()->json(
                ['message' => $result['message'] ?? 'Agent request failed'],
                $result['status'] ?? Response::HTTP_BAD_GATEWAY
            );
        }

        HorizonQueueState::updateOrCreate(
            ['service_id' => $serviceId, 'queue' => $name],
            ['is_paused' => false]
        );

        return \response()->json(['message' => 'Queue resume requested']);
    }
}
