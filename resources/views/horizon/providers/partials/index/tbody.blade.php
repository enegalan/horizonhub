@php
    /** @var \App\Models\NotificationProvider $provider */
@endphp
@forelse($providers as $provider)
    @php
        $providerMeta = $provider->meta();
        $usesWebhook = $provider->usesWebhook();
        $configSummary = $usesWebhook
            ? ($provider->getWebhookUrl() ?: 'No webhook configured')
            : (\implode(', ', $provider->getToEmails()) ?: 'No recipients configured');
        $emailsForSig = $provider->getToEmails();
        \sort($emailsForSig);

        $streamSig = \hash('sha256', \json_encode([
            'id' => (int) $provider->id,
            'name' => (string) ($provider->name ?? ''),
            'type' => (string) ($provider->type ?? ''),
            'webhook_url' => $usesWebhook ? (string) $provider->getWebhookUrl() : '',
            'to_emails' => $usesWebhook ? [] : $emailsForSig,
        ], \JSON_THROW_ON_ERROR));

        $configLabel = $usesWebhook ? 'Webhook' : 'Recipients';
        $subtitle = $usesWebhook
            ? "{$providerMeta['label']} webhook"
            : "{$providerMeta['label']} recipients";
    @endphp
    <article
        class="card group relative overflow-hidden transition-colors hover:border-primary/30"
        data-stream-row-id="prv-{{ (int) $provider->id }}"
        data-horizon-stream-sig="{{ $streamSig }}"
    >
        <div
            class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-{{ $providerMeta['color'] }}-500/80 via-{{ $providerMeta['color'] }}-400/60 to-transparent"
            aria-hidden="true"
        ></div>

        <div class="flex h-full flex-col p-4">
            <div class="flex items-start justify-between gap-3">
                <div class="flex min-w-0 items-start gap-3">
                    <div class="flex size-11 shrink-0 items-center justify-center rounded-xl border border-{{ $providerMeta['color'] }}-500/20 bg-{{ $providerMeta['color'] }}-500/10 text-{{ $providerMeta['color'] }}-700 dark:text-{{ $providerMeta['color'] }}-300">
                        <x-dynamic-component :component="'icons.' . $providerMeta['icon']" class="size-5" />
                    </div>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-foreground">{{ $provider->name }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ $subtitle }}
                        </p>
                    </div>
                </div>
                <span class="badge shrink-0 border-transparent bg-{{ $providerMeta['color'] }}-500/15 text-{{ $providerMeta['color'] }}-700 dark:text-{{ $providerMeta['color'] }}-300">
                    {{ $providerMeta['label'] }}
                </span>
            </div>

            <div class="mt-4 rounded-lg border border-border/70 bg-muted/20 px-3 py-2.5">
                <p class="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                    {{ $configLabel }}
                </p>
                <p class="mt-1 break-all font-mono text-xs text-foreground/90">
                    {{ $configSummary }}
                </p>
            </div>

            <div class="mt-4 flex items-center justify-end gap-2" data-stream-preserve-client>
                <x-form-drawer-link
                    :href="route('horizon.providers.edit', $provider)"
                    variant="ghost"
                    class="h-8 min-h-8 px-2.5 text-xs"
                >
                    <x-icons.pencil-square class="size-4" />
                    <span>Edit</span>
                </x-form-drawer-link>
                <x-button
                    variant="ghost"
                    type="button"
                    class="h-8 min-h-8 px-2.5 text-xs text-destructive hover:text-destructive"
                    aria-label="Delete"
                    title="Delete"
                    x-on:click="openDeleteProviderModal({{ \Illuminate\Support\Js::from($provider->name) }}, {{ \Illuminate\Support\Js::from(route('horizon.providers.destroy', $provider)) }})"
                >
                    <x-icons.trash class="size-4" />
                    <span>Delete</span>
                </x-button>
            </div>
        </div>
    </article>
@empty
    <div class="card p-8 sm:col-span-2 xl:col-span-3">
        <x-empty-state
            title="No providers yet"
            description="Create Slack, Discord, or email providers, then select them when creating alerts."
        >
            <x-slot name="icon">
                <x-icons.megaphone class="empty-state-icon" />
            </x-slot>
            <x-form-drawer-link :href="route('horizon.providers.create')" class="mt-3 h-9 text-sm">
                New provider
            </x-form-drawer-link>
        </x-empty-state>
    </div>
@endforelse
