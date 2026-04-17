<form
    x-ref="deleteAlertForm"
    method="POST"
    x-bind:action="deleteAlertAction"
    class="hidden"
>
    @csrf
    @method('DELETE')
</form>

<x-confirm-modal
    title="Delete alert"
    x-show="showDeleteAlertModal"
    x-on:close-modal.window="closeDeleteAlertModal()"
>
    <p class="text-sm text-muted-foreground mb-4">
        Delete alert <span class="font-medium text-foreground" x-text="deleteAlertName"></span>? This cannot be undone.
    </p>
    <x-slot:footer>
        <div class="flex w-full flex-wrap items-center justify-end gap-2">
            <x-button type="button" variant="ghost" @click="$dispatch('close-modal')">Cancel</x-button>
            <x-button type="button" variant="destructive" @click="confirmDeleteAlert()">Delete</x-button>
        </div>
    </x-slot:footer>
</x-confirm-modal>
