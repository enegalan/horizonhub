@php
    $detailService = $detailService ?? null;
@endphp

@if($detailService)
    <form
        x-ref="deleteServiceForm"
        method="POST"
        action="{{ route('horizon.services.destroy', $detailService) }}"
        class="hidden"
    >
        @csrf
        @method('DELETE')
    </form>
@else
    <form
        x-ref="deleteServiceForm"
        method="POST"
        x-bind:action="deleteServiceAction"
        class="hidden"
    >
        @csrf
        @method('DELETE')
    </form>
@endif

<x-confirm-modal
    title="Delete service"
    x-show="showDeleteServiceModal"
    x-on:close-modal.window="closeDeleteServiceModal()"
>
    @if($detailService)
        <p class="text-sm text-muted-foreground mb-4">
            Delete service <span class="font-medium text-foreground">{{ $detailService->name }}</span>? This cannot be undone.
        </p>
    @else
        <p class="text-sm text-muted-foreground mb-4">
            Delete service <span class="font-medium text-foreground" x-text="deleteServiceName"></span>? This cannot be undone.
        </p>
    @endif
    <x-slot:footer>
        <div class="flex w-full flex-wrap items-center justify-end gap-2">
            <x-button type="button" variant="ghost" @click="$dispatch('close-modal')">Cancel</x-button>
            <x-button type="button" variant="destructive" @click="confirmDeleteService()">Delete</x-button>
        </div>
    </x-slot:footer>
</x-confirm-modal>
