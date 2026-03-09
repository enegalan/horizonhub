<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\HorizonFailedJob;
use App\Models\HorizonJob;
use App\Services\HorizonApiProxyService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class JobActionController extends Controller {
    /**
     * The Horizon API proxy service.
     *
     * @var HorizonApiProxyService
     */
    private HorizonApiProxyService $horizonApi;
    /**
     * Construct the job action controller.
     *
     * @param HorizonApiProxyService $horizonApi
     */
    public function __construct(
        HorizonApiProxyService $horizonApi
    ) {
        $this->horizonApi = $horizonApi;
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

        $result = $this->horizonApi->retryJob($service, $jobUuid);
        if (! $result['success']) {
            return \response()->json(
                ['message' => $result['message'] ?? 'Horizon API request failed'],
                $result['status'] ?? Response::HTTP_BAD_GATEWAY
            );
        }

        return \response()->json(['message' => 'Retry requested']);
    }
}
