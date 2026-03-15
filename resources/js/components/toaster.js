import { createRoot } from 'react-dom/client';
import React from 'react';
import { Toaster, toast } from 'sonner';
import 'sonner/dist/styles.css';

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
        if (document.body) document.body.appendChild(el);
        else {
            document.addEventListener('DOMContentLoaded', mountToaster);
            return;
        }
    }
    if (el._toasterMounted) return;

    el._toasterMounted = true;
    try {
        var root = createRoot(el);
        root.render(React.createElement(Toaster, {
            theme: 'light',
            richColors: true,
            position: 'bottom-right'
        }));
        window.toast = toast;
    } catch (err) {
        console.error('Toaster mount failed', err);
    }
}
