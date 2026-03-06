<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\HorizonFailedJob;
use App\Models\HorizonJob;
use App\Services\AgentProxyService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class JobActionController extends Controller {
    /**
     * The agent proxy service.
     *
     * @var AgentProxyService
     */
    private AgentProxyService $agentProxy;
    /**
     * Construct the job action controller.
     *
     * @param AgentProxyService $agentProxy
     */
    public function __construct(
        AgentProxyService $agentProxy
    ) {
        $this->agentProxy = $agentProxy;
    }

    /**
     * Retry a job.
     *
     * @param string $id
     * @return JsonResponse
     */
    public function retry(string $id): JsonResponse {
        $job = HorizonJob::find($id) ?? HorizonFailedJob::find($id);
        if (! $job) {
            return \response()->json(['message' => 'Job not found'], 404);
        }

        $service = $job->service;
        $jobUuid = $job->job_uuid;

        $result = $this->agentProxy->retryJob($service, $jobUuid);
        if (! $result['success']) {
            return \response()->json(
                ['message' => $result['message'] ?? 'Agent request failed'],
                $result['status'] ?? Response::HTTP_BAD_GATEWAY
            );
        }

        return \response()->json(['message' => 'Retry requested']);
    }

    /**
     * Delete a job.
     *
     * @param string $id
     * @return JsonResponse
     */
    public function delete(string $id): JsonResponse {
        $job = HorizonJob::find($id) ?? HorizonFailedJob::find($id);
        if (! $job) {
            return \response()->json(['message' => 'Job not found'], 404);
        }

        $service = $job->service;
        $jobUuid = $job->job_uuid;

        $result = $this->agentProxy->deleteJob($service, $jobUuid);
        if (! $result['success']) {
            return \response()->json(
                ['message' => $result['message'] ?? 'Agent request failed'],
                $result['status'] ?? Response::HTTP_BAD_GATEWAY
            );
        }

        return \response()->json(['message' => 'Delete requested']);
    }
}
