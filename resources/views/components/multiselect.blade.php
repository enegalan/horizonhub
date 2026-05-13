@props([
    'name' => 'service',
    'placeholder' => '',
    'selected' => [],
    'submitOnChange' => false,
    'emptyMessage' => 'No results',
])

@php
    $selectedStrings = array_values(array_map('strval', (array) $selected));
    $wrapperClass = $attributes->get('class', '');
    $domId = $attributes->get('id');
    $extraAttrs = $attributes->except(['class', 'id']);
@endphp
<div
    @if($domId) id="{{ $domId }}" @endif
    class="relative {{ $wrapperClass }}"
    {{ $extraAttrs }}
    x-data="{
        open: false,
        anchor: { top: 0, left: 0, width: 0 },
        _repositionHandler: null,
        submitOnChange: {{ $submitOnChange ? 'true' : 'false' }},
        fieldName: {{ json_encode($name.'[]') }},
        selectedValues: {{ json_encode($selectedStrings) }},
        initialSnapshot: '',
        syncHiddenInputs() {
            var host = this.$refs.hiddenInputsHost;
            if (!host) return;
            while (host.firstChild) host.removeChild(host.firstChild);
            var self = this;
            this.selectedValues.forEach(function (id) {
                var inp = document.createElement('input');
                inp.type = 'hidden';
                inp.name = self.fieldName;
                inp.value = String(id);
                host.appendChild(inp);
            });
        },
        init() {
            this.initialSnapshot = JSON.stringify(this.selectedValues.slice().sort());
            var self = this;
            this.$nextTick(function () { self.syncHiddenInputs(); });
        },
        destroy() {
            this.unbindReposition();
        },
        get options() {
            var sel = this.$refs.optionSource;
            if (!sel) return [];
            return Array.from(sel.options).map(function (o) {
                return { value: o.value, label: o.textContent.trim() };
            }).filter(function (o) { return o.value !== ''; });
        },
        get summaryLabel() {
            if (this.options.length === 0) return this.emptyMessage;
            if (this.selectedValues.length === 0) return this.placeholder || 'All';
            if (this.selectedValues.length === 1) {
                var self = this;
                var o = this.options.find(function (x) { return String(x.value) === String(self.selectedValues[0]); });
                return o ? o.label : this.selectedValues[0];
            }
            return this.selectedValues.length + ' selected';
        },
        placeholder: {{ json_encode($placeholder) }},
        emptyMessage: {{ json_encode($emptyMessage) }},
        openMenu() {
            this.open = true;
            var self = this;
            this.$nextTick(function () {
                self.updateAnchor();
                self.bindReposition();
            });
        },
        closeMenu() {
            if (!this.open) return;
            this.open = false;
            this.unbindReposition();
            this.maybeSubmitIfDirty();
        },
        toggleMenu() {
            if (this.open) {
                this.closeMenu();
            } else {
                this.openMenu();
            }
        },
        updateAnchor() {
            var trigger = this.$refs.trigger;
            if (!trigger) return;
            var rect = trigger.getBoundingClientRect();
            this.anchor = { top: rect.bottom + 4, left: rect.left, width: rect.width };
        },
        bindReposition() {
            if (this._repositionHandler) return;
            var self = this;
            this._repositionHandler = function () { self.updateAnchor(); };
            window.addEventListener('scroll', this._repositionHandler, true);
            window.addEventListener('resize', this._repositionHandler);
        },
        unbindReposition() {
            if (!this._repositionHandler) return;
            window.removeEventListener('scroll', this._repositionHandler, true);
            window.removeEventListener('resize', this._repositionHandler);
            this._repositionHandler = null;
        },
        toggle(optValue) {
            var v = String(optValue);
            var i = this.selectedValues.indexOf(v);
            if (i >= 0) {
                this.selectedValues.splice(i, 1);
            } else {
                this.selectedValues.push(v);
            }
            this.syncHiddenInputs();
            this.$dispatch('change', { values: this.selectedValues.slice() });
        },
        isSelected(optValue) {
            return this.selectedValues.indexOf(String(optValue)) >= 0;
        },
        maybeSubmitIfDirty() {
            this.syncHiddenInputs();
            var snap = JSON.stringify(this.selectedValues.slice().sort());
            if (this.submitOnChange && snap !== this.initialSnapshot) {
                this.initialSnapshot = snap;
                var form = this.$el.closest('form');
                if (form) {
                    if (typeof form.requestSubmit === 'function') {
                        form.requestSubmit();
                    } else {
                        form.submit();
                    }
                }
            }
        },
    }"
    @click.away="closeMenu()"
>
    <select x-ref="optionSource" multiple class="sr-only" tabindex="-1" aria-hidden="true">
        {{ $slot }}
    </select>
    <div x-ref="hiddenInputsHost" class="hidden" aria-hidden="true"></div>

    <button
        type="button"
        x-ref="trigger"
        @click="toggleMenu()"
        :aria-expanded="open"
        aria-haspopup="listbox"
        class="btn-ghost flex h-9 w-full min-w-[8rem] items-center justify-between whitespace-nowrap rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm ring-offset-background placeholder:text-muted-foreground disabled:cursor-not-allowed disabled:opacity-50 [&>span]:line-clamp-1"
    >
        <span x-text="summaryLabel" class="block truncate text-left"></span>
        <x-heroicon-o-chevron-down class="h-4 w-4 shrink-0 opacity-50" />
    </button>

    <div
        x-ref="panel"
        x-show="open"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        x-bind:style="{ top: anchor.top + 'px', left: anchor.left + 'px', minWidth: Math.max(anchor.width, 192) + 'px' }"
        class="fixed z-50 max-h-60 overflow-auto rounded-md border border-border bg-popover p-1 text-popover-foreground shadow-md"
        role="listbox"
    >
        <div
            x-show="options.length === 0"
            class="px-2 py-1.5 text-sm text-muted-foreground select-none"
            x-text="emptyMessage"
            role="presentation"
        ></div>
        <template x-for="opt in options" :key="opt.value">
            <button
                type="button"
                @click.stop="toggle(opt.value)"
                class="btn-ghost relative flex w-full cursor-default select-none items-center justify-start rounded-sm py-1.5 pl-2 pr-8 text-sm outline-none hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:text-accent-foreground"
                role="option"
            >
                <span class="block truncate text-left" x-text="opt.label"></span>
                <span x-show="isSelected(opt.value)" class="absolute right-2 flex h-3.5 w-3.5 items-center justify-center">
                    <x-heroicon-o-check class="h-3.5 w-3.5" />
                </span>
            </button>
        </template>
    </div>
</div>
