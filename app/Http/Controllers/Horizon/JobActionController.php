<?php

namespace App\Http\Controllers\Horizon;

use App\Http\Controllers\Controller;
use App\Http\Requests\Horizon\FailedJobsListRequest;
use App\Http\Requests\Horizon\RetryBatchRequest;
use App\Http\Requests\Horizon\RetryJobRequest;
use App\Models\Service;
use App\Services\Horizon\HorizonClientApiService;
use App\Services\Jobs\JobListService;
use App\Support\ArrayFormatter;
use App\Support\StringFormatter;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class JobActionController extends Controller
{
    /**
     * List failed jobs for the retry modal (with filters).
     */
    public function failedList(FailedJobsListRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $selection = (string) ($validated['selection'] ?? 'page');

        $perPage = (int) ($validated['per_page'] ?? config('horizonhub.jobs_per_page'));
        $page = max(1, (int) ($validated['page'] ?? 1));
        $returnData = [
            'data' => [],
            'meta' => [
                'current_page' => $page,
                'last_page' => 1,
                'per_page' => $perPage,
                'total' => 0,
                'results_may_be_truncated' => false,
            ],
        ];

        $serviceIds = ArrayFormatter::orEmpty($validated['service_ids'] ?? null);

        $tags = ArrayFormatter::orEmpty($validated['service_tag'] ?? null);

        $servicesQuery = Service::enabled();

        if (\count($serviceIds) > 0) {
            $servicesQuery->whereIn('id', $serviceIds);
        }

        if (\count($tags) > 0) {
            $servicesQuery->matchingTags($tags);
        }

        $services = $servicesQuery->get();

        if ($services->count() === 0) {
            if ($selection === 'all') {
                return \response()->json([
                    'jobs' => [],
                    'meta' => [
                        'total' => 0,
                        'results_may_be_truncated' => false,
                    ],
                ]);
            }

            return \response()->json($returnData);
        }

        $search = StringFormatter::trim($validated['search'] ?? null);
        $dateFrom = $validated['date_from'] ?? null;
        $dateTo = $validated['date_to'] ?? null;

        if ($selection === 'all') {
            $pageData = JobListService::buildFailedJobsRetryModalPage(
                $services,
                $search,
                $dateFrom,
                $dateTo,
                1,
                \PHP_INT_MAX,
            );
            $jobs = [];

            foreach ($pageData['rows'] as $row) {
                $jobs[] = [
                    'id' => (string) $row['uuid'],
                    'service_id' => (int) $row['service_id'],
                ];
            }

            return \response()->json([
                'jobs' => $jobs,
                'meta' => [
                    'total' => $pageData['total'],
                    'results_may_be_truncated' => $pageData['results_may_be_truncated'],
                ],
            ]);
        }

        $pageData = JobListService::buildFailedJobsRetryModalPage(
            $services,
            $search,
            $dateFrom,
            $dateTo,
            $page,
            $perPage,
        );

        $returnData['data'] = $pageData['rows'];
        $returnData['meta']['last_page'] = $pageData['last_page'];
        $returnData['meta']['total'] = $pageData['total'];
        $returnData['meta']['results_may_be_truncated'] = $pageData['results_may_be_truncated'];

        return \response()->json($returnData);
    }

    /**
     * Retry a job.
     */
    public function retry(RetryJobRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $uuid = $validated['uuid'];
        $serviceId = $validated['service_id'];

        $service = Service::find($serviceId);

        if (! $service) {
            return \response()->json(['message' => 'Service not found'], 404);
        }

        $result = HorizonClientApiService::retryJob($service, $uuid);

        if (! $result['success']) {
            return \response()->json(
                ['message' => $result['message'] ?? 'Horizon API request failed'],
                $result['status'] ?? Response::HTTP_BAD_GATEWAY,
            );
        }

        return \response()->json(['message' => 'Retry requested']);
    }

    /**
     * Retry multiple jobs by ID
     */
    public function retryBatch(RetryBatchRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $jobs = $validated['jobs'];
        $results = [];
        $succeeded = 0;
        $failed = 0;

        $servicesById = Service::whereIn('id', \array_values(\array_unique(\array_column($jobs, 'service_id'))))
            ->get()
            ->keyBy('id');

        foreach ($jobs as $item) {
            $id = (string) $item['id'];
            $service = $servicesById->get((int) $item['service_id']);

            if (! $service) {
                $results[] = ['id' => $id, 'success' => false, 'message' => 'Service not found'];
                $failed++;

                continue;
            }
            $result = HorizonClientApiService::retryJob($service, $id);

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
            'requested' => \count($jobs),
            'succeeded' => $succeeded,
            'failed' => $failed,
            'results' => $results,
        ]);
    }
}
