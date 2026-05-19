@php
    /** @var \App\Models\Alert $alert */
@endphp
@forelse($alerts as $alert)
    @php
        $serviceLabels = isset($serviceLabelsByAlertId) ? ($serviceLabelsByAlertId[$alert->id] ?? []) : [];
        $queuePatterns = isset($alert->threshold['queue_patterns']) && \is_array($alert->threshold['queue_patterns']) ? $alert->threshold['queue_patterns'] : [];
        $jobPatterns = isset($alert->threshold['job_patterns']) && \is_array($alert->threshold['job_patterns']) ? $alert->threshold['job_patterns'] : [];
        $queueSummary = \count($queuePatterns) > 1
            ? $queuePatterns[0] . ' (+' . (\count($queuePatterns) - 1) . ')'
            : (\count($queuePatterns) === 1 ? $queuePatterns[0] : ($alert->queue ?? 'All queues'));
        $jobSummary = \count($jobPatterns) > 1
            ? \Illuminate\Support\Str::limit((string) $jobPatterns[0], 24) . ' (+' . (\count($jobPatterns) - 1) . ')'
            : (\count($jobPatterns) === 1
                ? \Illuminate\Support\Str::limit((string) $jobPatterns[0], 32)
                : ($alert->job_type ? \Illuminate\Support\Str::limit($alert->job_type, 32) : 'All jobs'));
    @endphp
    <article
        class="card group relative overflow-hidden transition-colors hover:border-primary/30"
        data-stream-row-id="alt-{{ (int) $alert->id }}"
    >
        <div
            @class([
                'absolute inset-x-0 top-0 h-1',
                'bg-gradient-to-r from-emerald-500/80 via-emerald-400/60 to-transparent' => $alert->enabled,
                'bg-gradient-to-r from-amber-500/80 via-amber-400/60 to-transparent' => ! $alert->enabled,
            ])
            data-alert-enabled-accent="1"
            aria-hidden="true"
        ></div>

        <div class="flex h-full flex-col p-4">
            <div class="flex items-start justify-between gap-3" data-stream-preserve-client>
                <div class="flex min-w-0 items-start gap-3">
                    <div
                        @class([
                            'flex size-11 shrink-0 items-center justify-center rounded-xl border',
                            'border-emerald-500/20 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300' => $alert->enabled,
                            'border-amber-500/20 bg-amber-500/10 text-amber-700 dark:text-amber-300' => ! $alert->enabled,
                        ])
                        data-alert-enabled-icon="1"
                    >
                        <x-heroicon-o-bell class="size-5" />
                    </div>
                    <div class="min-w-0">
                        <a href="{{ route('horizon.alerts.show', $alert) }}" class="link truncate text-sm font-semibold text-foreground" data-turbo-action="replace">
                            {{ $alert->name ?: ('Alert #' . $alert->id) }}
                        </a>
                        <p class="mt-1 font-mono text-xs text-muted-foreground">{{ $alert->rule_type }}</p>
                    </div>
                </div>
                <button
                    type="button"
                    class="alert-enabled-toggle flex shrink-0 rounded-md transition-opacity hover:opacity-80 disabled:cursor-wait disabled:opacity-60"
                    data-alert-enabled-toggle="1"
                    data-alert-id="{{ (int) $alert->id }}"
                    data-alert-enabled="{{ $alert->enabled ? '1' : '0' }}"
                    data-alert-enabled-toggle-url="{{ route('horizon.alerts.toggle-enabled', $alert) }}"
                    aria-pressed="{{ $alert->enabled ? 'true' : 'false' }}"
                    aria-label="{{ $alert->enabled ? 'Disable alert' : 'Enable alert' }}"
                    title="{{ $alert->enabled ? 'Disable alert' : 'Enable alert' }}"
                >
                    <span
                        class="{{ $alert->enabled ? 'badge-success' : 'badge-danger' }}"
                        data-alert-enabled-badge="1"
                    >
                        {{ $alert->enabled ? 'On' : 'Off' }}
                    </span>
                </button>
            </div>

            <div class="mt-4 space-y-2 rounded-lg border border-border/70 bg-muted/20 px-3 py-2.5">
                <div>
                    <p class="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">Scope</p>
                    <p class="mt-1 text-xs text-foreground/90">
                        @if(\count($serviceLabels) > 0)
                            {{ \count($serviceLabels) === 1 ? $serviceLabels[0] : \implode(', ', $serviceLabels) }}
                        @else
                            No services selected
                        @endif
                    </p>
                </div>
                <div class="grid gap-2 sm:grid-cols-2">
                    <div>
                        <p class="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">Queue</p>
                        <p class="mt-1 truncate font-mono text-xs text-foreground/90">{{ $queueSummary }}</p>
                    </div>
                    <div>
                        <p class="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">Job</p>
                        <p class="mt-1 truncate font-mono text-xs text-foreground/90">{{ $jobSummary }}</p>
                    </div>
                </div>
                <div>
                    <p class="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">Last triggered</p>
                    <p class="mt-1 text-xs text-foreground/90">
                        @if($alert->alert_logs_max_sent_at)
                            {{ \Carbon\Carbon::parse($alert->alert_logs_max_sent_at)->diffForHumans() }}
                        @else
                            Never
                        @endif
                    </p>
                </div>
            </div>

            <div class="mt-4 flex items-center justify-end gap-2" data-stream-preserve-client>
                <x-button
                    variant="ghost"
                    type="button"
                    class="h-8 min-h-8 px-2.5 text-xs alert-evaluate-btn"
                    disabled="{{ !$alert->enabled }}"
                    aria-label="Evaluate alert"
                    title="Evaluate alert"
                    data-alert-evaluate-button="1"
                    data-alert-id="{{ (int) $alert->id }}"
                    data-alert-evaluate-url="{{ route('horizon.alerts.evaluate', $alert) }}"
                    data-alert-evaluate-initial-disabled="{{ $alert->enabled ? '0' : '1' }}"
                >
                    <span class="inline-flex items-center justify-center gap-1">
                        <x-heroicon-o-arrow-path class="size-4 alert-evaluate-btn-icon" />
                        <x-heroicon-o-arrow-path class="size-4 animate-spin alert-evaluate-btn-spinner hidden" />
                        <span>Evaluate</span>
                    </span>
                </x-button>
                <x-button
                    variant="ghost"
                    type="button"
                    class="h-8 min-h-8 px-2.5 text-xs"
                    aria-label="Edit"
                    title="Edit"
                    onclick="window.location.href='{{ route('horizon.alerts.edit', $alert) }}'"
                >
                    <x-heroicon-o-pencil-square class="size-4" />
                    <span>Edit</span>
                </x-button>
                <x-button
                    variant="ghost"
                    type="button"
                    class="h-8 min-h-8 px-2.5 text-xs text-destructive hover:text-destructive"
                    aria-label="Delete"
                    title="Delete"
                    x-on:click="openDeleteAlertModal({{ \Illuminate\Support\Js::from($alert->name ?: ('#' . $alert->id)) }}, {{ \Illuminate\Support\Js::from(route('horizon.alerts.destroy', $alert)) }})"
                >
                    <x-heroicon-o-trash class="size-4" />
                    <span>Delete</span>
                </x-button>
            </div>
        </div>
    </article>
@empty
    <div class="card p-8 sm:col-span-2 xl:col-span-3">
        <x-empty-state
            title="No alerts"
            description="Create an alert rule to get notified when jobs fail, queues block, or workers go offline."
        >
            <x-slot name="icon">
                <x-heroicon-o-bell class="empty-state-icon" />
            </x-slot>
            <x-button
                type="button"
                class="mt-3 h-9 text-sm"
                onclick="window.location.href='{{ route('horizon.alerts.create') }}'"
            >
                New alert
            </x-button>
        </x-empty-state>
    </div>
@endforelse
