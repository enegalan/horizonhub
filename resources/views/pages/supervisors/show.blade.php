@extends('layouts.app')

@section('content')
    <div class="space-y-6">
        <x-breadcrumbs :items="[
            ['label' => 'Services', 'url' => route('horizon.services.index')],
            ['label' => $service->name, 'url' => route('horizon.services.show', $service)],
            ['label' => $supervisorName],
        ]" />

        <div class="card overflow-hidden">
            <x-page-hero
                eyebrow="Supervisor"
                :title="$supervisorName"
                :description="'Runtime configuration reported by ' . $service->name . '.'"
            >
                <x-slot:actions>
                    <a href="{{ route('horizon.services.show', $service) }}" class="link text-sm" data-turbo-action="replace">Back to service</a>
                </x-slot:actions>
            </x-page-hero>

            <div id="supervisor-show-body" class="p-5 sm:p-6">
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <x-skeleton.metric-columns :columns="4" />
                </div>
            </div>
        </div>
    </div>
@endsection
