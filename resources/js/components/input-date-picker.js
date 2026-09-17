import Lightpickr from 'lightpickr';
import { parseFailedAtRange } from '../lib/parse';

/**
 * Register Alpine directive for datepicker.
 * @param {*} Alpine
 * @returns {void}
 */
export function registerInputDatePicker(Alpine) {
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
}
