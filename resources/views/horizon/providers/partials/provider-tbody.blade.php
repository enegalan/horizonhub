@php
    /** @var \App\Models\NotificationProvider $provider */
@endphp
@forelse($providers as $provider)
    @php
        $isSlack = $provider->type === \App\Models\NotificationProvider::TYPE_SLACK;
        $configSummary = $isSlack
            ? ($provider->getWebhookUrl() ?: 'No webhook configured')
            : (\implode(', ', $provider->getToEmails()) ?: 'No recipients configured');
    @endphp
    <article
        class="card group relative overflow-hidden transition-colors hover:border-primary/30"
        data-stream-row-id="prv-{{ (int) $provider->id }}"
    >
        <div
            @class([
                'absolute inset-x-0 top-0 h-1',
                'bg-gradient-to-r from-violet-500/80 via-violet-400/60 to-transparent' => $isSlack,
                'bg-gradient-to-r from-sky-500/80 via-sky-400/60 to-transparent' => ! $isSlack,
            ])
            aria-hidden="true"
        ></div>

        <div class="flex h-full flex-col p-4">
            <div class="flex items-start justify-between gap-3">
                <div class="flex min-w-0 items-start gap-3">
                    <div
                        @class([
                            'flex size-11 shrink-0 items-center justify-center rounded-xl border',
                            'border-violet-500/20 bg-violet-500/10 text-violet-700 dark:text-violet-300' => $isSlack,
                            'border-sky-500/20 bg-sky-500/10 text-sky-700 dark:text-sky-300' => ! $isSlack,
                        ])
                    >
                        @if($isSlack)
                            <x-icons.slack class="size-5" />
                        @else
                            <x-heroicon-o-envelope class="size-5" />
                        @endif
                    </div>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-foreground">{{ $provider->name }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ $isSlack ? 'Slack webhook' : 'Email recipients' }}
                        </p>
                    </div>
                </div>
                <span
                    @class([
                        'badge shrink-0 border-transparent',
                        'bg-violet-500/15 text-violet-700 dark:text-violet-300' => $isSlack,
                        'bg-sky-500/15 text-sky-700 dark:text-sky-300' => ! $isSlack,
                    ])
                >
                    {{ $isSlack ? 'Slack' : 'Email' }}
                </span>
            </div>

            <div class="mt-4 rounded-lg border border-border/70 bg-muted/20 px-3 py-2.5">
                <p class="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                    {{ $isSlack ? 'Webhook' : 'Recipients' }}
                </p>
                <p class="mt-1 break-all font-mono text-xs text-foreground/90">
                    {{ $configSummary }}
                </p>
            </div>

            <div class="mt-4 flex items-center justify-end gap-2" data-stream-preserve-client>
                <x-button
                    variant="ghost"
                    type="button"
                    class="h-8 min-h-8 px-2.5 text-xs"
                    onclick="window.location.href='{{ route('horizon.providers.edit', $provider) }}'"
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
                    x-on:click="openDeleteProviderModal({{ \Illuminate\Support\Js::from($provider->name) }}, {{ \Illuminate\Support\Js::from(route('horizon.providers.destroy', $provider)) }})"
                >
                    <x-heroicon-o-trash class="size-4" />
                    <span>Delete</span>
                </x-button>
            </div>
        </div>
    </article>
@empty
    <div class="card p-8 sm:col-span-2 xl:col-span-3">
        <div class="empty-state">
            <x-heroicon-o-megaphone class="empty-state-icon" />
            <p class="empty-state-title">No providers yet</p>
            <p class="empty-state-description">Create Slack or email providers, then select them when creating alerts.</p>
            <x-button
                type="button"
                class="mt-3 h-9 text-sm"
                onclick="window.location.href='{{ route('horizon.providers.create') }}'"
            >
                New provider
            </x-button>
        </div>
    </div>
@endforelse
