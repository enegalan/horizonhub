@props([
    'entity',
    'title',
    'resourceLabel',
    'fixedName' => null,
    'destroyUrl' => null,
])

@php
    $formRef = 'delete' . $entity . 'Form';
    $showProperty = 'showDelete' . $entity . 'Modal';
    $closeMethod = 'closeDelete' . $entity . 'Modal';
    $confirmMethod = 'confirmDelete' . $entity;
    $nameProperty = 'delete' . $entity . 'Name';
    $actionProperty = 'delete' . $entity . 'Action';
@endphp

<form
    x-ref="{{ $formRef }}"
    method="POST"
    @if($destroyUrl)
        action="{{ $destroyUrl }}"
    @else
        x-bind:action="{{ $actionProperty }}"
    @endif
    class="hidden"
>
    @csrf
    @method('DELETE')
</form>

<x-confirm-modal
    :title="$title"
    x-show="{{ $showProperty }}"
    x-on:close-modal.window="{{ $closeMethod }}()"
>
    <p class="text-sm text-muted-foreground mb-4">
        Delete {{ $resourceLabel }}
        @if($fixedName)
            <span class="font-medium text-foreground">{{ $fixedName }}</span>
        @else
            <span class="font-medium text-foreground" x-text="{{ $nameProperty }}"></span>
        @endif
        ? This cannot be undone.
    </p>
    <x-slot:footer>
        <div class="flex w-full flex-wrap items-center justify-end gap-2">
            <x-button type="button" variant="ghost" @click="$dispatch('close-modal')">Cancel</x-button>
            <x-button type="button" variant="destructive" @click="{{ $confirmMethod }}()">Delete</x-button>
        </div>
    </x-slot:footer>
</x-confirm-modal>
