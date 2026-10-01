import Alpine from 'alpinejs';

import Lightpickr from 'lightpickr';
import { parseFailedAtRange } from './lib/parse';

window.Alpine = Alpine;

/**
 * Destroy Alpine tree on turbo:before-cache.
 */
document.addEventListener('turbo:before-cache', function () {
    Alpine.destroyTree(document.body);
});

/**
 * Initialize Alpine tree on turbo:load.
 */
document.addEventListener('turbo:load', function () {
    queueMicrotask(function () {
        Alpine.initTree(document.body);
    });
});

/**
 * Register Alpine directive for datepicker.
 */
Alpine.directive('datepicker', (el, { modifiers }, { cleanup }) => {
    var dp = null;
    var isRange = modifiers.includes('range');
    var withTime = modifiers.includes('time');
    var format = withTime ? 'YYYY-MM-DDTHH:mm' : 'YYYY-MM-DD';
    var raw = (el.value && el.value.trim()) || '';

    /** @returns {void} */
    var notifyModel = function () {
        queueMicrotask(function () {
            el.dispatchEvent(new Event('input', { bubbles: true }));
            el.dispatchEvent(new Event('change', { bubbles: true }));
        });
    };

    queueMicrotask(function () {
        var range =
            raw && isRange
                ? parseFailedAtRange(raw)
                : null;

        var options = {
            range: isRange,
            enableTime: withTime,
            format: format,
            minutesStep: 1,
            autoClose: true,
            isMobile: false,
            position: 'bottom left',
            buttons: ['clear'],
            onSelect: notifyModel,
        };
        if (withTime) {
            options.onTimeChange = notifyModel;
        }

        if (raw && !isRange) {
            options.selectedDates = [raw];
        } else if (range && range.dateFrom && range.dateTo) {
            options.selectedDates = [[range.dateFrom, range.dateTo]];
        }

        dp = new Lightpickr(el, options);

        if (range && range.dateFrom && !range.dateTo) {
            dp.selectDate(range.dateFrom);
        }

        if (raw) {
            notifyModel();
        }
    });

    cleanup(function () {
        if (dp && typeof dp.destroy === 'function' && !dp.isDestroyed) {
            dp.destroy();
        }
        dp = null;
    });
});

/**
 * Start Alpine.
 *
 * The entry point calls this once every global its components read is in place,
 * since Alpine evaluates them as it boots.
 * @returns {void}
 */
export function startAlpine() {
    Alpine.start();
}