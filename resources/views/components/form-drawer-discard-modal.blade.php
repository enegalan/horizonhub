<x-confirm-modal
    title="Discard unsaved changes?"
    size="sm"
    x-show="showFormDrawerDiscardModal"
    x-on:form-drawer-discard-request.window="showFormDrawerDiscardModal = true"
    x-on:close-modal.window="showFormDrawerDiscardModal = false"
>
    <p class="text-sm text-muted-foreground">
        This form has changes that have not been saved yet. Closing the panel will discard them.
    </p>
    <x-slot:footer>
        <div class="flex w-full flex-wrap items-center justify-end gap-2">
            <x-button type="button" variant="ghost" @click="$dispatch('close-modal')">Keep editing</x-button>
            <x-button
                type="button"
                variant="destructive"
                data-form-drawer-discard
                @click="showFormDrawerDiscardModal = false"
            >
                Discard
            </x-button>
        </div>
    </x-slot:footer>
</x-confirm-modal>
