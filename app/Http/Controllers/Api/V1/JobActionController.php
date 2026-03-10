<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RetryJobsRequest;
use App\Models\HorizonFailedJob;
use App\Models\HorizonJob;
use App\Services\HorizonApiProxyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
     * List failed jobs for the retry modal (with filters).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function failedList(Request $request): JsonResponse {
        $validated = $request->validate([
            'service_id' => 'nullable|integer|exists:services,id',
            'search' => 'nullable|string|max:255',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = HorizonJob::with('service')
            ->where('status', 'failed')
            ->orderByDesc('failed_at');

        if (! empty($validated['service_id'] ?? null)) {
            $query->where('service_id', (int) $validated['service_id']);
        }

        $search = \trim((string) ($validated['search'] ?? ''));
        if ($search !== '') {
            $term = "%$search%";
            $query->where(function ($q) use ($term): void {
                $q->where('queue', 'like', $term)
                    ->orWhere('name', 'like', $term)
                    ->orWhere('job_uuid', 'like', $term)
                    ->orWhere('payload->displayName', 'like', $term);
            });
        }

        if (! empty($validated['date_from'] ?? null)) {
            $query->whereDate('failed_at', '>=', $validated['date_from']);
        }

        if (! empty($validated['date_to'] ?? null)) {
            $query->whereDate('failed_at', '<=', $validated['date_to']);
        }

        $perPage = (int) ($validated['per_page'] ?? \config('horizonhub.jobs_per_page'));
        $paginator = $query->paginate($perPage);

        $data = \collect($paginator->items())->map(static function (HorizonJob $j): array {
            return [
                'id' => $j->id,
                'service_name' => $j->service?->name,
                'queue' => $j->queue,
                'name' => $j->name ?? $j->job_uuid,
                'failed_at_formatted' => $j->failed_at?->format('Y-m-d H:i'),
                'failed_at_iso' => $j->failed_at?->toIso8601String(),
                'has_service' => $j->service !== null,
            ];
        })->all();

        return \response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * Clean jobs and failed jobs.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function clean(Request $request): JsonResponse {
        $validated = $request->validate([
            'service_id' => 'nullable|integer|exists:services,id',
            'status' => 'nullable|string|in:processed,failed,processing',
            'job_type' => 'nullable|string|max:255',
            'preview' => 'sometimes|boolean',
        ]);

        $serviceId = $validated['service_id'] ?? null;
        $status = $validated['status'] ?? null;
        $jobType = $validated['job_type'] ?? null;

        $jobQuery = HorizonJob::query();

        if ($serviceId !== null && $serviceId !== '') {
            $jobQuery->where('service_id', (int) $serviceId);
        }

        if ($status !== null && $status !== '') {
            $jobQuery->where('status', $status);
        }

        if ($jobType !== null && $jobType !== '') {
            $jobQuery->where('name', 'like', '%' . $jobType . '%');
        }

        $count = $jobQuery->count();

        if (! $request->boolean('preview')) {
            $jobQuery->delete();
        }

        $failedCount = 0;

        if ($status === null || $status === '' || $status === 'failed') {
            $failedQuery = HorizonFailedJob::query();

            if ($serviceId !== null && $serviceId !== '') {
                $failedQuery->where('service_id', (int) $serviceId);
            }

            $failedCount = $failedQuery->count();

            if (! $request->boolean('preview')) {
                $failedQuery->delete();
            }
        }

        $total = $count + $failedCount;

        return \response()->json([
            'deleted_jobs' => $count,
            'deleted_failed_jobs' => $failedCount,
            'total_deleted' => $total,
        ]);
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
