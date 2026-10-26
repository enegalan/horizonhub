@extends('layouts.app')

@section('content')
    <div class="space-y-6">
        <x-breadcrumbs :items="[
            ['label' => 'Batches', 'url' => route('horizon.batches.index')],
            ['label' => $service->name, 'url' => route('horizon.services.show', $service)],
            ['label' => $batchId],
        ]" />

        <div class="card overflow-hidden">
            <x-page-hero
                eyebrow="Batch"
                :title="$batchId"
                :description="'Progress and failed jobs reported by ' . $service->name . '.'"
            >
                <x-slot:actions>
                    <a href="{{ route('horizon.batches.index', ['service_id' => [$service->id]]) }}" class="link text-sm" data-turbo-action="replace">All batches</a>
                </x-slot:actions>
            </x-page-hero>

            <div id="batch-show-body" class="p-5 sm:p-6">
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    @for ($i = 0; $i < 4; $i++)
                        <div class="rounded-lg border border-border/70 bg-muted/20 px-4 py-3">
                            <div class="skeleton h-3 w-24" style="--skeleton-delay: {{ $i * 60 }}ms"></div>
                            <div class="mt-2 skeleton h-7 w-20" style="--skeleton-delay: {{ ($i * 60) + 80 }}ms"></div>
                        </div>
                    @endfor
                </div>
            </div>
        </div>
    </div>
@endsection
