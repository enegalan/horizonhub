@props([
    'allTags' => [],
    'selectedTags' => [],
    'showServiceMultiselect' => false,
    'services' => null,
    'serviceIds' => [],
    'serviceMultiselectName' => 'service_id',
    'serviceMultiselectId' => 'service-filter',
    'serviceMultiselectLabel' => 'Services',
])

@php
    $allTags = \is_array($allTags) ? $allTags : [];
    $selectedTags = \is_array($selectedTags) ? $selectedTags : [];
@endphp

<div
    class="flex flex-wrap items-end gap-3"
    data-service-tag-filter="1"
>
    <div class="space-y-2">
        <x-input-label id="service-tag-filter-label" for="service-tag-filter-trigger">Tags</x-input-label>
        <x-multiselect
            id="service-tag-filter"
            labelled-by="service-tag-filter-label"
            name="service_tag"
            class="w-full min-w-0 sm:w-64"
            :selected="$selectedTags"
            placeholder="All tags"
            empty-message="No tags yet"
        >
            @foreach($allTags as $tag)
                <option value="{{ $tag }}">{{ $tag }}</option>
            @endforeach
        </x-multiselect>
    </div>
    @if($showServiceMultiselect && $services !== null)
        <div class="space-y-2">
            <x-input-label id="{{ $serviceMultiselectId }}-label" for="{{ $serviceMultiselectId }}">{{ $serviceMultiselectLabel }}</x-input-label>
            <x-multiselect
                id="{{ $serviceMultiselectId }}"
                labelled-by="{{ $serviceMultiselectId }}-label"
                name="{{ $serviceMultiselectName }}"
                class="w-full min-w-0 sm:w-64"
                :selected="$serviceIds"
                placeholder="All services"
                empty-message="No services found"
            >
                @foreach($services as $service)
                    <option value="{{ $service->id }}">{{ $service->name }}</option>
                @endforeach
            </x-multiselect>
        </div>
    @endif
</div>

