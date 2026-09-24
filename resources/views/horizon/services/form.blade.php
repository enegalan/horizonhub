@extends('layouts.form-drawer')

@section('content')
    @php
        $isEdit = $service->exists;
        $action = $isEdit ? route('horizon.services.update', $service) : route('horizon.services.store');
        $headersForForm = [];

        if ($isEdit) {
            foreach ($service->headers as $headerRow) {
                $headersForForm[] = [
                    'name' => $headerRow->name,
                    'value' => $headerRow->value ?? '',
                ];
            }
        }

        if (empty($headersForForm)) {
            $headersForForm[] = ['name' => '', 'value' => ''];
        }

        $tagsForForm = $isEdit ? $service->tags : [];
        $tlsFilesForForm = [
            'certName' => filled($service->tls_client_cert_path) ? \basename((string) $service->tls_client_cert_path) : '',
            'keyName' => filled($service->tls_client_key_path) ? \basename((string) $service->tls_client_key_path) : '',
        ];
    @endphp

    <div
        class="space-y-6"
        x-data="window.horizonServiceForm({!! \Illuminate\Support\Js::from($headersForForm) !!}, {!! \Illuminate\Support\Js::from($tagsForForm) !!}, {!! \Illuminate\Support\Js::from($existingTags ?? []) !!}, {!! \Illuminate\Support\Js::from(old('tls_client_mode', $service->tls_client_mode?->value ?? '')) !!}, {!! \Illuminate\Support\Js::from($tlsFilesForForm) !!})"
    >
        <form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="space-y-6" data-turbo-frame="form-drawer">
            @csrf
            @if($isEdit)
                @method('PUT')
            @endif

            <div class="card overflow-hidden">
                <div class="border-b border-border px-5 py-4 sm:px-6">
                    <h3 class="text-sm font-semibold text-foreground">Connection details</h3>
                    <p class="mt-1 text-sm text-muted-foreground">Keep the internal base URL accurate so metrics and events continue to sync.</p>
                </div>
                <div class="space-y-4 px-5 py-5 sm:px-6">
                    <div class="space-y-2">
                        <x-input-label>Name</x-input-label>
                        <x-text-input type="text" name="name" value="{{ $service->name }}" class="w-full" />
                        @error('name') <span class="text-xs text-destructive">{{ $message }}</span> @enderror
                    </div>
                    <div class="space-y-2 rounded-xl border border-border/70 bg-muted/20 px-4 py-4">
                        <x-input-label>Base URL</x-input-label>
                        <x-text-input type="url" name="base_url" value="{{ $service->exists ? $service->base_url : '' }}" class="w-full font-mono text-sm" />
                        @error('base_url') <span class="text-xs text-destructive">{{ $message }}</span> @enderror
                        <p class="text-xs text-muted-foreground">
                            Internal URL used to obtain events from the service.
                        </p>
                    </div>
                    <div class="space-y-2 rounded-xl border border-border/70 bg-muted/20 px-4 py-4">
                        <x-input-label>Public URL (optional)</x-input-label>
                        <x-text-input type="url" name="public_url" value="{{ $service->exists ? $service->public_url : '' }}" class="w-full font-mono text-sm" />
                        @error('public_url') <span class="text-xs text-destructive">{{ $message }}</span> @enderror
                        <p class="text-xs text-muted-foreground">
                            URL reachable from your browser.
                        </p>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="border-b border-border px-5 py-4 sm:px-6">
                    <h3 class="text-sm font-semibold text-foreground">Tags</h3>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Group services for filters.
                    </p>
                </div>
                <div class="flex flex-wrap gap-4 px-5 py-5 sm:px-6">
                    <div class="flex max-h-36 flex-wrap gap-2 overflow-y-auto rounded-md" x-show="tags.length > 0">
                        <template x-for="(tag, index) in tags" :key="'tag-' + index">
                            <span class="inline-flex items-center gap-1 rounded-full border border-border bg-muted/40 px-2.5 py-1 text-xs text-foreground">
                                <span x-text="tag"></span>
                                <input type="hidden" x-bind:name="'tags[]'" x-bind:value="tag" />
                                <button type="button" class="text-muted-foreground hover:text-foreground" @click="removeTag(index)" aria-label="Remove tag" no-ring>
                                    <x-icons.x-mark class="h-3 w-3" />
                                </button>
                            </span>
                        </template>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 w-full">
                        <div class="relative min-w-0 flex-1 space-y-2" @click.outside="closeTagSuggestions()">
                            <x-input-label for="service-tag-input">Add tag</x-input-label>
                            <div class="flex items-center gap-2">
                                <x-text-input
                                    id="service-tag-input"
                                    type="text"
                                    class="w-full"
                                    x-model="tagInput"
                                    autocomplete="off"
                                    role="combobox"
                                    aria-autocomplete="list"
                                    aria-controls="service-tag-suggestions"
                                    x-bind:aria-expanded="tagSuggestionsOpen && tagSuggestions.length > 0 ? 'true' : 'false'"
                                    @focus="openTagSuggestions()"
                                    @input="openTagSuggestions()"
                                    @keydown.arrow-down.prevent="highlightNextTagSuggestion()"
                                    @keydown.arrow-up.prevent="highlightPreviousTagSuggestion()"
                                    @keydown.escape="closeTagSuggestions()"
                                    @keydown.enter.prevent="hasHighlightedTagSuggestion() ? selectHighlightedTagSuggestion() : addTag()"
                                />
                                <ul
                                    id="service-tag-suggestions"
                                    role="listbox"
                                    class="absolute z-20 mt-1 max-h-48 w-full overflow-y-auto rounded-md border border-border bg-background py-1 shadow-md"
                                    x-show="tagSuggestionsOpen && tagSuggestions.length > 0"
                                    x-cloak
                                >
                                    <template x-for="(suggestion, index) in tagSuggestions" :key="'tag-suggestion-' + suggestion">
                                        <li role="option">
                                            <button
                                                type="button"
                                                class="flex w-full px-3 py-2 text-left text-sm text-foreground hover:bg-muted/60"
                                                x-bind:class="{ 'bg-muted/60': tagSuggestionHighlight === index }"
                                                x-text="suggestion"
                                                @mousedown.prevent="selectTagSuggestion(suggestion)"
                                            ></button>
                                        </li>
                                    </template>
                                </ul>
                                <p class="text-xs text-muted-foreground" x-show="existingTags.length > 0">
                                    Pick an existing tag from the list or type a new one.
                                </p>
                                <x-button type="button" variant="secondary" class="h-9 shrink-0 text-sm" x-bind:disabled="!canAddTag()" @click="addTag()">
                                    Add
                                </x-button>
                            </div>
                        </div>
                    </div>
                    @error('tags') <span class="text-xs text-destructive">{{ $message }}</span> @enderror
                    @error('tags.*') <span class="text-xs text-destructive">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="card overflow-hidden">
                <div class="border-b border-border px-5 py-4 sm:px-6">
                    <h3 class="text-sm font-semibold text-foreground">HTTP headers</h3>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Optional headers sent on every HTTP request to this service's Horizon API.
                    </p>
                </div>
                <div class="flex flex-wrap gap-4 px-5 py-5 sm:px-6">
                    <template x-for="(header, index) in headers" :key="'hdr-' + index">
                        <div class="w-full grid gap-3 sm:grid-cols-2 sm:items-start">
                            <div class="space-y-2">
                                <label class="block text-sm font-medium leading-none text-muted-foreground peer-disabled:opacity-70" x-bind:for="'header-name-' + index">Name</label>
                                <input
                                    type="text"
                                    class="flex h-9 w-full rounded-md border border-border bg-background px-3 py-1 text-sm font-mono text-foreground shadow-sm"
                                    x-bind:id="'header-name-' + index"
                                    x-bind:name="'headers[' + index + '][name]'"
                                    x-model="headers[index].name"
                                    placeholder="Authorization"
                                />
                            </div>
                            <div class="space-y-2">
                                <label class="block text-sm font-medium leading-none text-muted-foreground peer-disabled:opacity-70" x-bind:for="'header-value-' + index">Value (optional)</label>
                                <div class="flex gap-2">
                                    <input
                                        type="text"
                                        class="flex h-9 min-w-0 flex-1 rounded-md border border-border bg-background px-3 py-1 text-sm font-mono text-foreground shadow-sm"
                                        x-bind:id="'header-value-' + index"
                                        x-bind:name="'headers[' + index + '][value]'"
                                        x-model="headers[index].value"
                                        placeholder="Bearer <token>"
                                    />
                                    <x-button
                                        type="button"
                                        variant="ghost"
                                        class="h-9 shrink-0 text-xs"
                                        @click="removeHeader(index)"
                                        x-show="headers.length > 1"
                                    >
                                        Remove
                                    </x-button>
                                </div>
                            </div>
                        </div>
                    </template>
                    <x-button
                        type="button"
                        variant="secondary"
                        class="h-9 text-sm"
                        x-bind:disabled="!canAddHeader()"
                        @click="addHeader()"
                    >
                        Add header
                    </x-button>
                    @error('headers')
                        <span class="text-xs text-destructive">{{ $message }}</span>
                    @enderror
                    @if ($errors->has('headers.*.name') || $errors->has('headers.*.value'))
                        <ul class="space-y-1 text-xs text-destructive">
                            @foreach ($errors->get('headers.*.name') as $message)
                                <li>{{ $message }}</li>
                            @endforeach
                            @foreach ($errors->get('headers.*.value') as $message)
                                <li>{{ $message }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>

            <div class="card overflow-hidden">
                <div class="border-b border-border px-5 py-4 sm:px-6">
                    <h3 class="text-sm font-semibold text-foreground">Client TLS (mTLS)</h3>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Optional. Upload a client certificate when the upstream Horizon API requires mTLS. Files are stored privately on the Horizon Hub server.
                    </p>
                </div>
                <div class="space-y-4 px-5 py-5 sm:px-6">
                    <div class="space-y-2">
                        <x-input-label for="tls_client_mode">Mode</x-input-label>
                        <x-select
                            id="tls_client_mode"
                            name="tls_client_mode"
                            class="w-full"
                            :searchable="false"
                            @change="tlsClientMode = $event.target.value"
                        >
                            <option value="" @selected(old('tls_client_mode', $service->tls_client_mode?->value ?? '') === '')>None</option>
                            @foreach (\App\Enums\TlsClientMode::options() as $value => $label)
                                <option value="{{ $value }}" @selected(old('tls_client_mode', $service->tls_client_mode?->value ?? '') === $value)>{{ $label }}</option>
                            @endforeach
                        </x-select>
                        @error('tls_client_mode') <span class="text-xs text-destructive">{{ $message }}</span> @enderror
                    </div>

                    <div class="space-y-2" x-show="tlsClientMode === 'pem' || tlsClientMode === 'p12'" x-cloak>
                        <x-input-label for="tls_client_cert">
                            <span x-show="tlsClientMode === 'pem'">Certificate (.crt / .pem)</span>
                            <span x-show="tlsClientMode === 'p12'">PKCS#12 (.p12 / .pfx)</span>
                        </x-input-label>
                        <input type="hidden" name="tls_client_remove_cert" x-bind:value="tlsRemoveCert ? '1' : '0'" />
                        <div
                            x-show="tlsCertOnFile"
                            class="flex items-center gap-2 bg-background rounded-md border border-border px-3 py-1"
                        >
                            <code class="min-w-0 flex-1 truncate font-mono text-xs text-foreground" x-text="tlsCertName" x-bind:title="tlsCertName"></code>
                            <x-button
                                type="button"
                                variant="ghost"
                                class="h-8 shrink-0 px-2 text-destructive hover:text-destructive"
                                @click="removeTlsCert()"
                                aria-label="Remove certificate"
                                title="Remove certificate"
                            >
                                <x-icons.trash class="size-4" />
                            </x-button>
                        </div>
                        <input
                            id="tls_client_cert"
                            type="file"
                            name="tls_client_cert"
                            x-show="!tlsCertOnFile"
                            class="flex h-9 w-full rounded-md border border-border bg-background px-3 py-1.5 text-sm text-foreground shadow-sm file:mr-3 file:rounded file:border-0 file:bg-muted file:px-2 file:py-0.5 file:text-xs file:font-medium"
                        />
                        @error('tls_client_cert') <span class="text-xs text-destructive">{{ $message }}</span> @enderror
                    </div>

                    <div class="space-y-2" x-show="tlsClientMode === 'pem'" x-cloak>
                        <x-input-label for="tls_client_key">Private key (.key / .pem)</x-input-label>
                        <input type="hidden" name="tls_client_remove_key" x-bind:value="tlsRemoveKey ? '1' : '0'" />
                        <div
                            x-show="tlsKeyOnFile"
                            class="flex items-center gap-2 bg-background rounded-md border border-border px-3 py-1"
                        >
                            <code class="min-w-0 flex-1 truncate font-mono text-xs text-foreground" x-text="tlsKeyName" x-bind:title="tlsKeyName"></code>
                            <x-button
                                type="button"
                                variant="ghost"
                                class="h-8 shrink-0 px-2 text-destructive hover:text-destructive"
                                @click="removeTlsKey()"
                                aria-label="Remove private key"
                                title="Remove private key"
                            >
                                <x-icons.trash class="size-4" />
                            </x-button>
                        </div>
                        <input
                            id="tls_client_key"
                            type="file"
                            name="tls_client_key"
                            x-show="!tlsKeyOnFile"
                            class="flex h-9 w-full rounded-md border border-border bg-background px-3 py-1.5 text-sm text-foreground shadow-sm file:mr-3 file:rounded file:border-0 file:bg-muted file:px-2 file:py-0.5 file:text-xs file:font-medium"
                        />
                        @error('tls_client_key') <span class="text-xs text-destructive">{{ $message }}</span> @enderror
                    </div>

                    <div class="space-y-2" x-show="tlsClientMode === 'pem' || tlsClientMode === 'p12'" x-cloak>
                        <x-input-label for="tls_client_passphrase">Passphrase (optional)</x-input-label>
                        <div class="relative">
                            <x-text-input
                                id="tls_client_passphrase"
                                type="password"
                                name="tls_client_passphrase"
                                value=""
                                class="w-full pr-10 font-mono text-sm"
                                autocomplete="new-password"
                                x-bind:type="showTlsPassphrase ? 'text' : 'password'"
                                placeholder="{{ ! blank($service->getAttributes()['tls_client_passphrase'] ?? null) ? 'Leave blank to keep current passphrase' : 'Optional' }}"
                            />
                            <button
                                type="button"
                                class="absolute inset-y-0 right-0 flex items-center px-3 text-muted-foreground hover:text-foreground"
                                @click="showTlsPassphrase = !showTlsPassphrase"
                                x-bind:aria-label="showTlsPassphrase ? 'Hide passphrase' : 'Show passphrase'"
                                x-bind:title="showTlsPassphrase ? 'Hide passphrase' : 'Show passphrase'"
                            >
                                <x-icons.eye class="size-4" x-show="!showTlsPassphrase" x-cloak />
                                <x-icons.eye-slash class="size-4" x-show="showTlsPassphrase" x-cloak />
                            </button>
                        </div>
                        @error('tls_client_passphrase') <span class="text-xs text-destructive">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <x-button type="submit" class="h-9 text-sm relative inline-flex items-center justify-center">
                    {{ $isEdit ? 'Save changes' : 'Register service' }}
                </x-button>
                <x-button variant="ghost" type="button" class="h-9 text-sm" data-form-drawer-close>
                    Cancel
                </x-button>
            </div>
        </form>
    </div>
@endsection
