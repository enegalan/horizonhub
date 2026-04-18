@extends('layouts.app')

@section('content')
    @php
        $isEdit = $alert->exists;
        $action = $isEdit ? route('horizon.alerts.update', $alert) : route('horizon.alerts.store');
        /** @var array<int,int> $selectedProviderIds */
        $selectedProviderIds = $selectedProviderIds ?? [];
        /** @var array<int,int> $selectedServiceIds */
        $selectedServiceIds = $selectedServiceIds ?? [];
        $thresholdForm = $alert->threshold ?? [];
        $oldJobPatterns = old('job_patterns');
        if (\is_array($oldJobPatterns)) {
            $jobPatternsForForm = [];
            foreach ($oldJobPatterns as $v) {
                $jobPatternsForForm[] = \is_string($v) ? $v : '';
            }
            if ($jobPatternsForForm === []) {
                $jobPatternsForForm = [''];
            }
        } elseif (isset($thresholdForm['job_patterns']) && \is_array($thresholdForm['job_patterns']) && $thresholdForm['job_patterns'] !== []) {
            $jobPatternsForForm = \array_slice(\array_map('strval', $thresholdForm['job_patterns']), 0, 20);
        } else {
            $jobPatternsForForm = [''];
        }
        $jobTypeValueForForm = old('job_type');
        if ($jobTypeValueForForm === null) {
            $storedJobPatterns = $thresholdForm['job_patterns'] ?? [];
            $hasStoredJobPatterns = \is_array($storedJobPatterns)
                && \count(\array_filter($storedJobPatterns, static fn ($x) => \is_string($x) && \trim($x) !== '')) > 0;
            $jobTypeValueForForm = $hasStoredJobPatterns ? '' : (string) ($alert->job_type ?? '');
        }
        $oldQueuePatterns = old('queue_patterns');
        if (\is_array($oldQueuePatterns)) {
            $queuePatternsForForm = [];
            foreach ($oldQueuePatterns as $v) {
                $queuePatternsForForm[] = \is_string($v) ? $v : '';
            }
            if ($queuePatternsForForm === []) {
                $queuePatternsForForm = [''];
            }
        } elseif (isset($thresholdForm['queue_patterns']) && \is_array($thresholdForm['queue_patterns']) && $thresholdForm['queue_patterns'] !== []) {
            $queuePatternsForForm = \array_slice(\array_map('strval', $thresholdForm['queue_patterns']), 0, 20);
        } elseif ($alert->queue !== null && (string) $alert->queue !== '') {
            $queuePatternsForForm = [(string) $alert->queue];
        } else {
            $queuePatternsForForm = [''];
        }
    @endphp

    <div
        class="max-w-2xl space-y-6"
        x-data="{
            ruleType: {!! \Illuminate\Support\Js::from(old('rule_type', $alert->rule_type ?? 'failure_count')) !!},
            jobPatterns: {!! \Illuminate\Support\Js::from($jobPatternsForForm) !!},
            queuePatterns: {!! \Illuminate\Support\Js::from($queuePatternsForForm) !!},
            addJobPattern() {
                if (this.jobPatterns.length < 20) {
                    this.jobPatterns.push('');
                }
            },
            removeJobPattern(i) {
                if (this.jobPatterns.length > 1) {
                    this.jobPatterns.splice(i, 1);
                } else {
                    this.jobPatterns[0] = '';
                }
            },
            addQueuePattern() {
                if (this.queuePatterns.length < 20) {
                    this.queuePatterns.push('');
                }
            },
            removeQueuePattern(i) {
                if (this.queuePatterns.length > 1) {
                    this.queuePatterns.splice(i, 1);
                } else {
                    this.queuePatterns[0] = '';
                }
            },
            toggleCheckboxRow(event) {
                if (event.target.tagName === 'INPUT' && event.target.type === 'checkbox') {
                    return;
                }
                if (event.target.closest('label')) {
                    return;
                }
                if (event.target.closest('a') || event.target.closest('button')) {
                    return;
                }
                var root = event.currentTarget;
                var input = root.querySelector('.checkbox-root input');
                if (input && !input.disabled) {
                    input.click();
                }
            }
        }"
    >
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
                        <x-input-label>Services</x-input-label>
                        <p class="text-xs text-muted-foreground">Select one or more services. Leave all unchecked to apply to all services.</p>
                        @php
                            $oldServiceIds = old('service_ids', $selectedServiceIds);
                            $oldServiceIds = \is_array($oldServiceIds) ? \array_map('intval', $oldServiceIds) : [];
                        @endphp
                        <div class="space-y-2">
                            @foreach($services as $s)
                                <div
                                    class="flex cursor-pointer items-center gap-2 rounded-md border border-border px-3 py-2 hover:bg-muted/50"
                                    @click="toggleCheckboxRow($event)"
                                >
                                    <x-checkbox
                                        id="service-{{ $s->id }}"
                                        name="service_ids[]"
                                        value="{{ $s->id }}"
                                        :checked="in_array((int) $s->id, $oldServiceIds, true)"
                                    />
                                    <x-input-label for="service-{{ $s->id }}" class="cursor-pointer text-sm font-normal">{{ $s->name }}</x-input-label>
                                </div>
                            @endforeach
                        </div>
                        @error('service_ids') <span class="text-xs text-destructive">{{ $message }}</span> @enderror
                        @error('service_ids.*') <span class="text-xs text-destructive">{{ $message }}</span> @enderror
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
                    <div
                        class="space-y-2"
                        x-show="['failure_count','avg_execution_time','queue_blocked'].includes(ruleType)"
                        x-cloak
                    >
                        <x-input-label>Queue (optional)</x-input-label>
                        <p class="text-xs text-muted-foreground">Exact queue names. Use one row for a single queue, or add rows for several (OR). Leave empty to include all queues.</p>
                        <div class="space-y-2">
                            <template x-for="(val, index) in queuePatterns" :key="'qp-' + index">
                                <div class="flex gap-2 items-center">
                                    <input
                                        type="text"
                                        name="queue_patterns[]"
                                        x-model="queuePatterns[index]"
                                        placeholder="default"
                                        class="flex-1 rounded-md border border-border bg-background px-3 py-2 text-sm font-mono text-foreground shadow-sm"
                                    />
                                    <x-button
                                        type="button"
                                        variant="ghost"
                                        class="h-9 shrink-0 text-xs"
                                        @click="removeQueuePattern(index)"
                                        x-show="queuePatterns.length > 1"
                                    >
                                        Remove
                                    </x-button>
                                </div>
                            </template>
                            <x-button
                                type="button"
                                variant="secondary"
                                class="h-9 text-sm"
                                @click="addQueuePattern()"
                                x-show="queuePatterns.length < 20"
                            >
                                Add queue
                            </x-button>
                        </div>
                        @error('queue_patterns') <span class="text-xs text-destructive">{{ $message }}</span> @enderror
                    </div>
                    <div
                        class="space-y-2"
                        x-show="['failure_count','avg_execution_time'].includes(ruleType)"
                        x-cloak
                    >
                        <x-input-label>Job (optional)</x-input-label>
                        <p class="text-xs text-muted-foreground">Substring match on Horizon job class / display name. Multiple patterns match any (OR). Leave all rows empty to include every job type where the rule allows.</p>
                        <div class="space-y-2">
                            <template x-for="(val, index) in jobPatterns" :key="'jp-' + index">
                                <div class="flex gap-2 items-center">
                                    <input
                                        type="text"
                                        name="job_patterns[]"
                                        x-model="jobPatterns[index]"
                                        placeholder="App\Jobs\SendEmail"
                                        class="flex-1 rounded-md border border-border bg-background px-3 py-2 text-sm font-mono text-foreground shadow-sm"
                                    />
                                    <x-button
                                        type="button"
                                        variant="ghost"
                                        class="h-9 shrink-0 text-xs"
                                        @click="removeJobPattern(index)"
                                        x-show="jobPatterns.length > 1"
                                    >
                                        Remove
                                    </x-button>
                                </div>
                            </template>
                            <x-button
                                type="button"
                                variant="secondary"
                                class="h-9 text-sm"
                                @click="addJobPattern()"
                                x-show="jobPatterns.length < 20"
                            >
                                Add pattern
                            </x-button>
                        </div>
                        @error('job_patterns') <span class="text-xs text-destructive">{{ $message }}</span> @enderror
                    </div>
                    <div class="space-y-2" x-show="['failure_count','avg_execution_time'].includes(ruleType)" x-cloak>
                        <x-input-label for="job_type">Job type (optional single substring)</x-input-label>
                        <x-text-input
                            type="text"
                            id="job_type"
                            name="job_type"
                            value="{{ $jobTypeValueForForm }}"
                            placeholder="App\Jobs\ProcessOrder"
                            class="w-full font-mono text-sm"
                        />
                        <p class="text-xs text-muted-foreground">Merged with job patterns above when saved (same substring rules).</p>
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
                                            value="{{ old('thresholdCount') !== null ? old('thresholdCount') : ($alert->threshold['count'] ?? config('horizonhub.alerts.default_count')) }}"
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
                                            value="{{ old('thresholdMinutes') !== null ? old('thresholdMinutes') : ($alert->threshold['minutes'] ?? config('horizonhub.alerts.default_minutes')) }}"
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
                                            value="{{ old('thresholdSeconds') !== null ? old('thresholdSeconds') : ($alert->threshold['seconds'] ?? config('horizonhub.alerts.default_seconds')) }}"
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
                                            value="{{ old('thresholdMinutes') !== null ? old('thresholdMinutes') : ($alert->threshold['minutes'] ?? config('horizonhub.alerts.default_minutes')) }}"
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
                                        value="{{ old('thresholdMinutes') !== null ? old('thresholdMinutes') : ($alert->threshold['minutes'] ?? config('horizonhub.alerts.default_minutes')) }}"
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
                            value="{{ old('email_interval_minutes', $alert->email_interval_minutes ?? config('horizonhub.alerts.default_email_interval_minutes')) }}"
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
                            <a href="{{ route('horizon.settings', ['tab' => 'providers']) }}" class="link" data-turbo-action="replace">Create a provider</a>
                            in Settings first.
                        </p>
                    @else
                        <div class="space-y-2">
                            @php
                                $oldProviderIds = old('provider_ids', $selectedProviderIds);
                            @endphp
                            @foreach($providers as $provider)
                                <div
                                    class="flex cursor-pointer items-center gap-2 rounded-md border border-border px-3 py-2 hover:bg-muted/50"
                                    @click="toggleCheckboxRow($event)"
                                >
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
