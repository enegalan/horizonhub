@extends('layouts.form-drawer')

@section('content')
    @php
        // TODO: EYES BLEEDING HERE
        $isEdit = $alert->exists;
        $action = $isEdit ? route('horizon.alerts.update', $alert) : route('horizon.alerts.store');
        $selectedProviderIds ??= [];
        $selectedServiceIds ??= [];
        $oldJobPatterns = old('job_patterns');
        $jobPatternsForForm = [];
        if (\is_array($oldJobPatterns)) {
            foreach ($oldJobPatterns as $v) {
                $jobPatternsForForm[] = \is_string($v) ? $v : '';
            }
        } elseif (! empty($alert->getJobPatterns())) {
            $jobPatternsForForm = \array_values(\array_map('strval', $alert->getJobPatterns()));
        }
        if (empty($jobPatternsForForm)) {
            $jobPatternsForForm = [''];
        }
        $jobTypeValueForForm = old('job_type');
        if (empty($jobTypeValueForForm)) {
            $storedJobPatterns = $alert->getJobPatterns();
            $hasStoredJobPatterns = \is_array($storedJobPatterns)
                && \count(\array_filter($storedJobPatterns, static fn ($x) => \is_string($x) && \trim($x) !== '')) > 0;
            $jobTypeValueForForm = $hasStoredJobPatterns ? '' : (string) ($alert->job_type ?? '');
        }
        $oldQueuePatterns = old('queue_patterns');
        $queuePatternsForForm = [];
        if (\is_array($oldQueuePatterns)) {
            foreach ($oldQueuePatterns as $v) {
                $queuePatternsForForm[] = \is_string($v) ? $v : '';
            }
        } elseif (! empty($alert->getQueuePatterns()) && \is_array($alert->getQueuePatterns())) {
            $queuePatternsForForm = \array_values(\array_map('strval', $alert->getQueuePatterns()));
        } elseif (! empty($alert->queue)) {
            $queuePatternsForForm = [(string) $alert->queue];
        }
        if (empty($queuePatternsForForm)) {
            $queuePatternsForForm = [''];
        }
        $queueOptionalSectionOpenDefault = \count(\array_filter(
            $queuePatternsForForm,
            static fn ($x) => \is_string($x) && \trim($x) !== ''
        )) > 0 || $errors->has('queue_patterns');
        $jobOptionalSectionOpenDefault = \count(\array_filter(
            $jobPatternsForForm,
            static fn ($x) => \is_string($x) && \trim($x) !== ''
        )) > 0 || $errors->has('job_patterns');
        $jobTypeOptionalSectionOpenDefault = (\is_string($jobTypeValueForForm) ? \trim($jobTypeValueForForm) : '') !== ''
            || $errors->has('job_type');
    @endphp

    <div
        class="space-y-6"
        x-data="{
            ruleType: {!! \Illuminate\Support\Js::from(old('rule_type', $alert->rule_type ?? 'failure_count')) !!},
            jobPatterns: {!! \Illuminate\Support\Js::from($jobPatternsForForm) !!},
            queuePatterns: {!! \Illuminate\Support\Js::from($queuePatternsForForm) !!},
            queueOptionalSectionOpen: {!! \Illuminate\Support\Js::from($queueOptionalSectionOpenDefault) !!},
            jobOptionalSectionOpen: {!! \Illuminate\Support\Js::from($jobOptionalSectionOpenDefault) !!},
            jobTypeOptionalSectionOpen: {!! \Illuminate\Support\Js::from($jobTypeOptionalSectionOpenDefault) !!},
            allServicesSelected: false,
            init() {
                this.$nextTick(() => {
                    this.syncAllServicesSelected();
                });
            },
            addJobPattern() {
                this.jobPatterns.push('');
            },
            removeJobPattern(i) {
                if (this.jobPatterns.length > 1) {
                    this.jobPatterns.splice(i, 1);
                } else {
                    this.jobPatterns[0] = '';
                }
            },
            addQueuePattern() {
                this.queuePatterns.push('');
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
            },
            serviceCheckboxInputs() {
                if (!this.$refs.serviceCheckboxList) {
                    return [];
                }
                return Array.from(this.$refs.serviceCheckboxList.querySelectorAll('.checkbox-root input[type=checkbox]'));
            },
            syncAllServicesSelected() {
                var inputs = this.serviceCheckboxInputs();
                this.allServicesSelected = inputs.length > 0 && inputs.every(function (input) {
                    return input.checked;
                });
            },
            toggleAllServices() {
                var inputs = this.serviceCheckboxInputs();
                if (inputs.length === 0) {
                    return;
                }
                var selectAll = !this.allServicesSelected;
                inputs.forEach(function (input) {
                    input.checked = selectAll;
                });
                this.syncAllServicesSelected();
            }
        }"
    >
        <form method="POST" action="{{ $action }}" class="space-y-6" data-turbo-frame="form-drawer">
            @csrf
            @if($isEdit)
                @method('PUT')
            @endif

            <div id="alert-section-rule" class="card overflow-hidden">
                <div class="border-b border-border px-5 py-4 sm:px-6">
                    <h3 class="text-sm font-semibold text-foreground">Rule</h3>
                    <p class="mt-1 text-sm text-muted-foreground">Scope the alert to services, queues, and jobs, then set the trigger threshold.</p>
                </div>
                <div class="space-y-5 px-5 py-5 sm:px-6">
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
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <x-input-label>Services</x-input-label>
                            @if($services->isNotEmpty())
                                <x-button
                                    type="button"
                                    variant="secondary"
                                    class="h-8 text-xs"
                                    @click="toggleAllServices()"
                                    x-text="allServicesSelected ? 'Unselect all' : 'Select all'"
                                />
                            @endif
                        </div>
                        <p class="text-xs text-muted-foreground">Select at least one service. This alert only applies to the services you choose.</p>
                        @php
                            $oldServiceIds = old('service_ids', $selectedServiceIds);
                            $oldServiceIds = \is_array($oldServiceIds) ? \array_map('intval', $oldServiceIds) : [];
                        @endphp
                        <div class="space-y-2" x-ref="serviceCheckboxList" @change="syncAllServicesSelected()">
                            @foreach($services as $s)
                                <div
                                    class="flex cursor-pointer items-center gap-3 rounded-xl border border-border px-3 py-2.5 transition-colors hover:bg-muted/50"
                                    role="group"
                                    tabindex="0"
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
                        class="overflow-hidden rounded-lg border border-border"
                        x-show="['failure_count','avg_execution_time','queue_blocked'].includes(ruleType)"
                        x-cloak
                    >
                        <button
                            type="button"
                            class="flex w-full items-center justify-between gap-2 px-3 py-2.5 text-left text-sm font-medium text-foreground hover:bg-muted/50"
                            @click="queueOptionalSectionOpen = !queueOptionalSectionOpen"
                            :aria-expanded="queueOptionalSectionOpen"
                        >
                            <span>Queue (optional)</span>
                            <x-icons.chevron-down
                                class="h-5 w-5 shrink-0 text-muted-foreground transition-transform duration-200"
                                x-bind:class="{ 'rotate-180': queueOptionalSectionOpen }"
                            />
                        </button>
                        <div
                            x-show="queueOptionalSectionOpen"
                            x-transition
                            class="space-y-2 border-t border-border px-3 py-3"
                        >
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
                                >
                                    Add queue
                                </x-button>
                            </div>
                            @error('queue_patterns') <span class="text-xs text-destructive">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div
                        class="overflow-hidden rounded-lg border border-border"
                        x-show="['failure_count','avg_execution_time'].includes(ruleType)"
                        x-cloak
                    >
                        <button
                            type="button"
                            class="flex w-full items-center justify-between gap-2 px-3 py-2.5 text-left text-sm font-medium text-foreground hover:bg-muted/50"
                            @click="jobOptionalSectionOpen = !jobOptionalSectionOpen"
                            :aria-expanded="jobOptionalSectionOpen"
                        >
                            <span>Job (optional)</span>
                            <x-icons.chevron-down
                                class="h-5 w-5 shrink-0 text-muted-foreground transition-transform duration-200"
                                x-bind:class="{ 'rotate-180': jobOptionalSectionOpen }"
                            />
                        </button>
                        <div
                            x-show="jobOptionalSectionOpen"
                            x-transition
                            class="space-y-2 border-t border-border px-3 py-3"
                        >
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
                                >
                                    Add pattern
                                </x-button>
                            </div>
                            @error('job_patterns') <span class="text-xs text-destructive">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div
                        class="overflow-hidden rounded-lg border border-border"
                        x-show="['failure_count','avg_execution_time'].includes(ruleType)"
                        x-cloak
                    >
                        <button
                            type="button"
                            class="flex w-full items-center justify-between gap-2 px-3 py-2.5 text-left text-sm font-medium text-foreground hover:bg-muted/50"
                            @click="jobTypeOptionalSectionOpen = !jobTypeOptionalSectionOpen"
                            :aria-expanded="jobTypeOptionalSectionOpen"
                        >
                            <span>Job type (optional)</span>
                            <x-icons.chevron-down
                                class="h-5 w-5 shrink-0 text-muted-foreground transition-transform duration-200"
                                x-bind:class="{ 'rotate-180': jobTypeOptionalSectionOpen }"
                            />
                        </button>
                        <div
                            x-show="jobTypeOptionalSectionOpen"
                            x-transition
                            class="space-y-2 border-t border-border px-3 py-3"
                        >
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
                                            value="{{ old('thresholdCount') !== null ? old('thresholdCount') : ($alert->getThresholdCount()) }}"
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
                                            value="{{ old('thresholdMinutes') !== null ? old('thresholdMinutes') : ($alert->getThresholdMinutes()) }}"
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
                                            value="{{ old('thresholdSeconds') !== null ? old('thresholdSeconds') : ($alert->getThresholdSeconds()) }}"
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
                                            value="{{ old('thresholdMinutes') !== null ? old('thresholdMinutes') : ($alert->getThresholdMinutes()) }}"
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
                                        value="{{ old('thresholdMinutes') !== null ? old('thresholdMinutes') : ($alert->getThresholdMinutes()) }}"
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

            <div id="alert-section-notifications" class="card overflow-hidden">
                <div class="border-b border-border px-5 py-4 sm:px-6">
                    <h3 class="text-sm font-semibold text-foreground">Notifications</h3>
                    <p class="mt-1 text-sm text-muted-foreground">Control delivery throttling and choose which providers receive this alert.</p>
                </div>
                <div class="space-y-5 px-5 py-5 sm:px-6">
                    <div class="space-y-2 rounded-xl border border-border/70 bg-muted/20 px-4 py-4">
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
                            <a
                                href="{{ route('horizon.providers.index') }}"
                                class="link"
                                data-turbo-frame="_top"
                                data-turbo-action="replace"
                            >Create a provider</a>
                            on the Providers page first.
                        </p>
                    @else
                        <div class="space-y-2">
                            @php
                                $oldProviderIds = old('provider_ids', $selectedProviderIds);
                            @endphp
                            @foreach($providers as $provider)
                                @php
                                    $isSlackProvider = $provider->type === \App\Models\NotificationProvider::TYPE_SLACK;
                                @endphp
                                <div
                                    class="flex cursor-pointer items-center gap-3 rounded-xl border border-border px-3 py-2.5 transition-colors hover:bg-muted/50"
                                    role="group"
                                    tabindex="0"
                                    @click="toggleCheckboxRow($event)"
                                >
                                    <x-checkbox
                                        id="provider-{{ $provider->id }}"
                                        name="provider_ids[]"
                                        value="{{ $provider->id }}"
                                        :checked="in_array($provider->id, $oldProviderIds, true)"
                                    />
                                    <div
                                        @class([
                                            'flex size-9 shrink-0 items-center justify-center rounded-lg border',
                                            'border-violet-500/20 bg-violet-500/10 text-violet-700 dark:text-violet-300' => $isSlackProvider,
                                            'border-sky-500/20 bg-sky-500/10 text-sky-700 dark:text-sky-300' => ! $isSlackProvider,
                                        ])
                                    >
                                        @if($isSlackProvider)
                                            <x-icons.slack class="size-4" />
                                        @else
                                            <x-icons.envelope class="size-4" />
                                        @endif
                                    </div>
                                    <div class="min-w-0">
                                        <span class="text-sm font-medium">{{ $provider->name }}</span>
                                        <p class="text-xs text-muted-foreground">{{ $isSlackProvider ? 'Slack' : 'Email' }}</p>
                                    </div>
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
                    {{ $isEdit ? 'Save changes' : 'Create alert' }}
                </x-button>
                <x-button variant="ghost" type="button" class="h-9 text-sm" data-form-drawer-close>
                    Cancel
                </x-button>
            </div>
        </form>
    </div>
@endsection
