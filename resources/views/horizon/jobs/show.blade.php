@extends('layouts.app')

@section('content')
    <div
        x-data="window.horizonJobDetail()"
        x-init="typeof init === 'function' ? init() : null"
        id="horizon-job-detail"
        data-horizon-job-detail-root="1"
        data-horizon-job-uuid="{{ ! empty($job->uuid ?? null) ? e($job->uuid) : '' }}"
    >
        @include('horizon.jobs.partials.show.body')
    </div>
@endsection
