@extends('layouts.app')

@section('content')
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        <div class="card p-4">
            <h3 class="label-muted">Jobs past minute</h3>
            <div class="mt-1 flex items-center gap-2 min-h-[2.5rem]">
                <span id="metrics-loader-jobs-minute"><x-loader class="size-5 shrink-0 text-muted-foreground" /></span>
                <span id="metrics-value-jobs-minute" class="text-2xl font-semibold text-foreground">—</span>
            </div>
        </div>
        <div class="card p-4">
            <h3 class="label-muted">Jobs past hour</h3>
            <div class="mt-1 flex items-center gap-2 min-h-[2.5rem]">
                <span id="metrics-loader-jobs-hour"><x-loader class="size-5 shrink-0 text-muted-foreground" /></span>
                <span id="metrics-value-jobs-hour" class="text-2xl font-semibold text-foreground">—</span>
            </div>
        </div>
        <div class="card p-4">
            <h3 class="label-muted">Failed jobs (past 7 days)</h3>
            <div class="mt-1 flex items-center gap-2 min-h-[2.5rem]">
                <span id="metrics-loader-failed-seven"><x-loader class="size-5 shrink-0 text-muted-foreground" /></span>
                <span id="metrics-value-failed-seven" class="text-2xl font-semibold text-foreground">—</span>
            </div>
        </div>
        <div class="card p-4">
            <h3 class="label-muted">Processed (24h)</h3>
            <div class="mt-1 flex items-center gap-2 min-h-[2.5rem]">
                <span id="metrics-loader-processed-24"><x-loader class="size-5 shrink-0 text-muted-foreground" /></span>
                <span id="metrics-value-processed-24" class="text-2xl font-semibold text-foreground">—</span>
            </div>
        </div>
    </div>

    <div class="grid gap-4">
        <div class="card p-4">
            <h3 class="text-section-title text-foreground mb-1">Failure rate (last 24h)</h3>
            <div class="flex items-center gap-2 min-h-[2rem]">
                <span id="metrics-loader-failure-rate"><x-loader class="size-5 shrink-0 text-muted-foreground" /></span>
                <p id="metrics-value-failure-rate" class="text-xl font-semibold text-foreground">—</p>
            </div>
        </div>

        <div class="card p-4">
            <h3 class="text-section-title text-foreground mb-2">Processed vs failed (last 24h, by hour)</h3>
            <div class="relative h-56">
                <div id="metrics-loader-processed-failed" class="absolute inset-0 flex items-center justify-center bg-muted/30 rounded">
                    <x-loader class="size-8 text-muted-foreground" />
                </div>
                <div id="processed-failed-chart" class="h-56"></div>
            </div>
        </div>

        <div class="card p-4">
            <h3 class="text-section-title text-foreground mb-2">Failure rate over time (last 24h, %)</h3>
            <div class="relative h-56">
                <div id="metrics-loader-failure-rate-chart" class="absolute inset-0 flex items-center justify-center bg-muted/30 rounded">
                    <x-loader class="size-8 text-muted-foreground" />
                </div>
                <div id="failure-rate-chart" class="h-56"></div>
            </div>
        </div>

        <div class="card p-4">
            <h3 class="text-section-title text-foreground mb-2">Average job runtime (last 24h, seconds)</h3>
            <div class="relative h-56">
                <div id="metrics-loader-runtime-chart" class="absolute inset-0 flex items-center justify-center bg-muted/30 rounded">
                    <x-loader class="size-8 text-muted-foreground" />
                </div>
                <div id="runtime-chart" class="h-56"></div>
            </div>
        </div>

        <div class="card p-4">
            <h3 class="text-section-title text-foreground mb-2">Jobs by queue (last 7 days, top 12)</h3>
            <div class="relative h-80">
                <div id="metrics-loader-queue-chart" class="absolute inset-0 flex items-center justify-center bg-muted/30 rounded">
                    <x-loader class="size-8 text-muted-foreground" />
                </div>
                <div id="queue-distribution-chart" class="h-80"></div>
            </div>
        </div>

        <div class="card p-4">
            <h3 class="text-section-title text-foreground mb-2">Jobs by service (last 7 days, top 10)</h3>
            <div class="relative h-80">
                <div id="metrics-loader-service-chart" class="absolute inset-0 flex items-center justify-center bg-muted/30 rounded">
                    <x-loader class="size-8 text-muted-foreground" />
                </div>
                <div id="service-distribution-chart" class="h-80"></div>
            </div>
        </div>

        <div class="card p-4">
            <h3 class="text-section-title text-foreground mb-2">Failed by service × queue (past 7 days, top 15)</h3>
            <div class="overflow-x-auto">
                <div id="metrics-loader-failures-table" class="flex items-center justify-center py-12">
                    <x-loader class="size-8 text-muted-foreground" />
                </div>
                <table class="min-w-full text-sm" id="metrics-failures-table" style="display: none;">
                    <thead>
                        <tr class="border-b border-border">
                            <th class="table-header px-4 py-2.5 text-left">Service</th>
                            <th class="table-header px-4 py-2.5 text-left">Queue</th>
                            <th class="table-header px-4 py-2.5 text-right">Count</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border" id="metrics-failures-tbody">
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        (function () {
            var urls = {
                summary: {{ Js::from(route('horizon.metrics.data.summary')) }},
                processedVsFailed: {{ Js::from(route('horizon.metrics.data.processed-vs-failed')) }},
                avgRuntime: {{ Js::from(route('horizon.metrics.data.avg-runtime')) }},
                byQueue: {{ Js::from(route('horizon.metrics.data.by-queue')) }},
                byService: {{ Js::from(route('horizon.metrics.data.by-service')) }},
                failuresTable: {{ Js::from(route('horizon.metrics.data.failures-table')) }}
            };

            function hideLoader(id) {
                var el = document.getElementById(id);
                if (el) el.style.display = 'none';
            }

            function formatNum(n) {
                return typeof n === 'number' ? n.toLocaleString() : '—';
            }

            function esc(s) {
                var d = document.createElement('div');
                d.textContent = s == null ? '' : s;
                return d.innerHTML;
            }

            window.__metricsChartQueue = window.__metricsChartQueue || [];
            function renderChart(data) {
                if (!data) return;
                window.__metricsChartQueue.push(data);
                if (typeof window.processMetricsChartQueue === 'function') {
                    window.processMetricsChartQueue();
                }
            }

            function fetchSection(url, onSuccess, loaderIds) {
                fetch(url)
                    .then(function (res) {
                        if (!res.ok) throw new Error('Request failed');
                        return res.json();
                    })
                    .then(function (data) {
                        if (data.error) throw new Error(data.error);
                        if (loaderIds && loaderIds.length) loaderIds.forEach(hideLoader);
                        onSuccess(data);
                    })
                    .catch(function () {
                        if (loaderIds && loaderIds.length) loaderIds.forEach(hideLoader);
                    });
            }

            fetchSection(urls.summary, function (d) {
                var v = document.getElementById('metrics-value-jobs-minute');
                if (v) v.textContent = formatNum(d.jobsPastMinute);
                v = document.getElementById('metrics-value-jobs-hour');
                if (v) v.textContent = formatNum(d.jobsPastHour);
                v = document.getElementById('metrics-value-failed-seven');
                if (v) v.textContent = formatNum(d.failedPastSevenDays);
                v = document.getElementById('metrics-value-processed-24');
                if (v) v.textContent = formatNum(d.processedPast24Hours);
                v = document.getElementById('metrics-value-failure-rate');
                if (v && d.failureRate24h) {
                    var r = d.failureRate24h;
                    v.innerHTML = r.rate + '% <span class="text-xs font-normal text-muted-foreground">(' + r.failed + ' failed / ' + r.processed + ' processed)</span>';
                }
            }, ['metrics-loader-jobs-minute', 'metrics-loader-jobs-hour', 'metrics-loader-failed-seven', 'metrics-loader-processed-24', 'metrics-loader-failure-rate']);

            fetchSection(urls.processedVsFailed, function (d) {
                hideLoader('metrics-loader-processed-failed');
                hideLoader('metrics-loader-failure-rate-chart');
                renderChart(d);
            }, null);

            fetchSection(urls.avgRuntime, function (d) {
                hideLoader('metrics-loader-runtime-chart');
                renderChart({ avgRuntimeOverTime: d });
            }, null);

            fetchSection(urls.byQueue, function (d) {
                hideLoader('metrics-loader-queue-chart');
                renderChart({ byQueue: d });
            }, null);

            fetchSection(urls.byService, function (d) {
                hideLoader('metrics-loader-service-chart');
                renderChart({ byService: d });
            }, null);

            fetchSection(urls.failuresTable, function (d) {
                var loader = document.getElementById('metrics-loader-failures-table');
                var table = document.getElementById('metrics-failures-table');
                var tbody = document.getElementById('metrics-failures-tbody');
                if (!tbody) return;
                if (loader) loader.style.display = 'none';
                if (table) table.style.display = 'table';
                var rows = d.failuresTable || [];
                tbody.innerHTML = '';
                if (rows.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="3" class="px-4 py-6 text-center text-muted-foreground text-sm">No failures in the past 7 days</td></tr>';
                    return;
                }
                rows.forEach(function (r) {
                    var tr = document.createElement('tr');
                    tr.className = 'hover:bg-muted/30';
                    tr.innerHTML = '<td class="px-4 py-2.5 font-medium text-foreground">' + esc(r.service) + '</td>' +
                        '<td class="px-4 py-2.5 font-mono text-xs text-muted-foreground">' + esc(r.queue) + '</td>' +
                        '<td class="px-4 py-2.5 text-right text-muted-foreground">' + formatNum(r.cnt) + '</td>';
                    tbody.appendChild(tr);
                });
            }, ['metrics-loader-failures-table']);
        })();
    </script>
@endsection
