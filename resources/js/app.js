import './bootstrap';
import './resizable-table';
import './alert-log-modal';

import { createRoot } from 'react-dom/client';
import React from 'react';
import { Toaster, toast } from 'sonner';
import 'sonner/dist/styles.css';
import { onDocumentReady, schedule } from './utils/init';
import { withLivewireInitialized, onLivewireNavigated, onLivewireRequestSuccess } from './utils/livewire';
import { parseJsonFromElement } from './utils/parse';
import { initMetricsCharts, initAlertDetailCharts } from './metrics-charts';
import { registerToastEventListeners } from './toast-events';
import { applyTheme } from './theme';
import { formatDateTimeElements } from './datetime-format';

function mountToaster() {
    function run() {
        var el = document.getElementById('toaster');
        if (!el) {
            el = document.createElement('div');
            el.id = 'toaster';
            el.setAttribute('aria-live', 'polite');
            if (document.body) document.body.appendChild(el);
            else {
                document.addEventListener('DOMContentLoaded', run);
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
    run();
}

function hydratePage() {
    schedule(() => {
        hydrateMetricsChartsFromDom();
        hydrateAlertDetailChartsFromDom();
        formatDateTimeElements();
    });
}

function syncTheme() {
    applyTheme();
    window.dispatchEvent(new CustomEvent('apply-theme'));
}

onDocumentReady(() => {
    mountToaster();
    registerToastEventListeners();
    syncTheme();
    hydratePage();
});

onLivewireNavigated(() => {
    mountToaster();
    syncTheme();
    hydratePage();
});

document.addEventListener('livewire:navigating', e => {
    e.detail.onSwap(syncTheme);
});

withLivewireInitialized(() => {
    onLivewireRequestSuccess(hydratePage);
    window.Livewire.hook('morph.updated', () => {
        if (document.getElementById('alert-detail-chart-data')) {
            var alertData = parseJsonFromElement('alert-detail-chart-data');
            initAlertDetailCharts(alertData);
        }
        formatDateTimeElements();
    });
});

window.addEventListener('apply-theme', () => {
    applyTheme();
});

function hydrateMetricsChartsFromDom() {
    if (typeof window.echarts === 'undefined') return;

    var data = parseJsonFromElement('metrics-chart-data');
    if (!data) return;

    initMetricsCharts(data);
}

function hydrateAlertDetailChartsFromDom() {
    if (typeof window.echarts === 'undefined') return;

    var data = parseJsonFromElement('alert-detail-chart-data');
    if (!data) return;

    initAlertDetailCharts(data);
}
