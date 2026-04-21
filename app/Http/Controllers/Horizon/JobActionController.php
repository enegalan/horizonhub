<?php

namespace App\Http\Controllers\Horizon;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Rules\RetryModalDateFilter;
use App\Services\Horizon\HorizonApiProxyService;
use App\Services\Horizon\HorizonJobListService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class JobActionController extends Controller
{
    /**
     * The Horizon API proxy service.
     */
    private HorizonApiProxyService $horizonApi;

    /**
     * The job list service.
     */
    private HorizonJobListService $jobList;

    /**
     * The constructor.
     */
    public function __construct(HorizonApiProxyService $horizonApi, HorizonJobListService $jobList)
    {
        $this->horizonApi = $horizonApi;
        $this->jobList = $jobList;
    }

    /**
     * List failed jobs for the retry modal (with filters).
     */
    public function failedList(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'service_id' => 'nullable|integer|exists:services,id',
            'service_ids' => 'nullable|array',
            'service_ids.*' => 'integer|exists:services,id',
            'search' => 'nullable|string|max:255',
            'date_from' => ['nullable', 'string', 'max:32', new RetryModalDateFilter],
            'date_to' => ['nullable', 'string', 'max:32', new RetryModalDateFilter],
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'selection' => ['nullable', 'string', Rule::in(['page', 'all'])],
        ]);

        $selection = (string) ($validated['selection'] ?? 'page');

        $perPage = (int) ($validated['per_page'] ?? config('horizonhub.jobs_per_page'));
        $page = (int) ($validated['page'] ?? 1);

        if ($page < 1) {
            $page = 1;
        }

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
        } elseif (isset($validated['service_id']) && $validated['service_id'] !== null && $validated['service_id'] !== '') {
            $serviceIds = [(int) $validated['service_id']];
        }

        $servicesQuery = Service::query()->whereNotNull('base_url');

        if (\count($serviceIds) > 0) {
            $servicesQuery->whereIn('id', $serviceIds);
        }

        /** @var Collection<int, Service> $services */
        $services = $servicesQuery->get();

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
    public function retry(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'uuid' => ['required', 'string'],
            'service_id' => ['required', 'integer', 'exists:services,id'],
        ]);
        $uuid = $validated['uuid'];
        $serviceId = $validated['service_id'];

        $service = Service::find($serviceId);

        if (! $service) {
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
    public function retryBatch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'jobs' => ['required', 'array'],
            'jobs.*.id' => ['required', 'string'],
            'jobs.*.service_id' => ['required', 'integer', 'exists:services,id'],
        ]);
        $jobs = $validated['jobs'];
        $results = [];
        $succeeded = 0;
        $failed = 0;

        $servicesById = Service::query()
            ->whereNotNull('base_url')
            ->whereIn('id', \array_values(\array_unique(\array_column($jobs, 'service_id'))))
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
