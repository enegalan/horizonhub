<div class="max-w-2xl space-y-6" x-data="{ type: @entangle('type') }">
    <form wire:submit="save" class="space-y-6">
        <div class="card">
            <div class="px-4 py-4 space-y-4">
                <div class="space-y-1.5">
                    <x-input-label class="text-[11px] font-medium text-muted-foreground" for="name">Name</x-input-label>
                    <x-text-input type="text" id="name" wire:model="name" placeholder="e.g. Slack #ops" class="w-full" />
                    @error('name') <span class="text-xs text-destructive">{{ $message }}</span> @enderror
                </div>
                <div class="space-y-1.5">
                    <x-input-label class="text-[11px] font-medium text-muted-foreground" for="type">Type</x-input-label>
                    <x-select id="type" wire:model.live="type" class="w-full" :options="array('slack' => 'Slack', 'email' => 'Email')" />
                </div>
                <div class="space-y-1.5" x-show="type === 'slack'" x-cloak>
                    <x-input-label class="text-[11px] font-medium text-muted-foreground" for="webhook_url">Webhook URL</x-input-label>
                    <x-text-input type="url" id="webhook_url" wire:model="webhook_url" placeholder="https://hooks.slack.com/services/..." class="w-full font-mono text-sm" />
                    @error('webhook_url') <span class="text-xs text-destructive">{{ $message }}</span> @enderror
                </div>
                <div class="space-y-1.5" x-show="type === 'email'" x-cloak>
                    <x-input-label class="text-[11px] font-medium text-muted-foreground" for="email_to">Recipients (comma-separated)</x-input-label>
                    <x-text-input type="text" id="email_to" wire:model="email_to" placeholder="alerts@example.com" class="w-full" />
                    @error('email_to') <span class="text-xs text-destructive">{{ $message }}</span> @enderror
                </div>
            </div>
        </div>

        <div class="flex gap-2">
            <x-button
                type="submit"
                class="h-9 text-sm relative inline-flex items-center justify-center"
                wire:loading.attr="disabled"
                wire:target="save"
            >
                <span wire:loading.remove wire:target="save">
                    Save
                </span>
                <span wire:loading wire:target="save" class="inline-flex" aria-hidden="true">
                    <svg class="size-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </span>
            </x-button>
            <a href="{{ route('horizon.settings', ['tab' => 'providers']) }}" wire:navigate>
                <x-button variant="ghost" type="button" class="h-9 text-sm">Cancel</x-button>
            </a>
        </div>
    </form>
</div>
