@php
    $sectionTitle ??= 'Patterns';
    $sectionOpenVar ??= 'optionalSectionOpen';
    $toggleOpenExpr ??= "$sectionOpenVar = !$sectionOpenVar";
    $ariaExpandedVar ??= $sectionOpenVar;
    $rotateClassVar ??= $sectionOpenVar;
    $hint ??= '';
    $rowsVar ??= 'patterns';
    $rowKeyPrefix ??= 'p';
    $inputName ??= 'patterns[]';
    $placeholder ??= '';
    $removeMethod ??= 'removePattern';
    $addMethod ??= 'addPattern';
    $addLabel ??= 'Add';
    $errorKey ??= 'patterns';
@endphp
<div class="overflow-hidden rounded-lg border border-border">
    <button
        type="button"
        class="flex w-full items-center justify-between gap-2 px-3 py-2.5 text-left text-sm font-medium text-foreground hover:bg-muted/50"
        @click="{{ $toggleOpenExpr }}"
        :aria-expanded="{{ $ariaExpandedVar }}"
    >
        <span>{{ $sectionTitle }}</span>
        <x-icons.chevron-down
            class="h-5 w-5 shrink-0 text-muted-foreground transition-transform duration-200"
            x-bind:class="{ 'rotate-180': {{ $rotateClassVar }} }"
        />
    </button>
    <div
        x-show="{{ $sectionOpenVar }}"
        x-transition
        class="space-y-2 border-t border-border px-3 py-3"
    >
        <p class="text-xs text-muted-foreground">{{ $hint }}</p>
        <div class="space-y-2">
            <template x-for="(row, index) in {{ $rowsVar }}" :key="'{{ $rowKeyPrefix }}-' + row.id">
                <div class="flex gap-2 items-center">
                    <input
                        type="text"
                        name="{{ $inputName }}"
                        x-model="row.value"
                        placeholder="{{ $placeholder }}"
                        class="flex-1 rounded-md border border-border bg-background px-3 py-2 text-sm font-mono text-foreground shadow-sm"
                    />
                    <x-button
                        type="button"
                        variant="ghost"
                        class="h-9 shrink-0 text-xs"
                        @click="{{ $removeMethod }}(index)"
                        x-show="{{ $rowsVar }}.length > 1"
                    >
                        Remove
                    </x-button>
                </div>
            </template>
            <x-button
                type="button"
                variant="secondary"
                class="h-9 text-sm"
                @click="{{ $addMethod }}()"
            >
                {{ $addLabel }}
            </x-button>
        </div>
        @error($errorKey) <span class="text-xs text-destructive">{{ $message }}</span> @enderror
        @error($errorKey.'.*') <span class="text-xs text-destructive">{{ $message }}</span> @enderror
    </div>
</div>
