@props([
    'multiple' => false,
    'selected' => [],
    'placeholder' => '',
    'emptyMessage' => 'No results',
    'searchable' => true,
    'submitOnChange' => false,
    'labelledBy' => null,
    'ariaLabel' => null,
])

@php
    $wrapperClass = $attributes->get('class', '');
    $multiple = (bool) $multiple;
    $searchable = (bool) $searchable;
    $selectAttrs = $attributes->except(['class', 'searchable']);
    $fieldAttrs = $attributes->except(['class', 'id', 'aria-labelledby', 'aria-label', 'name']);
    $fieldName = $attributes->get('name') ?? 'select';
    $triggerId = $multiple ? $attributes->get('id') : null;
    $triggerLabelledBy = $multiple ? $labelledBy : null;
    $triggerAriaLabel = $multiple ? $ariaLabel : null;
    $selectedValues = array_values(array_map('strval', (array) $selected));
    $panelMaxHeight = 288;
    $panelMinHeight = 96;
    $panelGap = 4;
    $panelPad = 8;
    $panelMinWidth = 192;
    $panelMaxWidth = 384;
@endphp
<div class="relative min-w-0 max-w-full {{ $wrapperClass }}"
    {{ $multiple ? $fieldAttrs : '' }}
    x-data="{
        open: false,
        anchor: { top: 0, left: 0, width: 0, maxHeight: {{ $panelMaxHeight }} },
        _repositionHandler: null,
        selectedValue: '',
        multiple: {{ $multiple ? 'true' : 'false' }},
        selectedValues: {{ \Illuminate\Support\Js::from($selectedValues) }},
        fieldName: {{ \Illuminate\Support\Js::from("{$fieldName}[]") }},
        submitOnChange: {{ $submitOnChange ? 'true' : 'false' }},
        initialSnapshot: '',
        searchable: {{ $searchable ? 'true' : 'false' }},
        filterQuery: '',
        get hiddenSelect() { return this.$refs.hidden; },
        get options() {
            if (!this.hiddenSelect) return [];
            return Array.from(this.hiddenSelect.options).map(o => {
                return { value: o.value, label: o.textContent.trim() };
            });
        },
        get dataOptions() {
            return this.options.filter(o => o.value !== '');
        },
        get filteredOptions() {
            var source = this.multiple ? this.dataOptions : this.options;
            if (!this.searchable) return source;
            var q = (this.filterQuery || '').trim().toLowerCase();
            if (q === '') return source;
            return source.filter(function (o) {
                return o.label.toLowerCase().indexOf(q) !== -1;
            });
        },
        get selectedLabel() {
            if (this.dataOptions.length === 0) return this.emptyMessage;
            if (this.multiple) {
                if (this.selectedValues.length === 0) return this.placeholder || 'All';
                if (this.selectedValues.length === 1) {
                    var only = this.selectedValues[0];
                    var one = this.dataOptions.find(o => String(o.value) === String(only));
                    return one ? one.label : only;
                }
                return this.selectedValues.length + ' selected';
            }
            var opt = this.options.find(o => o.value === this.selectedValue);
            return opt ? opt.label : (this.placeholder || '');
        },
        placeholder: {{ json_encode($placeholder) }},
        emptyMessage: {{ json_encode($emptyMessage) }},
        openMenu() {
            window.dispatchEvent(new CustomEvent('horizonhub-select-open'));
            this.filterQuery = '';
            this.open = true;
            var self = this;
            this.$nextTick(function () {
                self.updateAnchor();
                self.bindReposition();
                self.$nextTick(function () {
                    self.updateAnchor();
                });
                if (self.searchable && self.$refs.searchInput) {
                    self.$refs.searchInput.focus();
                }
            });
        },
        closeMenu() {
            if (!this.open) return;
            this.open = false;
            this.unbindReposition();
            if (this.multiple) this.maybeSubmitIfDirty();
        },
        toggleMenu() {
            if (this.open) {
                this.closeMenu();
            } else {
                this.openMenu();
            }
        },
        handleOutsideClick(event) {
            if (!this.open) return;
            var target = event.target;
            if (this.$refs.trigger && this.$refs.trigger.contains(target)) return;
            if (this.$refs.panel && this.$refs.panel.contains(target)) return;
            this.closeMenu();
        },
        updateAnchor() {
            var trigger = this.$refs.trigger;
            if (!trigger) return;

            var panel = this.$refs.panel;
            var rect = trigger.getBoundingClientRect();
            var gap = {{ $panelGap }};
            var pad = {{ $panelPad }};
            var viewportH = window.innerHeight;
            var viewportW = window.innerWidth;
            var spaceBelow = Math.max(0, viewportH - rect.bottom - gap - pad);
            var spaceAbove = Math.max(0, rect.top - gap - pad);
            var cssMax = Math.min({{ $panelMaxHeight }}, viewportH * 0.5);
            var measuredHeight = panel && panel.offsetHeight > 0 ? panel.offsetHeight : cssMax;
            var placeAbove = spaceBelow < measuredHeight && spaceAbove > spaceBelow;
            var available = placeAbove ? spaceAbove : spaceBelow;
            var maxHeight = Math.min(Math.max({{ $panelMinHeight }}, available), cssMax, available);
            var width = Math.min(Math.max(rect.width, {{ $panelMinWidth }}), Math.min({{ $panelMaxWidth }}, viewportW - (pad * 2)));
            var left = Math.min(Math.max(pad, rect.left), viewportW - width - pad);
            var top;
            var bottom;

            if (placeAbove) {
                top = 'auto';
                bottom = Math.max(pad, viewportH - rect.top + gap);
            } else {
                top = rect.bottom + gap;
                bottom = 'auto';
            }

            this.anchor = { top: top, bottom: bottom, left: left, width: width, maxHeight: maxHeight };
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
        destroy() {
            this.unbindReposition();
            this.removePanel();
        },
        removePanel() {
            this.open = false;
            var panel = this.$refs.panel;
            if (panel && panel.parentNode) {
                panel.parentNode.removeChild(panel);
            }
        },
        isSelected(opt) {
            return this.multiple
                ? this.selectedValues.indexOf(String(opt.value)) >= 0
                : opt.value === this.selectedValue;
        },
        choose(opt) {
            if (this.multiple) {
                var value = String(opt.value);
                var at = this.selectedValues.indexOf(value);
                if (at >= 0) {
                    this.selectedValues.splice(at, 1);
                } else {
                    this.selectedValues.push(value);
                }
                this.syncHiddenInputs();
                this.$dispatch('change', { values: this.selectedValues.slice() });
                return;
            }
            this.selectedValue = opt.value;
            this.hiddenSelect.value = opt.value;
            this.hiddenSelect.dispatchEvent(new Event('input', { bubbles: true }));
            this.hiddenSelect.dispatchEvent(new Event('change', { bubbles: true }));
            this.closeMenu();
        },
        syncHiddenInputs() {
            var host = this.$refs.hiddenInputsHost;
            if (!host) return;
            while (host.firstChild) host.removeChild(host.firstChild);
            var self = this;
            this.selectedValues.forEach(function (id) {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = self.fieldName;
                input.value = String(id);
                host.appendChild(input);
            });
        },
        maybeSubmitIfDirty() {
            this.syncHiddenInputs();
            var snapshot = JSON.stringify(this.selectedValues.slice().sort());
            if (!this.submitOnChange || snapshot === this.initialSnapshot) return;
            this.initialSnapshot = snapshot;
            var form = this.$el.closest('form');
            if (!form) return;
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
        },
    }"
    x-init="
        const sync = () => { const el = $refs.hidden; if (el && !multiple) selectedValue = el.value };
        $nextTick(() => {
            sync();
            if (multiple) {
                initialSnapshot = JSON.stringify(selectedValues.slice().sort());
                syncHiddenInputs();
            }
        });
        $watch('open', (open) => { if (open) sync(); });
    "
    @click.window="handleOutsideClick($event)"
    @horizonhub-select-open.window="closeMenu()"
    >
    @if($multiple)
        <select x-ref="hidden"
            multiple
            class="sr-only"
            tabindex="-1"
            aria-hidden="true">
            {{ $slot }}
        </select>
        <div x-ref="hiddenInputsHost" class="hidden" aria-hidden="true"></div>
    @else
        <select x-ref="hidden"
            {{ $selectAttrs->merge(['class' => 'sr-only']) }}>
            {{ $slot }}
        </select>
    @endif

    <button type="button"
        x-ref="trigger"
        @if($triggerId) id="{{ $triggerId }}" @endif
        @if($triggerLabelledBy) aria-labelledby="{{ $triggerLabelledBy }}"
        @elseif($triggerAriaLabel) aria-label="{{ $triggerAriaLabel }}" @endif
        @click.stop="toggleMenu()"
        :aria-expanded="open"
        aria-haspopup="listbox"
        @class([
            'btn-ghost flex h-9 w-full max-w-full items-center justify-between gap-1 overflow-hidden whitespace-nowrap rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm ring-offset-background placeholder:text-muted-foreground',
            'min-w-[8rem]' => $multiple,
        ])>
        <span x-text="selectedLabel" :title="selectedLabel" class="min-w-0 flex-1 truncate text-left"></span>
        <x-icons.chevron-down class="h-4 w-4 shrink-0 opacity-50" />
    </button>

    <template x-teleport="body">
        <div x-ref="panel"
            x-show="open"
            x-cloak
            x-transition:enter="transition ease-out duration-100"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            x-bind:style="{ top: anchor.top === 'auto' ? 'auto' : anchor.top + 'px', bottom: anchor.bottom === 'auto' ? 'auto' : anchor.bottom + 'px', left: anchor.left + 'px', width: anchor.width + 'px', maxHeight: anchor.maxHeight + 'px' }"
            class="fixed z-[70] flex max-w-[min(24rem,calc(100vw_-_2rem))] flex-col overflow-hidden rounded-md border border-border bg-popover text-popover-foreground shadow-md"
            role="listbox">
        <div x-show="searchable && dataOptions.length > 0" class="shrink-0 border-b border-border p-2" @click.stop>
            <input
                type="text"
                x-ref="searchInput"
                x-model="filterQuery"
                placeholder="Search..."
                class="flex h-8 w-full rounded-md border border-input bg-background px-2 text-sm shadow-sm"
                autocomplete="off"
            />
        </div>
        <div class="min-h-0 flex-1 overflow-y-auto p-1">
        <div
            x-show="dataOptions.length === 0"
            class="px-2 py-1.5 text-sm text-muted-foreground select-none"
            x-text="emptyMessage"
            role="presentation"
        ></div>
        <div
            x-show="searchable && dataOptions.length > 0 && filteredOptions.length === 0"
            class="px-2 py-1.5 text-sm text-muted-foreground select-none"
            role="presentation"
        >No matches</div>
        <template x-for="opt in (searchable ? filteredOptions : (multiple ? dataOptions : options))" :key="opt.value">
            <button type="button"
                x-show="dataOptions.length > 0"
                @click="choose(opt)"
                :class="isSelected(opt) ? 'text-accent-foreground' : ''"
                class="btn-ghost relative flex w-full cursor-default select-none items-center justify-start rounded-sm py-1.5 pl-2 pr-8 text-sm outline-none hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:text-accent-foreground data-[disabled]:pointer-events-none data-[disabled]:opacity-50"
                role="option" no-ring>
                <span :title="opt.label" class="min-w-0 flex-1 truncate" x-text="opt.label"></span>
                <span x-show="isSelected(opt)" class="absolute right-2 flex h-3.5 w-3.5 items-center justify-center">
                    <x-icons.check class="size-3.5" />
                </span>
            </button>
        </template>
        </div>
        </div>
    </template>
</div>
