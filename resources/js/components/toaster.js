import { Notyf } from 'notyf';
import 'notyf/notyf.min.css';

var TOAST_DURATION_MS = 4500;

var DEFAULT_TOAST_MESSAGE = 'Done.';

/**
 * Remove Notyf DOM nodes.
 * @returns {void}
 */
function removeNotyfDom() {
    document.querySelectorAll('.notyf-announcer').forEach(function (el) {
        el.remove();
    });
    document.querySelectorAll('.notyf').forEach(function (el) {
        el.remove();
    });
}

/**
 * Mount Notyf and expose toast on window.
 * @returns {void}
 */
export function mountToaster() {
    removeNotyfDom();

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
        success: function (message) {
            notyf.open({ type: 'success', message: typeof message === 'string' && message ? message : DEFAULT_TOAST_MESSAGE });
        },
        error: function (message) {
            notyf.open({ type: 'error', message: typeof message === 'string' && message ? message : DEFAULT_TOAST_MESSAGE });
        },
        info: function (message) {
            notyf.open({ type: 'info', message: typeof message === 'string' && message ? message : DEFAULT_TOAST_MESSAGE });
        },
        warning: function (message) {
            notyf.open({ type: 'warning', message: typeof message === 'string' && message ? message : DEFAULT_TOAST_MESSAGE });
        },
    };
}
