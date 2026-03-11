@extends('layouts.app')

@section('content')
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <label for="metrics-service-filter" class="label-muted text-sm">Filter by service</label>
        <x-select id="metrics-service-filter" class="w-48" placeholder="All services">
            @foreach($services as $service)
                <option value="{{ $service->id }}">{{ $service->name }}</option>
            @endforeach
        </x-select>
    </div>

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

        <div class="card">
            <div class="flex items-center justify-between border-b border-border px-4 py-3">
                <h3 class="text-section-title text-foreground">Current workload</h3>
                <p id="metrics-workload-summary" class="text-xs text-muted-foreground"></p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full" data-resizable-table="horizon-metrics-queues" data-column-ids="service,queue,jobs,processes,wait">
                    <thead>
                        <tr class="border-b border-border bg-muted/50">
                            <th class="table-header px-4 py-2.5 min-w-[140px]" data-column-id="service">Service</th>
                            <th class="table-header px-4 py-2.5 min-w-[120px]" data-column-id="queue">Queue</th>
                            <th class="table-header px-4 py-2.5" data-column-id="jobs">Jobs</th>
                            <th class="table-header px-4 py-2.5" data-column-id="processes">Processes</th>
                            <th class="table-header px-4 py-2.5" data-column-id="wait">Wait</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border" data-table-body="horizon-metrics-queues" id="metrics-workload-body">
                        <tr id="metrics-workload-empty">
                            <td colspan="5" data-column-id="service">
                                <div class="empty-state">
                                    <x-heroicon-o-queue-list class="empty-state-icon" />
                                    <p class="empty-state-title">No queues yet</p>
                                    <p class="empty-state-description">Queues will appear here once jobs are dispatched to your services.</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="flex items-center justify-between border-b border-border px-4 py-3">
                <h3 class="text-section-title text-foreground">Supervisors</h3>
                <p id="metrics-supervisors-summary" class="text-xs text-muted-foreground"></p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full" data-resizable-table="horizon-metrics-supervisors" data-column-ids="service,supervisor,status,last_seen">
                    <thead>
                        <tr class="border-b border-border bg-muted/50">
                            <th class="table-header px-4 py-2.5 min-w-[140px]" data-column-id="service">Service</th>
                            <th class="table-header px-4 py-2.5 min-w-[160px]" data-column-id="supervisor">Supervisor</th>
                            <th class="table-header px-4 py-2.5 min-w-[80px]" data-column-id="status">Status</th>
                            <th class="table-header px-4 py-2.5 min-w-[160px]" data-column-id="last_seen">Last seen</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border" data-table-body="horizon-metrics-supervisors" id="metrics-supervisors-body">
                        <tr id="metrics-supervisors-empty">
                            <td colspan="4" data-column-id="service">
                                <div class="empty-state">
                                    <x-heroicon-o-queue-list class="empty-state-icon" />
                                    <p class="empty-state-title">No supervisor data yet</p>
                                    <p class="empty-state-description">
                                        Supervisors will appear here once Horizon is running on your services and the Hub agent has synced data.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        (function () {
            var baseUrls = {
                summary: {{ Js::from(route('horizon.metrics.data.summary')) }},
                processedVsFailed: {{ Js::from(route('horizon.metrics.data.processed-vs-failed')) }},
                avgRuntime: {{ Js::from(route('horizon.metrics.data.avg-runtime')) }},
                byQueue: {{ Js::from(route('horizon.metrics.data.by-queue')) }},
                byService: {{ Js::from(route('horizon.metrics.data.by-service')) }},
                supervisors: {{ Js::from(route('horizon.metrics.data.supervisors')) }},
                workload: {{ Js::from(route('horizon.metrics.data.workload')) }}
            };

            var allLoaderIds = [
                'metrics-loader-jobs-minute', 'metrics-loader-jobs-hour', 'metrics-loader-failed-seven',
                'metrics-loader-processed-24', 'metrics-loader-failure-rate', 'metrics-loader-processed-failed',
                'metrics-loader-failure-rate-chart', 'metrics-loader-runtime-chart', 'metrics-loader-queue-chart'
            ];

            function getUrl(base, serviceId) {
                if (!serviceId) return base;
                var sep = base.indexOf('?') === -1 ? '?' : '&';
                return base + sep + 'service_id=' + encodeURIComponent(serviceId);
            }

            function hideLoader(id) {
                var el = document.getElementById(id);
                if (el) el.style.display = 'none';
            }

            function showLoader(id) {
                var el = document.getElementById(id);
                if (!el) return;
                if (id === 'metrics-loader-processed-failed' || id === 'metrics-loader-failure-rate-chart' || id === 'metrics-loader-runtime-chart' || id === 'metrics-loader-queue-chart') {
                    el.style.display = 'flex';
                } else {
                    el.style.display = '';
                }
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

            function setSummaryPlaceholders() {
                var v = document.getElementById('metrics-value-jobs-minute');
                if (v) v.textContent = '—';
                v = document.getElementById('metrics-value-jobs-hour');
                if (v) v.textContent = '—';
                v = document.getElementById('metrics-value-failed-seven');
                if (v) v.textContent = '—';
                v = document.getElementById('metrics-value-processed-24');
                if (v) v.textContent = '—';
                v = document.getElementById('metrics-value-failure-rate');
                if (v) v.textContent = '—';
            }

            function clearWorkloadTable() {
                var body = document.getElementById('metrics-workload-body');
                var empty = document.getElementById('metrics-workload-empty');
                var summary = document.getElementById('metrics-workload-summary');
                if (!body) return;
                while (body.firstChild) {
                    body.removeChild(body.firstChild);
                }
                if (empty) {
                    body.appendChild(empty);
                    empty.style.display = '';
                }
                if (summary) {
                    summary.textContent = '';
                }
            }

            function clearSupervisorsTable() {
                var body = document.getElementById('metrics-supervisors-body');
                var empty = document.getElementById('metrics-supervisors-empty');
                var summary = document.getElementById('metrics-supervisors-summary');
                if (!body) return;
                while (body.firstChild) {
                    body.removeChild(body.firstChild);
                }
                if (empty) {
                    body.appendChild(empty);
                    empty.style.display = '';
                }
                if (summary) {
                    summary.textContent = '';
                }
            }

            function renderWorkloadRows(rows) {
                var body = document.getElementById('metrics-workload-body');
                var empty = document.getElementById('metrics-workload-empty');
                var summary = document.getElementById('metrics-workload-summary');
                if (!body) return;

                if (!rows || !rows.length) {
                    if (empty) empty.style.display = '';
                    if (summary) summary.textContent = '';
                    return;
                }

                if (empty) {
                    empty.style.display = 'none';
                }

                var totalQueues = rows.length;
                var totalJobs = 0;

                rows.forEach(function (row) {
                    totalJobs += row.jobs || 0;
                    var tr = document.createElement('tr');
                    tr.className = 'transition-colors hover:bg-muted/30';

                    var tdService = document.createElement('td');
                    tdService.className = 'px-4 py-2.5 text-sm text-muted-foreground break-all';
                    tdService.setAttribute('data-column-id', 'service');
                    tdService.textContent = row.service || '';
                    tr.appendChild(tdService);

                    var tdQueue = document.createElement('td');
                    tdQueue.className = 'px-4 py-2.5 font-mono text-xs text-muted-foreground break-all';
                    tdQueue.setAttribute('data-column-id', 'queue');
                    tdQueue.textContent = row.queue || '';
                    tr.appendChild(tdQueue);

                    var tdJobs = document.createElement('td');
                    tdJobs.className = 'px-4 py-2.5 text-sm text-muted-foreground';
                    tdJobs.setAttribute('data-column-id', 'jobs');
                    tdJobs.textContent = formatNum(row.jobs || 0);
                    tr.appendChild(tdJobs);

                    var tdProcesses = document.createElement('td');
                    tdProcesses.className = 'px-4 py-2.5 text-sm text-muted-foreground';
                    tdProcesses.setAttribute('data-column-id', 'processes');
                    if (row.processes !== null && row.processes !== undefined) {
                        tdProcesses.textContent = formatNum(row.processes);
                    } else {
                        tdProcesses.textContent = '–';
                    }
                    tr.appendChild(tdProcesses);

                    var tdWait = document.createElement('td');
                    tdWait.className = 'px-4 py-2.5 text-sm text-muted-foreground';
                    tdWait.setAttribute('data-column-id', 'wait');
                    if (row.wait !== null && row.wait !== undefined) {
                        var span = document.createElement('span');
                        span.setAttribute('data-wait-seconds', String(row.wait));
                        span.textContent = row.wait.toFixed(2) + ' s';
                        tdWait.appendChild(span);
                    } else {
                        tdWait.textContent = '–';
                    }
                    tr.appendChild(tdWait);

                    body.appendChild(tr);
                });

                if (summary) {
                    summary.textContent = totalQueues + ' queue(s), ' + formatNum(totalJobs) + ' job(s) total';
                }

                if (window.formatQueueWaitElements) {
                    window.formatQueueWaitElements(body);
                }
            }

            function renderSupervisorsRows(rows) {
                var body = document.getElementById('metrics-supervisors-body');
                var empty = document.getElementById('metrics-supervisors-empty');
                var summary = document.getElementById('metrics-supervisors-summary');
                if (!body) return;

                if (!rows || !rows.length) {
                    if (empty) empty.style.display = '';
                    if (summary) summary.textContent = '';
                    return;
                }

                if (empty) {
                    empty.style.display = 'none';
                }

                var total = rows.length;
                var online = 0;

                rows.forEach(function (row) {
                    if (row.status === 'online') online++;

                    var tr = document.createElement('tr');
                    tr.className = 'transition-colors hover:bg-muted/30';

                    var tdService = document.createElement('td');
                    tdService.className = 'px-4 py-2.5 text-sm text-muted-foreground break-all';
                    tdService.setAttribute('data-column-id', 'service');
                    tdService.textContent = row.service || '';
                    tr.appendChild(tdService);

                    var tdName = document.createElement('td');
                    tdName.className = 'px-4 py-2.5 font-mono text-xs text-muted-foreground break-all';
                    tdName.setAttribute('data-column-id', 'supervisor');
                    tdName.textContent = row.name || '';
                    tr.appendChild(tdName);

                    var tdStatus = document.createElement('td');
                    tdStatus.className = 'px-4 py-2.5 text-xs';
                    tdStatus.setAttribute('data-column-id', 'status');
                    var badge = document.createElement('span');
                    if (row.status === 'online') {
                        badge.className = 'badge-success';
                        badge.textContent = 'Online';
                    } else {
                        badge.className = 'badge-warning';
                        badge.textContent = 'Stale';
                    }
                    tdStatus.appendChild(badge);
                    tr.appendChild(tdStatus);

                    var tdLastSeen = document.createElement('td');
                    tdLastSeen.className = 'px-4 py-2.5 text-xs text-muted-foreground';
                    tdLastSeen.setAttribute('data-column-id', 'last_seen');
                    if (row.last_seen_iso) {
                        tdLastSeen.setAttribute('data-datetime', row.last_seen_iso);
                        tdLastSeen.textContent = row.last_seen_human || '…';
                    } else {
                        tdLastSeen.textContent = '–';
                    }
                    tr.appendChild(tdLastSeen);

                    body.appendChild(tr);
                });

                if (summary) {
                    summary.textContent = total + ' supervisor(s), ' + online + ' online';
                }

                if (window.formatDateTimeElements) {
                    window.formatDateTimeElements(body);
                }
            }

            function loadAllMetrics(serviceId) {
                allLoaderIds.forEach(showLoader);
                setSummaryPlaceholders();
                clearWorkloadTable();
                clearSupervisorsTable();

                var urls = {
                    summary: getUrl(baseUrls.summary, serviceId),
                    processedVsFailed: getUrl(baseUrls.processedVsFailed, serviceId),
                    avgRuntime: getUrl(baseUrls.avgRuntime, serviceId),
                    byQueue: getUrl(baseUrls.byQueue, serviceId),
                    byService: getUrl(baseUrls.byService, null),
                    supervisors: getUrl(baseUrls.supervisors, serviceId),
                    workload: getUrl(baseUrls.workload, serviceId)
                };

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

                fetchSection(urls.workload, function (d) {
                    renderWorkloadRows(d.workload || []);
                }, null);

                fetchSection(urls.supervisors, function (d) {
                    renderSupervisorsRows(d.supervisors || []);
                }, null);
            }

            var filterEl = document.getElementById('metrics-service-filter');
            if (filterEl) {
                filterEl.addEventListener('change', function () {
                    loadAllMetrics(this.value || null);
                });
            }

            loadAllMetrics(filterEl ? filterEl.value || null : null);
        })();
    </script>
@endsection
