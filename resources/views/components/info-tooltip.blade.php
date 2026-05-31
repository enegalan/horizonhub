@props([
    'text' => '',
])

<span
    {{ $attributes->merge(['class' => 'relative inline-flex shrink-0']) }}
    x-data="{
        open: false,
        anchor: { top: 0, left: 0, width: 208 },
        _repositionHandler: null,
        show() {
            this.open = true;
            var self = this;
            this.$nextTick(function () {
                self.updateAnchor();
                self.bindReposition();
            });
        },
        hide() {
            if (!this.open) {
                return;
            }
            this.open = false;
            this.unbindReposition();
        },
        updateAnchor() {
            var trigger = this.$refs.trigger;
            if (!trigger) {
                return;
            }
            var rect = trigger.getBoundingClientRect();
            var width = 208;
            var gap = 6;
            var left = rect.left + (rect.width / 2) - (width / 2);
            var edge = 8;
            left = Math.max(edge, Math.min(left, window.innerWidth - width - edge));
            this.anchor = { top: rect.bottom + gap, left: left, width: width };
        },
        bindReposition() {
            if (this._repositionHandler) {
                return;
            }
            var self = this;
            this._repositionHandler = function () {
                self.updateAnchor();
            };
            window.addEventListener('scroll', this._repositionHandler, true);
            window.addEventListener('resize', this._repositionHandler);
        },
        unbindReposition() {
            if (!this._repositionHandler) {
                return;
            }
            window.removeEventListener('scroll', this._repositionHandler, true);
            window.removeEventListener('resize', this._repositionHandler);
            this._repositionHandler = null;
        },
    }"
    @mouseenter="show()"
    @mouseleave="hide()"
    @focusin="show()"
    @focusout="hide()"
    @click.stop
>
    <button
        type="button"
        x-ref="trigger"
        class="inline-flex size-6 items-center justify-center rounded-full text-muted-foreground transition-colors hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
        aria-label="{{ $text }}"
    >
        <x-icons.information-circle class="size-4" />
    </button>

    <template x-teleport="body">
        <span
            x-show="open"
            x-cloak
            role="tooltip"
            x-bind:style="{ top: anchor.top + 'px', left: anchor.left + 'px', width: anchor.width + 'px' }"
            class="pointer-events-none fixed z-[70] rounded-lg border border-border bg-popover px-3 py-2 text-xs leading-snug text-popover-foreground shadow-md"
        >{{ $text }}</span>
    </template>
</span>
