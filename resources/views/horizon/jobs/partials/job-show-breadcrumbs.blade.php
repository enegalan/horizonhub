@php
    $breadcrumbItems = [
        ['label' => 'Jobs', 'url' => route('horizon.jobs.index')],
    ];
    if ($job->service) {
        $breadcrumbItems[] = ['label' => $job->service->name, 'url' => route('horizon.services.show', $job->service)];
    }
    $breadcrumbItems[] = ['label' => $job->name ?? $job->uuid];
@endphp

<x-breadcrumbs :items="$breadcrumbItems" />
