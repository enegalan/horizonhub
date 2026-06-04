<?php

namespace App\Http\Controllers\Horizon;

use App\Contracts\HorizonHubStore;
use App\Http\Controllers\Controller;
use App\Http\Requests\Horizon\FailedJobsListRequest;
use App\Http\Requests\Horizon\RetryBatchRequest;
use App\Http\Requests\Horizon\RetryJobRequest;
use App\Models\Service;
use App\Services\Horizon\Contracts\HorizonClientApi;
use App\Services\Jobs\JobListService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

class JobActionController extends Controller
{
    /**
     * The Horizon API client.
     */
    private HorizonClientApi $horizonApi;

    /**
     * The job list service.
     */
    private JobListService $jobList;

    /**
     * The store.
     */
    private HorizonHubStore $store;

    /**
     * The constructor.
     *
     * @param HorizonClientApi $horizonApi The Horizon API client.
     * @param JobListService $jobList The job list service.
     * @param HorizonHubStore $store The store.
     */
    public function __construct(HorizonClientApi $horizonApi, JobListService $jobList, HorizonHubStore $store)
    {
        $this->horizonApi = $horizonApi;
        $this->jobList = $jobList;
        $this->store = $store;
    }

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
            ],
        ];

        $serviceIds = [];

        if (! empty($validated['service_ids']) && \is_array($validated['service_ids'])) {
            $serviceIds = \array_values(\array_unique(\array_map('intval', $validated['service_ids'])));
        }

        $tags = $request->query('service_tag', []);

        $services = $this->store->enabledServices();

        if (\count($serviceIds) > 0) {
            $services = $services->filter(static fn (Service $service): bool => \in_array((int) $service->id, $serviceIds, true))->values();
        }

        if (\count($tags) > 0) {
            $tagIds = $this->store->matchingTagServiceIds($tags);
            $services = $services->filter(static fn (Service $service): bool => \in_array((int) $service->id, $tagIds, true))->values();
        }

        /** @var Collection<int, Service> $services */
        if ($services->count() === 0) {
            if ($selection === 'all') {
                return \response()->json([
                    'jobs' => [],
                    'meta' => ['total' => 0],
                ]);
            }

            return \response()->json($returnData);
        }

        $search = \trim((string) ($validated['search'] ?? ''));
        $dateFrom = $validated['date_from'] ?? null;
        $dateTo = $validated['date_to'] ?? null;

        if ($selection === 'all') {
            $pageData = $this->jobList->buildFailedJobsRetryModalPage(
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
                'meta' => ['total' => $pageData['total']],
            ]);
        }

        $pageData = $this->jobList->buildFailedJobsRetryModalPage(
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

        $service = $this->store->findService((int) $serviceId);

        if ($service === null) {
            return \response()->json(['message' => 'Service not found'], 404);
        }

        $result = $this->horizonApi->retryJob($service, $uuid);

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

        $servicesById = $this->store
            ->servicesByIds(\array_values(\array_unique(\array_column($jobs, 'service_id'))))
            ->keyBy('id');

        foreach ($jobs as $item) {
            $id = (string) $item['id'];
            $service = $servicesById->get((int) $item['service_id']);

            if ($service === null) {
                $results[] = ['id' => $id, 'success' => false, 'message' => 'Service not found'];
                $failed++;

                continue;
            }
            $result = $this->horizonApi->retryJob($service, $id);

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
