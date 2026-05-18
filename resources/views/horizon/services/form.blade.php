@extends('layouts.app')

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

        if ($headersForForm === []) {
            $headersForForm[] = ['name' => '', 'value' => ''];
        }
    @endphp

    <div
        class="mx-auto max-w-3xl space-y-6"
        x-data="window.horizonServiceForm({!! \Illuminate\Support\Js::from($headersForForm) !!})"
    >
        <div class="card overflow-hidden">
            <x-page-hero
                :eyebrow="$isEdit ? 'Update service' : 'Register service'"
                :title="$isEdit ? 'Edit service' : 'Register service'"
                :description="$isEdit
                    ? 'Update the URLs and HTTP headers Horizon Hub uses to reach this deployment.'
                    : 'Add the internal URL and optional HTTP headers Horizon Hub should use to collect metrics and events.'"
            />
        </div>

        <form method="POST" action="{{ $action }}" class="space-y-6">
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
                <x-button variant="ghost" type="button" class="h-9 text-sm" onclick="window.location.href='{{ route('horizon.services.index') }}'">
                    Cancel
                </x-button>
            </div>
        </form>
    </div>
@endsection
