@extends('layouts.app')

@section('content')
    <div
        x-data="window.horizonJobDetail({
            retryUrl: '{{ route('horizon.jobs.retry', ['uuid' => $job->uuid, 'service_id' => $job->service->id]) }}',
            canRetry: {{ $job->service && $job->service->base_url && $job->status === 'failed' ? 'true' : 'false' }},
        })"
        x-init="typeof init === 'function' ? init() : null"
        id="horizon-job-detail"
        data-horizon-job-detail-root="1"
        data-horizon-job-uuid="{{ $job->uuid ? e($job->uuid) : '' }}"
    >
        @include('horizon.jobs.partials.job-show-body')
    </div>
@endsection
