import { Notyf } from 'notyf';
import 'notyf/notyf.min.css';

var TOAST_DURATION_MS = 4500;

/**
 * @param {unknown} message
 * @returns {string}
 */
function resolveToastMessage(message) {
    return typeof message === 'string' && message ? message : 'Done.';
}

/**
 * Mount Notyf and expose toast on window.
 * @returns {void}
 */
export function mountToaster() {
    if (typeof document === 'undefined') {
        return;
    }
    if (window._hubNotyfMounted) {
        return;
    }
    window._hubNotyfMounted = true;

    var notyf = new Notyf({
        duration: TOAST_DURATION_MS,
        ripple: false,
        position: {
            x: 'right',
            y: 'bottom',
        },
        dismissible: false,
        types: [
            {
                type: 'success',
                className: 'hub-notyf hub-notyf--success',
                background: '',
                backgroundColor: '',
                icon: false,
            },
            {
                type: 'error',
                className: 'hub-notyf hub-notyf--error',
                background: '',
                backgroundColor: '',
                icon: false,
            },
            {
                type: 'info',
                className: 'hub-notyf hub-notyf--info',
                background: '',
                backgroundColor: '',
                icon: false,
            },
            {
                type: 'warning',
                className: 'hub-notyf hub-notyf--warning',
                background: '',
                backgroundColor: '',
                icon: false,
            },
        ],
    });

    window.toast = {
        success: function (m) {
            notyf.open({ type: 'success', message: resolveToastMessage(m) });
        },
        error: function (m) {
            notyf.open({ type: 'error', message: resolveToastMessage(m) });
        },
        info: function (m) {
            notyf.open({ type: 'info', message: resolveToastMessage(m) });
        },
        warning: function (m) {
            notyf.open({ type: 'warning', message: resolveToastMessage(m) });
        },
    };
}
