import { getCssHsl } from '../lib/dom';

/**
 * Get the chart colors.
 * @returns {object}
 */
export function getChartColors() {
    return {
        axis: getCssHsl('--muted-foreground'),
        processed: getCssHsl('--primary'),
        failed: getCssHsl('--destructive'),
        line: getCssHsl('--muted-foreground'),
    };
}

/**
 * Read legend series visibility from an existing chart (ECharts toggles this when the user clicks the legend).
 * @param {object} chart
 * @returns {object|null} Map of series name -> selected boolean
 */
function getLegendSelectedFromChart(chart) {
    try {
        var opt = chart.getOption();
        if (!opt || opt.legend === undefined) return null;
        var legends = Array.isArray(opt.legend) ? opt.legend : [opt.legend];
        for (var i = 0; i < legends.length; i++) {
            var leg = legends[i];
            if (leg && leg.selected && typeof leg.selected === 'object' && Object.keys(leg.selected).length) {
                return Object.assign({}, leg.selected);
            }
        }
        return null;
    } catch (e) {
        return null;
    }
}

/**
 * Merge prior legend visibility into new options so hot reload / data refresh keeps user filters.
 * @param {object} options
 * @param {object} previousSelected
 * @returns {void}
 */
function mergeLegendSelectedIntoOptions(options, previousSelected) {
    if (!previousSelected || !options || !options.legend) return;
    var leg = options.legend;
    var data = leg.data;
    if (!Array.isArray(data) || data.length === 0) return;
    var selected = {};
    for (var i = 0; i < data.length; i++) {
        var name = data[i];
        if (Object.prototype.hasOwnProperty.call(previousSelected, name)) {
            selected[name] = !!previousSelected[name];
        } else {
            selected[name] = true;
        }
    }
    options.legend = Object.assign({}, leg, { selected: selected });
}

/**
 * Apply the chart options.
 * @param {Element} el
 * @param {object} options
 * @returns {void}
 */
export function applyChartOptions(el, options) {
    var existing = window.echarts.getInstanceByDom(el);
    var previousLegendSelected = existing ? getLegendSelectedFromChart(existing) : null;
    if (previousLegendSelected) {
        mergeLegendSelectedIntoOptions(options, previousLegendSelected);
    }
    if (existing) {
        existing.setOption(options, { notMerge: true });
        existing.resize();
    } else {
        var chart = window.echarts.init(el);
        chart.setOption(options);
        chart.resize();
    }
}
