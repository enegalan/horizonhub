@props(['options' => [], 'placeholder' => '', 'selected' => null, 'emptyMessage' => 'No results'])

@php
    $wrapperClass = $attributes->get('class', '');
    $selectAttrs = $attributes->except('class');
@endphp
<div class="relative {{ $wrapperClass }}"
    x-data="{
        open: false,
        anchor: { top: 0, left: 0, width: 0 },
        _repositionHandler: null,
        selectedValue: '',
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
        get selectedLabel() {
            if (this.dataOptions.length === 0) return this.emptyMessage;
            var opt = this.options.find(o => o.value === this.selectedValue);
            return opt ? opt.label : (this.placeholder || '');
        },
        placeholder: {{ json_encode($placeholder) }},
        emptyMessage: {{ json_encode($emptyMessage) }},
        openMenu() {
            window.dispatchEvent(new CustomEvent('horizonhub-select-open'));
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
        choose(opt) {
            this.selectedValue = opt.value;
            this.hiddenSelect.value = opt.value;
            this.hiddenSelect.dispatchEvent(new Event('input', { bubbles: true }));
            this.hiddenSelect.dispatchEvent(new Event('change', { bubbles: true }));
            this.closeMenu();
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
    @click.window="handleOutsideClick($event)"
    @horizonhub-select-open.window="closeMenu()"
    >
    <select x-ref="hidden"
        {{ $selectAttrs->merge(['class' => 'sr-only']) }}>
        @if($placeholder !== '')
            <option value="" @selected(empty($selected))>{{ $placeholder }}</option>
        @endif
        @foreach($options as $value => $label)
            <option value="{{ $value }}" @selected($selected !== null && (string) $value === (string) $selected)>{{ $label }}</option>
        @endforeach
        {{ $slot }}
    </select>

    <button type="button"
        x-ref="trigger"
        @click.stop="toggleMenu()"
        :aria-expanded="open"
        aria-haspopup="listbox"
        class="btn-ghost flex h-9 w-full items-center justify-between whitespace-nowrap rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm ring-offset-background placeholder:text-muted-foreground [&>span]:line-clamp-1">
        <span x-text="selectedLabel" class="block truncate text-left"></span>
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
            x-bind:style="{ top: anchor.top + 'px', left: anchor.left + 'px', minWidth: Math.max(anchor.width, 128) + 'px' }"
            class="fixed z-[70] max-h-60 overflow-auto rounded-md border border-border bg-popover text-popover-foreground shadow-md p-1"
            role="listbox">
        <div
            x-show="dataOptions.length === 0"
            class="px-2 py-1.5 text-sm text-muted-foreground select-none"
            x-text="emptyMessage"
            role="presentation"
        ></div>
        <template x-for="opt in options" :key="opt.value">
            <button type="button"
                x-show="dataOptions.length > 0"
                @click="choose(opt)"
                :class="opt.value === selectedValue ? 'text-accent-foreground' : ''"
                class="btn-ghost relative flex w-full cursor-default select-none items-center justify-start rounded-sm py-1.5 pl-2 pr-8 text-sm outline-none hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:text-accent-foreground data-[disabled]:pointer-events-none data-[disabled]:opacity-50"
                role="option" no-ring>
                <span class="block truncate" x-text="opt.label"></span>
                <span x-show="opt.value === selectedValue" class="absolute right-2 flex h-3.5 w-3.5 items-center justify-center">
                    <x-icons.check class="size-3.5" />
                </span>
            </button>
        </template>
        </div>
    </template>
</div>
