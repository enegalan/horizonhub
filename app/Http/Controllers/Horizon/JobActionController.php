<?php

namespace App\Http\Controllers\Horizon;

use App\Http\Controllers\Controller;
use App\Models\Service;
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

        $jobEntries = [];

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
                $jobId = (string) $job['id'];

                if ( empty($jobId) ) {
                    continue;
                }

                $jobEntries[] = [
                    'service_id' => $service->id,
                    'job_uuid' => $jobId,
                ];
            }
        }

        if ($jobEntries === []) {
            return \response()->json($returnData);
        }

        $uuids = [];
        foreach ($jobEntries as $entry) {
            $uuids[] = $entry['job_uuid'];
        }
        $uuids = \array_values(\array_unique($uuids));

        $jobs = HorizonJob::with('service')
            ->where('status', 'failed')
            ->whereIn('job_uuid', $uuids)
            ->get()
            ->keyBy('job_uuid');

        $search = \trim((string) ($validated['search'] ?? ''));
        $dateFrom = $validated['date_from'] ?? null;
        $dateTo = $validated['date_to'] ?? null;

        $rows = [];

        foreach ($jobEntries as $entry) {
            /** @var HorizonJob|null $job */
            $job = $jobs->get($entry['job_uuid']);

            if ($job === null) {
                continue;
            }

            if ($serviceIdFilter !== null && $serviceIdFilter !== '' && (int) $job->service_id !== (int) $serviceIdFilter) {
                continue;
            }

            if ($search !== '') {
                $matches = false;

                if (\stripos((string) $job->queue, $search) !== false) {
                    $matches = true;
                } elseif (\stripos((string) $job->name, $search) !== false) {
                    $matches = true;
                } elseif (\stripos((string) $job->job_uuid, $search) !== false) {
                    $matches = true;
                }

                if (! $matches) {
                    continue;
                }
            }

            if ($dateFrom !== null && $job->failed_at !== null && $job->failed_at->toDateString() < $dateFrom) {
                continue;
            }

            if ($dateTo !== null && $job->failed_at !== null && $job->failed_at->toDateString() > $dateTo) {
                continue;
            }

            $rows[] = [
                'id' => $job->id,
                'service_name' => $job->service?->name,
                'queue' => $job->queue,
                'name' => $job->name ?? $job->job_uuid,
                'failed_at' => $job->failed_at,
                'failed_at_formatted' => $job->failed_at?->format('Y-m-d H:i'),
                'failed_at_iso' => $job->failed_at?->toIso8601String(),
                'has_service' => $job->service !== null,
            ];
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
     * @param Request $request
     * @return JsonResponse
     */
    public function retryBatch(Request $request): JsonResponse {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'min:1'],
        ]);
        $ids = \array_values(\array_unique($validated['ids']));
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
