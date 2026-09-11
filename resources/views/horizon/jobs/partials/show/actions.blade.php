@if($job->service && $job->status === \App\Enums\JobStatus::Failed->value)
    <x-button
        type="button"
        class="job-detail-retry-trigger h-8 min-h-8 p-2 relative"
        aria-label="Retry"
        title="Retry"
        data-job-detail-retry="1"
        data-job-detail-retry-url="{{ route('horizon.jobs.retry', ['uuid' => $job->uuid, 'service_id' => $job->service->id]) }}"
    >
        <x-icons.arrow-path class="size-4" />
    </x-button>
@endif
@include('horizon.partials.open-in-horizon-button', [
    'job' => $job,
    'buttonVariant' => 'secondary',
    'buttonClass' => 'h-8 min-h-8 px-3 inline-flex items-center gap-1',
    'showLabel' => true,
])
