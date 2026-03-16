<?php

namespace App\Http\Controllers\Horizon;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Services\HorizonApiProxyService;
use App\Services\HorizonJobResolverService;
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
     * The job resolver.
     *
     * @var HorizonJobResolverService
     */
    private HorizonJobResolverService $jobResolver;

    /**
     * Construct the job action controller.
     *
     * @param HorizonApiProxyService $horizonApi
     * @param HorizonJobResolverService $jobResolver
     */
    public function __construct(
        HorizonApiProxyService $horizonApi,
        HorizonJobResolverService $jobResolver
    ) {
        $this->horizonApi = $horizonApi;
        $this->jobResolver = $jobResolver;
    }

    /**
     * Retry a job.
     *
     * @param string $uuid
     * @return JsonResponse
     */
    public function retry(string $uuid): JsonResponse {
        $service = $this->jobResolver->getServiceForJob($uuid);
        if (! $service) {
            return \response()->json(['message' => 'Job not found'], 404);
        }

        $result = $this->horizonApi->retryJob($service, $uuid);
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

        $perPage = (int) ($validated['per_page'] ?? \config('horizonhub.jobs_per_page'));
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

        $serviceIdFilter = $validated['service_id'] ?? null;

        $servicesQuery = Service::query()->whereNotNull('base_url');
        if ($serviceIdFilter !== null && $serviceIdFilter !== '') {
            $servicesQuery->where('id', (int) $serviceIdFilter);
        }

        /** @var \Illuminate\Support\Collection<int, Service> $services */
        $services = $servicesQuery->get();

        if ($services->count() === 0) {
            return \response()->json($returnData);
        }

        $search = \trim((string) ($validated['search'] ?? ''));
        $dateFrom = $validated['date_from'] ?? null;
        $dateTo = $validated['date_to'] ?? null;

        $rows = [];

        foreach ($services as $service) {
            $apiQuery = [
                'starting_at' => 0,
                'limit' => $perPage,
            ];

            $apiResponse = $this->horizonApi->getFailedJobs($service, $apiQuery);
            $apiData = $apiResponse['data'] ?? null;

            if (! ($apiResponse['success'] ?? false) || ! \is_array($apiData)) {
                continue;
            }

            foreach ($apiData['jobs'] ?? [] as $job) {
                if (! \is_array($job)) {
                    continue;
                }

                $jobUuid = (string) $job['id'];
                if (empty($jobUuid)) {
                    continue;
                }

                $queue = (string) $job['queue'];
                $name = (string) $job['name'];

                if (! empty($search)) {
                    $haystack = "$queue $name $jobUuid";
                    if (\stripos($haystack, $search) === false) {
                        continue;
                    }
                }

                $failedAtRaw = $job['failed_at'] ?? null;
                $failedAtCarbon = null;
                if (\is_string($failedAtRaw) && !empty($failedAtRaw)) {
                    try {
                        $failedAtCarbon = new \Carbon\Carbon($failedAtRaw);
                    } catch (\Throwable $e) {
                        $failedAtCarbon = null;
                    }
                }

                $failedAtDate = $failedAtCarbon?->toDateString() ?? null;
                if ($dateFrom !== null && $failedAtDate !== null && $failedAtDate < $dateFrom) {
                    continue;
                }

                if ($dateTo !== null && $failedAtDate !== null && $failedAtDate > $dateTo) {
                    continue;
                }

                $rows[] = [
                    'uuid' => $jobUuid,
                    'service_id' => $service->id,
                    'service_name' => $service->name,
                    'queue' => $job['queue'] ?? null,
                    'name' => $job['name'] ?? ($job['displayName'] ?? $jobUuid),
                    'failed_at' => $failedAtCarbon,
                    'failed_at_formatted' => $failedAtCarbon ? $failedAtCarbon->format('Y-m-d H:i') : null,
                    'failed_at_iso' => $failedAtCarbon ? $failedAtCarbon->toIso8601String() : null,
                ];
            }
        }

        \usort($rows, static function (array $a, array $b): int {
            $aTime = $a['failed_at'];
            $bTime = $b['failed_at'];

            if ($aTime === null && $bTime === null) {
                return 0;
            }
            if ($aTime === null) {
                return 1;
            }
            if ($bTime === null) {
                return -1;
            }

            if ($aTime->eq($bTime)) {
                return 0;
            }

            return $aTime->lt($bTime) ? 1 : -1;
        });

        $total = \count($rows);
        $lastPage = $perPage > 0 ? (int) \max(1, \ceil($total / $perPage)) : 1;
        $offset = ($page - 1) * $perPage;
        $pageRows = $perPage > 0 ? \array_slice($rows, $offset, $perPage) : $rows;

        $data = [];
        foreach ($pageRows as $row) {
            unset($row['failed_at']);
            $data[] = $row;
        }

        $returnData['data'] = $data;
        $returnData['meta']['last_page'] = $lastPage;
        $returnData['meta']['total'] = $total;
        return \response()->json($returnData);
    }

    /**
     * Retry multiple jobs by ID (granular: one request per job, per-job result).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function retryBatch(Request $request): JsonResponse {
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
