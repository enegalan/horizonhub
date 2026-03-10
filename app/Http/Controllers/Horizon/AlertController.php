<?php

namespace App\Http\Controllers\Horizon;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Models\AlertLog;
use App\Models\NotificationProvider;
use App\Models\Service;
use App\Services\AlertEngine;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AlertController extends Controller {
    /**
     * Display the list of alerts.
     *
     * @return View
     */
    public function index(): View {
        $alerts = Alert::with('service')
            ->withCount('alertLogs')
            ->withMax('alertLogs', 'sent_at')
            ->orderByDesc('created_at')
            ->get();

        return \view('horizon.alerts.index', [
            'alerts' => $alerts,
            'header' => 'Horizon Hub – Alerts',
        ]);
    }

    /**
     * Show the form to create a new alert.
     *
     * @return View
     */
    public function create(): View {
        return $this->formView(new Alert());
    }

    /**
     * Store a new alert.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function store(Request $request): RedirectResponse {
        $data = $this->validateAlert($request, null);
        $alert = Alert::create($data['alert']);
        $alert->notificationProviders()->sync($data['provider_ids']);

        return redirect()
            ->route('horizon.alerts.index')
            ->with('status', 'Alert created.');
    }

    /**
     * Show the form to edit an existing alert.
     *
     * @param Alert $alert
     * @return View
     */
    public function edit(Alert $alert): View {
        return $this->formView($alert);
    }

    /**
     * Update an existing alert.
     *
     * @param Request $request
     * @param Alert $alert
     * @return RedirectResponse
     */
    public function update(Request $request, Alert $alert): RedirectResponse {
        $data = $this->validateAlert($request, $alert);
        $alert->update($data['alert']);
        $alert->notificationProviders()->sync($data['provider_ids']);

        return redirect()
            ->route('horizon.alerts.index')
            ->with('status', 'Alert updated.');
    }

    /**
     * Show alert detail.
     *
     * @param Alert $alert
     * @param Request $request
     * @return View
     */
    public function show(Alert $alert, Request $request): View {
        $statusFilter = (string) $request->query('status', '');
        $serviceFilter = $request->query('service_id');
        $perPage = (int) $request->query('per_page', \config('horizonhub.alerts_per_page'));
        if ($perPage <= 0) {
            $perPage = \config('horizonhub.alerts_per_page');
        }

        $logsQuery = $alert->alertLogs()
            ->with('service')
            ->orderByDesc('sent_at');

        if ($serviceFilter !== null && $serviceFilter !== '') {
            $logsQuery->where('service_id', (int) $serviceFilter);
        }
        if ($statusFilter !== '') {
            $logsQuery->where('status', $statusFilter);
        }

        $logs = $logsQuery->paginate($perPage)->withQueryString();

        $chartData = [
            'chart24h' => $this->buildChart($alert, 1),
            'chart7d' => $this->buildChart($alert, 7),
            'chart30d' => $this->buildChart($alert, 30),
        ];

        $alertName = $alert->name ?: 'Alert #' . $alert->id;

        $selectedLogId = $request->query('log');
        $selectedLog = null;
        if ($selectedLogId !== null) {
            $selectedLog = AlertLog::with('service')
                ->where('alert_id', $alert->id)
                ->find($selectedLogId);
        }

        $services = Service::orderBy('name')->get();

        $ruleConfig = [
            'rule_type' => $alert->rule_type,
            'threshold' => $alert->threshold,
            'queue' => $alert->queue,
            'job_type' => $alert->job_type,
            'service_id' => $alert->service_id,
        ];

        return \view('horizon.alerts.show', [
            'alert' => $alert,
            'alertName' => $alertName,
            'logs' => $logs,
            'chartData' => $chartData,
            'services' => $services,
            'ruleConfig' => $ruleConfig,
            'selectedLog' => $selectedLog,
            'filters' => [
                'status' => $statusFilter,
                'service_id' => $serviceFilter !== null ? (string) $serviceFilter : '',
                'per_page' => $perPage,
            ],
            'header' => "Horizon Hub – $alertName",
        ]);
    }

    /**
     * Delete an alert.
     *
     * @param Alert $alert
     * @return RedirectResponse
     */
    public function destroy(Alert $alert): RedirectResponse {
        $name = $alert->name ?: ('Alert #' . $alert->id);
        $alert->delete();

        return redirect()
            ->route('horizon.alerts.index')
            ->with('status', 'Alert ' . $name . ' deleted.');
    }

    /**
     * Retry a failed alert log delivery.
     *
     * @param AlertLog $log
     * @param AlertEngine $engine
     * @return RedirectResponse
     */
    public function retryLog(AlertLog $log, AlertEngine $engine): RedirectResponse {
        if ($log->status === 'failed') {
            $engine->retryAlertLog($log);
        }

        return redirect()
            ->route('horizon.alerts.show', [$log->alert_id])
            ->with('status', 'Retry requested for alert delivery.');
    }

    /**
     * Build common data for create/edit alert form.
     *
     * @param Alert $alert
     * @return View
     */
    private function formView(Alert $alert): View {
        $services = Service::orderBy('name')->get();
        $providers = NotificationProvider::orderBy('type')->orderBy('name')->get();
        $ruleTypes = [
            'job_specific_failure' => 'Job failed (any)',
            'job_type_failure' => 'Job type failed',
            'failure_count' => 'Failure count in window',
            'avg_execution_time' => 'Avg execution time exceeded',
            'queue_blocked' => 'Queue blocked',
            'worker_offline' => 'Worker offline',
            'supervisor_offline' => 'Supervisor offline',
        ];
        $header = $alert->exists ? 'Edit alert' : 'New alert';

        return \view('horizon.alerts.form', [
            'alert' => $alert,
            'services' => $services,
            'providers' => $providers,
            'ruleTypes' => $ruleTypes,
            'selectedProviderIds' => $alert->exists ? $alert->notificationProviders()->pluck('notification_providers.id')->all() : [],
            'header' => "Horizon Hub – $header",
        ]);
    }

    /**
     * Validate and normalize alert data from the request.
     *
     * @param Request $request
     * @param Alert|null $alert
     * @return array{alert: array<string, mixed>, provider_ids: array<int>}
     */
    private function validateAlert(Request $request, ?Alert $alert): array {
        $baseRules = [
            'rule_type' => 'required|in:job_specific_failure,job_type_failure,failure_count,avg_execution_time,queue_blocked,worker_offline,supervisor_offline',
            'service_id' => 'nullable|exists:services,id',
            'queue' => 'nullable|string|max:255',
            'job_type' => 'nullable|string|max:255',
            'thresholdCount' => 'nullable|integer|min:1',
            'thresholdMinutes' => 'nullable|integer|min:1',
            'thresholdSeconds' => 'nullable|numeric|min:0.1',
            'provider_ids' => 'required|array|min:1',
            'provider_ids.*' => 'integer|exists:notification_providers,id',
            'email_interval_minutes' => 'required|integer|min:0|max:1440',
            'enabled' => 'sometimes|boolean',
            'name' => 'nullable|string|max:255',
        ];

        $ruleType = (string) $request->input('rule_type', 'failure_count');

        if ($ruleType === 'job_type_failure') {
            $baseRules['job_type'] = 'required|string|max:255';
        }
        if (\in_array($ruleType, ['failure_count', 'avg_execution_time', 'queue_blocked', 'worker_offline', 'supervisor_offline'], true)) {
            $baseRules['thresholdMinutes'] = 'required|integer|min:1';
        }
        if ($ruleType === 'failure_count') {
            $baseRules['thresholdCount'] = 'required|integer|min:1';
        }
        if ($ruleType === 'avg_execution_time') {
            $baseRules['thresholdSeconds'] = 'required|numeric|min:0.1';
        }

        $validated = $request->validate($baseRules);

        $threshold = [];
        if (\in_array($ruleType, ['failure_count', 'avg_execution_time', 'queue_blocked', 'worker_offline', 'supervisor_offline'], true)) {
            $threshold['minutes'] = (int) ($validated['thresholdMinutes'] ?? 0);
        }
        if ($ruleType === 'failure_count') {
            $threshold['count'] = (int) ($validated['thresholdCount'] ?? 0);
        }
        if ($ruleType === 'avg_execution_time') {
            $threshold['seconds'] = (float) ($validated['thresholdSeconds'] ?? 0.0);
        }

        $alertData = [
            'name' => $validated['name'] ?? null,
            'service_id' => ! empty($validated['service_id']) ? (int) $validated['service_id'] : null,
            'rule_type' => $ruleType,
            'threshold' => $threshold,
            'queue' => ! empty($validated['queue']) ? $validated['queue'] : null,
            'job_type' => ! empty($validated['job_type']) ? $validated['job_type'] : null,
            'notification_channels' => [],
            'enabled' => (bool) ($validated['enabled'] ?? true),
            'email_interval_minutes' => (int) $validated['email_interval_minutes'],
        ];

        return [
            'alert' => $alertData,
            'provider_ids' => $validated['provider_ids'],
        ];
    }

    /**
     * Build chart data for an alert for a given window.
     *
     * @param Alert $alert
     * @param int $days
     * @return array{xAxis: list<string>, sent: list<int>, failed: list<int>}
     */
    private function buildChart(Alert $alert, int $days): array {
        $since = $days === 1
            ? \now()->subDay()
            : \now()->subDays($days - 1)->startOfDay();

        $bucketFormatPhp = $days === 1 ? 'Y-m-d H:00' : 'Y-m-d';
        $bucketFormatSql = $days === 1 ? '%Y-%m-%d %H:00' : '%Y-%m-%d';

        $buckets = [];
        $totalSlots = $days === 1 ? 24 : $days;
        for ($i = 0; $i < $totalSlots; $i++) {
            $key = $days === 1
                ? \now()->subHours(23 - $i)->format($bucketFormatPhp)
                : \now()->subDays($days - 1 - $i)->format($bucketFormatPhp);
            $buckets[$key] = ['sent' => 0, 'failed' => 0];
        }

        $logs = AlertLog::where('alert_id', $alert->id)
            ->where('sent_at', '>=', $since)
            ->selectRaw("DATE_FORMAT(sent_at, '" . $bucketFormatSql . "') as bucket, status, COUNT(*) as total")
            ->groupBy('bucket', 'status')
            ->get();

        foreach ($logs as $row) {
            $key = $row->bucket;
            if (! isset($buckets[$key])) {
                continue;
            }
            if ($row->status === 'sent') {
                $buckets[$key]['sent'] += (int) $row->total;
            } else {
                $buckets[$key]['failed'] += (int) $row->total;
            }
        }

        $xAxis = [];
        $sent = [];
        $failed = [];
        foreach ($buckets as $k => $v) {
            $xAxis[] = $days === 1
                ? \Carbon\Carbon::parse($k)->format('H:i')
                : \Carbon\Carbon::parse($k)->format('M j');
            $sent[] = $v['sent'];
            $failed[] = $v['failed'];
        }

        return ['xAxis' => $xAxis, 'sent' => $sent, 'failed' => $failed];
    }
}
