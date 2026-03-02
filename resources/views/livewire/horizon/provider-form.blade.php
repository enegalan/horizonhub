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
                    <x-ui.select id="type" wire:model.live="type" class="w-full" :options="array('slack' => 'Slack', 'email' => 'Email')" />
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
            <x-ui.button type="submit" class="h-9 text-sm">Save</x-ui.button>
            <a href="{{ route('horizon.settings', ['tab' => 'providers']) }}" wire:navigate>
                <x-ui.button variant="ghost" type="button" class="h-9 text-sm">Cancel</x-ui.button>
            </a>
        </div>
    </form>
</div>
