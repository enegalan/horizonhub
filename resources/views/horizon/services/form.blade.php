@extends('layouts.form-drawer')

@section('content')
    @php
        $isEdit = $service->exists;
        $action = $isEdit ? route('horizon.services.update', $service) : route('horizon.services.store');
        $headersForForm = [];
        $oldHeaders = old('headers');

        if (\is_array($oldHeaders)) {
            foreach ($oldHeaders as $headerRow) {
                if (! \is_array($headerRow)) {
                    continue;
                }

                $headersForForm[] = [
                    'name' => (string) ($headerRow['name'] ?? ''),
                    'value' => (string) ($headerRow['value'] ?? ''),
                ];
            }
        } elseif ($isEdit) {
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

        $tagsForForm = [];
        $oldTags = old('tags');

        if (\is_array($oldTags)) {
            foreach ($oldTags as $tag) {
                if (\is_string($tag) && \trim($tag) !== '') {
                    $tagsForForm[] = \trim($tag);
                }
            }
        } elseif ($isEdit) {
            $tagsForForm = $service->tags ?? [];
        }
    @endphp

    <div
        class="space-y-6"
        x-data="window.horizonServiceForm({!! \Illuminate\Support\Js::from($headersForForm) !!}, {!! \Illuminate\Support\Js::from($tagsForForm) !!}, {!! \Illuminate\Support\Js::from($existingTags ?? []) !!})"
    >
        <form method="POST" action="{{ $action }}" class="space-y-6" data-turbo-frame="form-drawer">
            @csrf
            @if($isEdit)
                @method('PUT')
            @endif

            <div class="card overflow-hidden">
                <div class="border-b border-border px-5 py-4 sm:px-6">
                    <h3 class="text-sm font-semibold text-foreground">Connection details</h3>
                    <p class="mt-1 text-sm text-muted-foreground">Keep the internal base URL accurate so metrics and events continue to sync.</p>
                </div>
                <div class="space-y-5 px-5 py-5 sm:px-6">
                    <div class="space-y-2">
                        <x-input-label>Name</x-input-label>
                        <x-text-input type="text" name="name" value="{{ old('name', $service->name) }}" class="w-full" />
                        @error('name') <span class="text-xs text-destructive">{{ $message }}</span> @enderror
                    </div>
                    <div class="space-y-2 rounded-xl border border-border/70 bg-muted/20 px-4 py-4">
                        <x-input-label>Base URL</x-input-label>
                        <x-text-input type="url" name="base_url" value="{{ old('base_url', $service->exists ? $service->getBaseUrl() : '') }}" class="w-full font-mono text-sm" />
                        @error('base_url') <span class="text-xs text-destructive">{{ $message }}</span> @enderror
                        <p class="text-xs text-muted-foreground">
                            Internal URL used to obtain events from the service.
                        </p>
                    </div>
                    <div class="space-y-2 rounded-xl border border-border/70 bg-muted/20 px-4 py-4">
                        <x-input-label>Public URL (optional)</x-input-label>
                        <x-text-input type="url" name="public_url" value="{{ old('public_url', $service->exists ? $service->public_url : '') }}" class="w-full font-mono text-sm" />
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
                <div class="space-y-4 px-5 py-5 sm:px-6">
                    <div class="flex max-h-36 flex-wrap gap-2 overflow-y-auto rounded-md border border-border/60 p-2" x-show="tags.length > 0">
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
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="relative min-w-0 flex-1 space-y-2" @click.outside="closeTagSuggestions()">
                            <x-input-label for="service-tag-input">Add tag</x-input-label>
                            <x-text-input
                                id="service-tag-input"
                                type="text"
                                class="w-full"
                                x-model="tagInput"
                                placeholder="production"
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
                        </div>
                        <x-button type="button" variant="secondary" class="h-9 shrink-0 text-sm" @click="addTag()">
                            Add
                        </x-button>
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
                <div class="space-y-4 px-5 py-5 sm:px-6">
                    <template x-for="(header, index) in headers" :key="'hdr-' + index">
                        <div class="grid gap-3 sm:grid-cols-2 sm:items-start">
                            <div class="space-y-2">
                                <label class="text-sm font-medium text-foreground" x-bind:for="'header-name-' + index">Name</label>
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
                                <label class="text-sm font-medium text-foreground" x-bind:for="'header-value-' + index">Value (optional)</label>
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
