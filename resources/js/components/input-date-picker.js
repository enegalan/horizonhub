import Lightpickr from 'lightpickr';
import 'lightpickr/lightpickr.css';

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
            var rangeBounds =
                raw && isRange
                    ? raw
                        .split(/\s*(?:\s+to\s+|–|—)\s*/i)
                        .map(function (s) {
                            return s.trim();
                        })
                        .filter(Boolean)
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
            } else if (rangeBounds && rangeBounds.length >= 2) {
                options.selectedDates = [[rangeBounds[0], rangeBounds[1]]];
            }

            dp = new Lightpickr(el, options);

            if (rangeBounds && rangeBounds.length === 1) {
                dp.selectDate(rangeBounds[0]);
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
