<div class="max-w-2xl space-y-6" x-data="{ ruleType: @entangle('rule_type') }">
    <form wire:submit="save" class="space-y-6">
        <div class="card">
            <div class="px-4 py-4 space-y-4">
                <h2 class="text-section-title text-foreground">Rule</h2>
                <div class="space-y-2">
                    <x-input-label class="text-[11px] font-medium text-muted-foreground" for="name">Name (optional)</x-input-label>
                    <x-text-input type="text" id="name" wire:model="name" placeholder="e.g. Production failures" class="w-full" />
                </div>
                <div class="space-y-2">
                    <x-input-label class="text-[11px] font-medium text-muted-foreground" for="service_id">Service</x-input-label>
                    <x-select id="service_id" wire:model="service_id" class="w-full">
                        <option value="">All services</option>
                        @foreach($services as $s)
                            <option value="{{ $s->id }}">{{ $s->name }}</option>
                        @endforeach
                    </x-select>
                </div>
                <div class="space-y-2">
                    <x-input-label class="text-[11px] font-medium text-muted-foreground" for="rule_type">Rule type</x-input-label>
                    <x-select id="rule_type" wire:model.live="rule_type" class="w-full" :options="$ruleTypes" />
                </div>
                <div class="space-y-2">
                    <x-input-label class="text-[11px] font-medium text-muted-foreground" for="queue">Queue (optional)</x-input-label>
                    <x-text-input type="text" id="queue" wire:model="queue" placeholder="default" class="w-full" />
                </div>
                <div class="space-y-2" x-show="ruleType === 'job_type_failure'">
                    <x-input-label class="text-[11px] font-medium text-muted-foreground" for="job_type">Job type (class name or substring)</x-input-label>
                    <x-text-input type="text" id="job_type" wire:model="job_type" placeholder="App\Jobs\ProcessOrder" class="w-full font-mono text-sm" />
                    @error('job_type') <span class="text-xs text-destructive">{{ $message }}</span> @enderror
                </div>
                <template x-if="['failure_count','avg_execution_time','queue_blocked','worker_offline'].includes(ruleType)">
                    <div class="space-y-3 pt-2 border-t border-border">
                        <p class="text-xs font-medium text-muted-foreground">Threshold</p>
                        <template x-if="ruleType === 'failure_count'">
                            <div class="flex gap-4 flex-wrap">
                                <div class="space-y-2">
                                    <x-input-label class="text-[11px] font-medium text-muted-foreground">Count</x-input-label>
                                    <x-text-input type="number" wire:model="thresholdCount" min="1" class="w-24" />
                                    @error('thresholdCount') <span class="text-xs text-destructive">{{ $message }}</span> @enderror
                                </div>
                                <div class="space-y-2">
                                    <x-input-label class="text-[11px] font-medium text-muted-foreground">Minutes</x-input-label>
                                    <x-text-input type="number" wire:model="thresholdMinutes" min="1" class="w-24" />
                                    @error('thresholdMinutes') <span class="text-xs text-destructive">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        </template>
                        <template x-if="ruleType === 'avg_execution_time'">
                            <div class="flex gap-4 flex-wrap">
                                <div class="space-y-2">
                                    <x-input-label class="text-[11px] font-medium text-muted-foreground">Seconds (max avg)</x-input-label>
                                    <x-text-input type="number" step="0.1" wire:model="thresholdSeconds" min="0.1" class="w-24" />
                                    @error('thresholdSeconds') <span class="text-xs text-destructive">{{ $message }}</span> @enderror
                                </div>
                                <div class="space-y-2">
                                    <x-input-label class="text-[11px] font-medium text-muted-foreground">Minutes (window)</x-input-label>
                                    <x-text-input type="number" wire:model="thresholdMinutes" min="1" class="w-24" />
                                    @error('thresholdMinutes') <span class="text-xs text-destructive">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        </template>
                        <template x-if="['queue_blocked','worker_offline'].includes(ruleType)">
                            <div class="space-y-2">
                                <x-input-label class="text-[11px] font-medium text-muted-foreground">Minutes</x-input-label>
                                <x-text-input type="number" wire:model="thresholdMinutes" min="1" class="w-24" />
                                @error('thresholdMinutes') <span class="text-xs text-destructive">{{ $message }}</span> @enderror
                            </div>
                        </template>
                    </div>
                </template>
                <div class="flex items-center gap-2 pt-2">
                    <x-checkbox id="enabled" wire:model="enabled" />
                    <x-input-label for="enabled" class="text-sm font-normal cursor-pointer">Alert enabled</x-input-label>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="px-4 py-4 space-y-4">
                <h2 class="text-section-title text-foreground">Notifications</h2>
                <p class="text-sm text-muted-foreground">Select one or more providers. Create providers in the Providers section if needed.</p>
                @if($providers->isEmpty())
                    <p class="text-sm text-amber-600 dark:text-amber-400">No providers yet. <a href="{{ route('horizon.settings', ['tab' => 'providers']) }}" wire:navigate class="link">Create a provider</a> in Settings first.</p>
                @else
                    <div class="space-y-2">
                        @foreach($providers as $provider)
                            <div class="flex items-center gap-2 rounded-md border border-border px-3 py-2 hover:bg-muted/50">
                                <x-checkbox id="provider-{{ $provider->id }}" wire:model="provider_ids" value="{{ $provider->id }}" />
                                <span class="text-sm font-medium">{{ $provider->name }}</span>
                                <span class="text-xs text-muted-foreground">({{ $provider->type }})</span>
                            </div>
                        @endforeach
                    </div>
                    @error('provider_ids') <span class="text-xs text-destructive">{{ $message }}</span> @enderror
                @endif
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
                    <x-heroicon-o-arrow-path class="size-4 animate-spin" />
                </span>
            </x-button>
            <x-button variant="ghost" type="button" class="h-9 text-sm" onclick="window.location.href='{{ route('horizon.alerts.index') }}'">
                Cancel
            </x-button>
        </div>
    </form>
</div>
