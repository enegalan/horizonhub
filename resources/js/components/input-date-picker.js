import AirDatepicker from 'air-datepicker';
import localeEn from 'air-datepicker/locale/en.js';
import 'air-datepicker/air-datepicker.css';

var RANGE_SEPARATOR = ' to ';

/**
 * Format date as YYYY-MM-DDTHH:MM.
 * @param {Date} d
 * @returns {string}
 */
function formatYmdTHm(d) {
    var pad = function (n) {
        return String(n).padStart(2, '0');
    };

    return (
        d.getFullYear() +
        '-' +
        pad(d.getMonth() + 1) +
        '-' +
        pad(d.getDate()) +
        'T' +
        pad(d.getHours()) +
        ':' +
        pad(d.getMinutes())
    );
}

/**
 * Format date as YYYY-MM-DD.
 * @param {Date} d
 * @returns {string}
 */
function formatYmd(d) {
    var pad = function (n) {
        return String(n).padStart(2, '0');
    };

    return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
}

/**
 * Alpine directive: Air Datepicker on a text input, x-model compatible.
 * Modifiers: .range (two bounds), .time (include HH:mm). Combine: x-datepicker.range.time
 * @param {*} Alpine
 * @returns {void}
 */
export function registerInputDatePicker(Alpine) {
    Alpine.directive('datepicker', (el, { modifiers }, { cleanup }) => {
        var dp = null;
        var isRange = modifiers.includes('range');
        var withTime = modifiers.includes('time');
        var fmt = withTime ? formatYmdTHm : formatYmd;

        /** @type {() => void} */
        var syncInputValueFromPicker = function () {};

        var changeHandler = function () {
            syncInputValueFromPicker();
        };

        var inputHandler = function () {
            if (!dp || dp.isDestroyed) {
                return;
            }
            if (el.value.trim() !== '') {
                return;
            }
            var dates = dp.selectedDates || [];
            if (dates.length === 0) {
                return;
            }
            dp.clear({ silent: true });
        };

        queueMicrotask(function () {
            syncInputValueFromPicker = function () {
                if (!dp || dp.isDestroyed) {
                    return;
                }
                var dates = dp.selectedDates || [];
                var nextVal = '';
                if (!isRange) {
                    if (dates[0] instanceof Date) {
                        nextVal = fmt(dates[0]);
                    }
                } else if (dates.length === 0) {
                    nextVal = '';
                } else if (dates.length === 1 && dates[0] instanceof Date) {
                    nextVal = fmt(dates[0]);
                } else if (dates.length >= 2 && dates[0] instanceof Date && dates[1] instanceof Date) {
                    var t0 = dates[0].getTime();
                    var t1 = dates[1].getTime();
                    var a = t0 <= t1 ? fmt(dates[0]) : fmt(dates[1]);
                    var b = t0 <= t1 ? fmt(dates[1]) : fmt(dates[0]);
                    nextVal = a + RANGE_SEPARATOR + b;
                }
                if (el.value === nextVal) {
                    return;
                }
                el.value = nextVal;
                el.dispatchEvent(new Event('input', { bubbles: true }));
                el.dispatchEvent(new Event('change', { bubbles: true }));
            };

            dp = new AirDatepicker(el, {
                locale: localeEn,
                range: isRange,
                timepicker: withTime,
                timeFormat: 'HH:mm',
                dateTimeSeparator: 'T',
                minutesStep: 1,
                dateFormat: withTime ? formatYmdTHm : 'yyyy-MM-dd',
                multipleDatesSeparator: RANGE_SEPARATOR,
                autoClose: true,
                isMobile: false,
                position: 'bottom left',
                toggleSelected: false,
                buttons: ['clear'],
                onSelect: function () {
                    syncInputValueFromPicker();
                },
            });

            var raw = el.value && el.value.trim();
            if (raw) {
                if (isRange) {
                    var parts = raw.split(/\s+to\s+/i).map(function (s) {
                        return s.trim();
                    }).filter(Boolean);
                    if (parts.length >= 2) {
                        dp.selectDate([parts[0], parts[1]], { silent: true });
                    } else {
                        dp.selectDate(parts[0], { silent: true });
                    }
                } else {
                    dp.selectDate(raw, { silent: true });
                }
                syncInputValueFromPicker();
            }

            el.addEventListener('input', inputHandler);
            if (withTime) {
                el.addEventListener('change', changeHandler);
            }
        });

        cleanup(function () {
            el.removeEventListener('input', inputHandler);
            if (withTime) {
                el.removeEventListener('change', changeHandler);
            }
            if (dp && typeof dp.destroy === 'function' && !dp.isDestroyed) {
                dp.destroy();
            }
            dp = null;
        });
    });
}
