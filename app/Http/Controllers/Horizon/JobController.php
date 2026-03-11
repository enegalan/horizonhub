<?php

namespace App\Http\Controllers\Horizon;

use App\Http\Controllers\Controller;
use App\Models\HorizonFailedJob;
use App\Models\HorizonJob;
use App\Models\Service;
use App\Services\HorizonApiProxyService;
use App\Services\HorizonSyncService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class JobController extends Controller {
    private HorizonSyncService $horizonSync;
    private HorizonApiProxyService $horizonApi;

    public function __construct(HorizonSyncService $horizonSync, HorizonApiProxyService $horizonApi) {
        $this->horizonSync = $horizonSync;
        $this->horizonApi = $horizonApi;
    }

    /**
     * Show the jobs index.
     *
     * @param Request $request
     * @return View
     */
    public function index(Request $request): View {
        $serviceFilter = (string) $request->query('serviceFilter', '');
        $statusFilter = (string) $request->query('statusFilter', '');
        $search = (string) $request->query('search', '');

        $this->horizonSync->syncRecentJobs($serviceFilter !== '' ? (int) $serviceFilter : null);

        $query = HorizonJob::query()
            ->with('service')
            ->orderByDesc('created_at');

        if ($serviceFilter !== '') {
            $query->where('service_id', (int) $serviceFilter);
        }

        if ($statusFilter !== '') {
            $query->where('status', $statusFilter);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('queue', 'like', '%' . $search . '%')
                    ->orWhere('name', 'like', '%' . $search . '%')
                    ->orWhere('job_uuid', 'like', '%' . $search . '%');
            });
        }

        $perPage = (int) \config('horizonhub.jobs_per_page');
        $jobs = $query->paginate($perPage)->withQueryString();
        $services = Service::orderBy('name')->get();

        return \view('horizon.jobs.index', [
            'jobs' => $jobs,
            'services' => $services,
            'filters' => [
                'serviceFilter' => $serviceFilter,
                'statusFilter' => $statusFilter,
                'search' => $search,
            ],
            'header' => 'Horizon Hub – Jobs',
        ]);
    }

    /**
     * Show a single job detail.
     *
     * @param int $job
     * @return View
     */
    public function show(int $job): View {
        $jobModel = $this->resolveJob($job);

        if (! $jobModel) {
            \abort(404);
        }

        $exception = $this->resolveException($jobModel);

        $horizonJob = $this->fetchLiveJobMetadataFromHorizon($jobModel);

        return \view('horizon.jobs.show', [
            'job' => $jobModel,
            'exception' => $exception,
            'horizonJob' => $horizonJob,
            'header' => 'Job: ' . ($jobModel->name ?? $jobModel->job_uuid),
        ]);
    }

    /**
     * Resolve a job from HorizonJob / HorizonFailedJob.
     *
     * @param int $id
     * @return HorizonJob|HorizonFailedJob|null
     */
    private function resolveJob(int $id): HorizonJob|HorizonFailedJob|null {
        $failed = HorizonFailedJob::with('service')->find($id);
        if ($failed) {
            $matchingJob = null;
            if ($failed->service_id && $failed->job_uuid) {
                $matchingJob = HorizonJob::with('service')
                    ->where('service_id', $failed->service_id)
                    ->where('job_uuid', $failed->job_uuid)
                    ->first();
            }

            return $matchingJob ?? $failed;
        }

        return HorizonJob::with('service')->find($id);
    }

    /**
     * Resolve an exception message for a job.
     *
     * @param HorizonJob|HorizonFailedJob $job
     * @return string|null
     */
    private function resolveException(HorizonJob|HorizonFailedJob $job): ?string {
        if (isset($job->exception) && (string) $job->exception !== '') {
            return (string) $job->exception;
        }

        if ($job instanceof HorizonJob && $job->status === 'failed' && $job->service_id && $job->job_uuid) {
            return HorizonFailedJob::where('service_id', $job->service_id)
                ->where('job_uuid', $job->job_uuid)
                ->value('exception');
        }

        return null;
    }

    /**
     * Fetch live job metadata from Horizon HTTP API.
     *
     * @param HorizonJob|HorizonFailedJob $job
     * @return array<string, mixed>|null
     */
    private function fetchLiveJobMetadataFromHorizon(HorizonJob|HorizonFailedJob $job): ?array {
        $service = $job->service ?? null;
        $jobUuid = $job->job_uuid ?? null;

        if (! $service || ! $service->base_url || ! $jobUuid) {
            return null;
        }

        $response = $this->horizonApi->getFailedJob($service, (string) $jobUuid);
        if (! ($response['success'] ?? false)) {
            return null;
        }

        $data = $response['data'] ?? null;
        if (! \is_array($data)) {
            return null;
        }

        $jobData = isset($data['job']) && \is_array($data['job']) ? $data['job'] : $data;

        $attemptsRaw = $jobData['attempts'] ?? null;

        $payload = $jobData['payload'] ?? null;
        if ($attemptsRaw === null && \is_array($payload)) {
            $attemptsRaw = $payload['attempts'] ?? null;
        }

        $retries = null;
        if (isset($jobData['retried_by']) && \is_array($jobData['retried_by'])) {
            $retries = \count($jobData['retried_by']);
            if ($attemptsRaw === null) {
                $attemptsRaw = $retries + 1;
            }
        }

        $attempts = null;
        if ($attemptsRaw !== null) {
            $attemptsInt = (int) $attemptsRaw;
            if ($attemptsInt > 0) {
                $attempts = $attemptsInt;
            }
        }

        $connection = isset($jobData['connection']) && (string) $jobData['connection'] !== ''
            ? (string) $jobData['connection']
            : null;

        $tags = [];
        if (isset($jobData['tags']) && \is_array($jobData['tags'])) {
            $tags = \array_values(\array_filter($jobData['tags'], static function ($tag) {
                return \is_string($tag) && $tag !== '';
            }));
        }

        $uuid = isset($jobData['id']) && (string) $jobData['id'] !== ''
            ? (string) $jobData['id']
            : $jobUuid;

        return [
            'attempts' => $attempts,
            'connection' => $connection,
            'retries' => $retries,
            'tags' => $tags,
            'uuid' => $uuid,
        ];
    }
}
