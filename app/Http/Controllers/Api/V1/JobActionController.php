<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RetryJobsRequest;
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

    /**
     * Retry multiple jobs by ID (granular: one request per job, per-job result).
     *
     * @param RetryJobsRequest $request
     * @return JsonResponse
     */
    public function retryBatch(RetryJobsRequest $request): JsonResponse {
        $ids = \array_values(\array_unique($request->validated('ids')));
        $results = [];
        $succeeded = 0;
        $failed = 0;
        foreach ($ids as $id) {
            $job = HorizonJob::find($id) ?? HorizonFailedJob::find($id);
            if (! $job) {
                $results[] = ['id' => $id, 'success' => false, 'message' => 'Job not found'];
                $failed++;
                continue;
            }
            $service = $job->service;
            if (! $service) {
                $results[] = ['id' => $id, 'success' => false, 'message' => 'Service not found'];
                $failed++;
                continue;
            }
            $result = $this->horizonApi->retryJob($service, $job->job_uuid);
            if ($result['success']) {
                $results[] = ['id' => $id, 'success' => true];
                $succeeded++;
            } else {
                $results[] = [
                    'id' => $id,
                    'success' => false,
                    'message' => $result['message'] ?? 'Horizon API request failed',
                ];
                $failed++;
            }
        }
        return \response()->json([
            'requested' => count($ids),
            'succeeded' => $succeeded,
            'failed' => $failed,
            'results' => $results,
        ]);
    }
}
