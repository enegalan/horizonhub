<form
    x-ref="deleteProviderForm"
    method="POST"
    x-bind:action="deleteProviderAction"
    class="hidden"
>
    @csrf
    @method('DELETE')
</form>

<x-confirm-modal
    title="Delete provider"
    x-show="showDeleteProviderModal"
    x-on:close-modal.window="closeDeleteProviderModal()"
>
    <p class="text-sm text-muted-foreground mb-4">
        Delete provider <span class="font-medium text-foreground" x-text="deleteProviderName"></span>? This cannot be undone.
    </p>
    <x-slot:footer>
        <div class="flex w-full flex-wrap items-center justify-end gap-2">
            <x-button type="button" variant="ghost" @click="$dispatch('close-modal')">Cancel</x-button>
            <x-button type="button" variant="destructive" @click="confirmDeleteProvider()">Delete</x-button>
        </div>
    </x-slot:footer>
</x-confirm-modal>
