@extends('layouts.app')

@section('content')
    <div
        x-data="window.horizonJobDetail({
            retryUrl: '{{ route('horizon.jobs.retry', ['uuid' => $job->uuid ?? '', 'service_id' => optional($job->service)->id ?? 0]) }}',
            canRetry: {{ ($job->service ?? null) && ($job->status ?? '') === 'failed' ? 'true' : 'false' }},
        })"
        x-init="typeof init === 'function' ? init() : null"
        id="horizon-job-detail"
        data-horizon-job-detail-root="1"
        data-horizon-job-uuid="{{ ! empty($job->uuid ?? null) ? e($job->uuid) : '' }}"
    >
        @include('horizon.jobs.partials.show.body')
    </div>
@endsection
