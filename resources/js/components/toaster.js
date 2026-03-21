var TOAST_DISMISS_MS = 4500;
var TOAST_EXIT_MS = 220;

/**
 * @param {string} variant
 * @param {string} message
 * @returns {void}
 */
function showHubToast(variant, message) {
    var container = document.getElementById('toaster');
    if (!container || !container.classList.contains('hub-toaster')) {
        return;
    }
    var text = typeof message === 'string' && message ? message : 'Done.';
    var item = document.createElement('div');
    item.className = 'hub-toast hub-toast--' + variant;
    item.setAttribute('role', 'status');
    item.textContent = text;
    container.appendChild(item);
    requestAnimationFrame(function () {
        item.classList.add('hub-toast--visible');
    });
    window.setTimeout(function () {
        item.classList.remove('hub-toast--visible');
        window.setTimeout(function () {
            if (item.parentNode) {
                item.parentNode.removeChild(item);
            }
        }, TOAST_EXIT_MS);
    }, TOAST_DISMISS_MS);
}

/**
 * Mount the toaster and expose toast on window.
 * @returns {void}
 */
export function mountToaster() {
    var el = document.getElementById('toaster');
    if (!el) {
        el = document.createElement('div');
        el.id = 'toaster';
        el.setAttribute('aria-live', 'polite');
        if (document.body) {
            document.body.appendChild(el);
        } else {
            document.addEventListener('DOMContentLoaded', mountToaster);
            return;
        }
    }
    if (el._toasterMounted) {
        return;
    }

    el._toasterMounted = true;
    el.classList.add('hub-toaster');

    window.toast = {
        success: function (m) {
            showHubToast('success', m);
        },
        error: function (m) {
            showHubToast('error', m);
        },
        info: function (m) {
            showHubToast('info', m);
        },
        warning: function (m) {
            showHubToast('warning', m);
        },
    };
}
