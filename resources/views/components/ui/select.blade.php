@props(['options' => array(), 'placeholder' => ''])

@php
    $wrapperClass = $attributes->get('class', '');
    $selectAttrs = $attributes->except('class');
@endphp
<div class="relative {{ $wrapperClass }}"
    x-data="{
        open: false,
        selectedValue: '',
        get hiddenSelect() { return this.$refs.hidden; },
        get options() {
                if (!this.hiddenSelect) return [];
                return Array.from(this.hiddenSelect.options).map(function(o) {
                        return { value: o.value, label: o.textContent.trim() };
                });
        },
        get selectedLabel() {
            var opt = this.options.find(function(o) { return o.value === this.selectedValue; }.bind(this));
            return opt ? opt.label : (this.placeholder || '');
        },
        placeholder: {{ json_encode($placeholder) }},
        choose(opt) {
            this.selectedValue = opt.value;
            this.hiddenSelect.value = opt.value;
            this.hiddenSelect.dispatchEvent(new Event('input', { bubbles: true }));
            this.hiddenSelect.dispatchEvent(new Event('change', { bubbles: true }));
            this.open = false;
        },
        syncFromDom() {
            if (this.hiddenSelect) this.selectedValue = this.hiddenSelect.value;
        }
    }"
    x-init="
        const sync = () => { const el = $refs.hidden; if (el) selectedValue = el.value };
        $nextTick(sync);
        $watch('open', (open) => { if (open) sync(); });
    "
    @click.away="open = false"
    x-cloak>
    <select x-ref="hidden"
            {{ $selectAttrs->merge(array('class' => 'sr-only')) }}>
        @if($placeholder !== '')
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach($options as $value => $label)
            <option value="{{ $value }}">{{ $label }}</option>
        @endforeach
        {{ $slot }}
    </select>

    <button type="button"
            @click="open = !open"
            :aria-expanded="open"
            aria-haspopup="listbox"
            class="flex h-9 w-full items-center justify-between whitespace-nowrap rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm ring-offset-background placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-ring disabled:cursor-not-allowed disabled:opacity-50 [&>span]:line-clamp-1">
        <span x-text="selectedLabel" class="block truncate text-left"></span>
        <svg class="h-4 w-4 shrink-0 opacity-50" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
            <path d="m6 9 6 6 6-6"/>
        </svg>
    </button>

    <div x-show="open"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="absolute z-50 mt-1 max-h-60 min-w-[8rem] overflow-auto rounded-md border border-border bg-popover text-popover-foreground shadow-md p-1"
        role="listbox">
        <template x-for="opt in options" :key="opt.value">
            <button type="button"
                    @click="choose(opt)"
                    :class="opt.value === selectedValue ? 'text-accent-foreground' : ''"
                    class="relative flex w-full cursor-default select-none items-center rounded-sm py-1.5 pl-2 pr-8 text-sm outline-none hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:text-accent-foreground data-[disabled]:pointer-events-none data-[disabled]:opacity-50"
                    role="option">
                <span class="block truncate" x-text="opt.label"></span>
                <span x-show="opt.value === selectedValue" class="absolute right-2 flex h-3.5 w-3.5 items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                </span>
            </button>
        </template>
    </div>
</div>
<style>[x-cloak]{display:none!important}</style>
