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

/**
 * ECharts options for "jobs per hour (last 24 hours)" line chart (completed vs failed).
 * @param {{ xAxis?: string[], completed?: number[], failed?: number[] }} jobsVolumeLast24h
 * @param {ReturnType<typeof getChartColors>} c
 * @returns {object}
 */
export function buildJobsVolumeLast24hOptions(jobsVolumeLast24h, c) {
    return {
        animation: false,
        color: [c.processed, c.failed],
        tooltip: { trigger: 'axis' },
        legend: {
            data: ['Completed', 'Failed'],
            bottom: 0,
            textStyle: { color: c.axis, fontSize: 10 },
        },
        grid: { left: 48, right: 24, top: 16, bottom: 36 },
        xAxis: {
            type: 'category',
            data: jobsVolumeLast24h.xAxis || [],
            axisLine: { lineStyle: { color: c.axis } },
            axisLabel: { color: c.axis, fontSize: 10 },
        },
        yAxis: {
            type: 'value',
            name: 'Jobs',
            minInterval: 1,
            axisLine: { show: false },
            splitLine: { lineStyle: { color: c.axis, opacity: 0.3 } },
            axisLabel: { color: c.axis, fontSize: 10 },
        },
        series: [
            {
                type: 'line',
                name: 'Completed',
                data: jobsVolumeLast24h.completed || [],
                smooth: false,
                showSymbol: false,
                lineStyle: { width: 2 },
            },
            {
                type: 'line',
                name: 'Failed',
                data: jobsVolumeLast24h.failed || [],
                smooth: false,
                showSymbol: false,
                lineStyle: { width: 2 },
            },
        ],
    };
}

/**
 * Get the HSL value of a CSS variable.
 * @param {string} varName
 * @returns {string}
 */
function getCssHsl(varName) {
    var val = getComputedStyle(document.documentElement).getPropertyValue(varName).trim();
    if (!val) return null;
    return 'hsl(' + val.replace(/\s+/g, ', ') + ')';
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
    if (!previousSelected || !options || !options.legend || !Array.isArray(options.legend.data) || options.legend.data.length === 0) return;
    var selected = {};
    for (var i = 0; i < options.legend.data.length; i++) {
        var name = options.legend.data[i];
        if (Object.prototype.hasOwnProperty.call(previousSelected, name)) {
            selected[name] = !!previousSelected[name];
        } else {
            selected[name] = true;
        }
    }
    options.legend = Object.assign({}, options.legend, { selected: selected });
}
