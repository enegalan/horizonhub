@extends('layouts.app')

@section('content')
    @php
        $isEdit = $alert->exists;
        $action = $isEdit ? route('horizon.alerts.update', $alert) : route('horizon.alerts.store');
        /** @var array<int,int> $selectedProviderIds */
        $selectedProviderIds = $selectedProviderIds ?? [];
    @endphp

    <div class="max-w-2xl space-y-6" x-data="{ ruleType: '{{ old('rule_type', $alert->rule_type ?? 'failure_count') }}' }">
        <form method="POST" action="{{ $action }}" class="space-y-6">
            @csrf
            @if($isEdit)
                @method('PUT')
            @endif

            <div class="card">
                <div class="px-4 py-4 space-y-4">
                    <h2 class="text-section-title text-foreground">Rule</h2>
                    <div class="space-y-2">
                        <x-input-label for="name">Name (optional)</x-input-label>
                        <x-text-input
                            type="text"
                            id="name"
                            name="name"
                            value="{{ old('name', $alert->name) }}"
                            placeholder="e.g. Production failures"
                            class="w-full"
                        />
                        @error('name') <span class="text-xs text-destructive">{{ $message }}</span> @enderror
                    </div>
                    <div class="space-y-2">
                        <x-input-label for="service_id">Service</x-input-label>
                        <x-select id="service_id" name="service_id" class="w-full">
                            <option value="">All services</option>
                            @foreach($services as $s)
                                <option value="{{ $s->id }}" @selected((string) old('service_id', $alert->service_id) === (string) $s->id)>{{ $s->name }}</option>
                            @endforeach
                        </x-select>
                        @error('service_id') <span class="text-xs text-destructive">{{ $message }}</span> @enderror
                    </div>
                    <div class="space-y-2">
                        <x-input-label for="rule_type">Rule type</x-input-label>
                        <x-select
                            id="rule_type"
                            name="rule_type"
                            class="w-full"
                            x-model="ruleType"
                        >
                            @foreach($ruleTypes as $key => $label)
                                <option value="{{ $key }}" @selected(old('rule_type', $alert->rule_type ?? 'failure_count') === $key)>{{ $label }}</option>
                            @endforeach
                        </x-select>
                        @error('rule_type') <span class="text-xs text-destructive">{{ $message }}</span> @enderror
                    </div>
                    <div class="space-y-2">
                        <x-input-label for="queue">Queue (optional)</x-input-label>
                        <x-text-input
                            type="text"
                            id="queue"
                            name="queue"
                            value="{{ old('queue', $alert->queue) }}"
                            placeholder="default"
                            class="w-full"
                        />
                        @error('queue') <span class="text-xs text-destructive">{{ $message }}</span> @enderror
                    </div>
                    <div class="space-y-2" x-show="ruleType === 'job_type_failure'">
                        <x-input-label for="job_type">Job type (class name or substring)</x-input-label>
                        <x-text-input
                            type="text"
                            id="job_type"
                            name="job_type"
                            value="{{ old('job_type', $alert->job_type) }}"
                            placeholder="App\Jobs\ProcessOrder"
                            class="w-full font-mono text-sm"
                        />
                        @error('job_type') <span class="text-xs text-destructive">{{ $message }}</span> @enderror
                    </div>

                    <template x-if="['failure_count','avg_execution_time','queue_blocked','worker_offline','supervisor_offline','horizon_offline'].includes(ruleType)">
                        <div class="space-y-3 pt-2 border-t border-border">
                            <p class="text-xs font-medium text-muted-foreground">Threshold</p>
                            <template x-if="ruleType === 'failure_count'">
                                <div class="flex gap-4 flex-wrap">
                                    <div class="space-y-2">
                                        <x-input-label>Count</x-input-label>
                                        <x-text-input
                                            type="number"
                                            name="thresholdCount"
                                            min="1"
                                            class="w-24"
                                            value="{{ old('thresholdCount') !== null ? old('thresholdCount') : ($alert->threshold['count'] ?? 5) }}"
                                        />
                                        @error('thresholdCount') <span class="text-xs text-destructive">{{ $message }}</span> @enderror
                                    </div>
                                    <div class="space-y-2">
                                        <x-input-label>Minutes</x-input-label>
                                        <x-text-input
                                            type="number"
                                            name="thresholdMinutes"
                                            min="1"
                                            class="w-24"
                                            value="{{ old('thresholdMinutes') !== null ? old('thresholdMinutes') : ($alert->threshold['minutes'] ?? 15) }}"
                                        />
                                        @error('thresholdMinutes') <span class="text-xs text-destructive">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                            </template>
                            <template x-if="ruleType === 'avg_execution_time'">
                                <div class="flex gap-4 flex-wrap">
                                    <div class="space-y-2">
                                        <x-input-label>Seconds (max avg)</x-input-label>
                                        <x-text-input
                                            type="number"
                                            step="0.1"
                                            name="thresholdSeconds"
                                            min="0.1"
                                            class="w-24"
                                            value="{{ old('thresholdSeconds') !== null ? old('thresholdSeconds') : ($alert->threshold['seconds'] ?? 60) }}"
                                        />
                                        @error('thresholdSeconds') <span class="text-xs text-destructive">{{ $message }}</span> @enderror
                                    </div>
                                    <div class="space-y-2">
                                        <x-input-label>Minutes (window)</x-input-label>
                                        <x-text-input
                                            type="number"
                                            name="thresholdMinutes"
                                            min="1"
                                            class="w-24"
                                            value="{{ old('thresholdMinutes') !== null ? old('thresholdMinutes') : ($alert->threshold['minutes'] ?? 15) }}"
                                        />
                                        @error('thresholdMinutes') <span class="text-xs text-destructive">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                            </template>
                            <template x-if="['queue_blocked','worker_offline','supervisor_offline','horizon_offline'].includes(ruleType)">
                                <div class="space-y-2">
                                    <x-input-label>Minutes</x-input-label>
                                    <x-text-input
                                        type="number"
                                        name="thresholdMinutes"
                                        min="1"
                                        class="w-24"
                                        value="{{ old('thresholdMinutes') !== null ? old('thresholdMinutes') : ($alert->threshold['minutes'] ?? 15) }}"
                                    />
                                    @error('thresholdMinutes') <span class="text-xs text-destructive">{{ $message }}</span> @enderror
                                </div>
                            </template>
                        </div>
                    </template>

                    <div class="flex items-center gap-2 pt-2">
                        <input type="hidden" name="enabled" value="0">
                        <x-checkbox
                            id="enabled"
                            name="enabled"
                            value="1"
                            :checked="old('enabled', $alert->enabled ?? true)"
                        />
                        <x-input-label for="enabled" class="text-sm font-normal cursor-pointer">Alert enabled</x-input-label>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="px-4 py-4 space-y-4">
                    <h2 class="text-section-title text-foreground">Notifications</h2>
                    <div class="space-y-2">
                        <x-input-label for="email_interval_minutes">Minutes between notifications (throttle)</x-input-label>
                        <x-text-input
                            type="number"
                            id="email_interval_minutes"
                            name="email_interval_minutes"
                            min="0"
                            max="1440"
                            class="w-24"
                            value="{{ old('email_interval_minutes', $alert->email_interval_minutes ?? 5) }}"
                        />
                        <p class="text-xs text-muted-foreground">
                            Minimum minutes between sends. Multiple triggers in that window are batched into one notification. Use 0 to send on every trigger.
                        </p>
                        @error('email_interval_minutes')
                            <p class="text-sm text-destructive">{{ $message }}</p>
                        @enderror
                    </div>
                    <p class="text-sm text-muted-foreground">Select one or more providers. Create providers in the Providers section if needed.</p>
                    @if($providers->isEmpty())
                        <p class="text-sm text-amber-600 dark:text-amber-400">
                            No providers yet.
                            <a href="{{ route('horizon.settings', ['tab' => 'providers']) }}" class="link">Create a provider</a>
                            in Settings first.
                        </p>
                    @else
                        <div class="space-y-2">
                            @php
                                $oldProviderIds = old('provider_ids', $selectedProviderIds);
                            @endphp
                            @foreach($providers as $provider)
                                <div class="flex items-center gap-2 rounded-md border border-border px-3 py-2 hover:bg-muted/50">
                                    <x-checkbox
                                        id="provider-{{ $provider->id }}"
                                        name="provider_ids[]"
                                        value="{{ $provider->id }}"
                                        :checked="in_array($provider->id, $oldProviderIds, true)"
                                    />
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
                >
                    Save
                </x-button>
                <x-button
                    variant="ghost"
                    type="button"
                    class="h-9 text-sm"
                    onclick="window.location.href='{{ route('horizon.alerts.index') }}'"
                >
                    Cancel
                </x-button>
            </div>
        </form>
    </div>
@endsection
