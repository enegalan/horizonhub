<?php

namespace App\Http\Controllers\Horizon;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Models\AlertLog;
use App\Models\NotificationProvider;
use App\Models\Service;
use App\Services\Alerts\AlertEvaluationBatchService;
use App\Services\Alerts\AlertUpsertService;
use App\Services\Alerts\Engine\AlertEngine;
use App\Support\Alerts\AlertDeliveryLogPresenter;
use App\Support\Alerts\AlertRuleCatalog;
use App\Support\FlashStatus;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    /**
     * The alert upsert service.
     */
    private AlertUpsertService $alertUpsert;

    /**
     * The alert evaluation batch service.
     */
    private AlertEvaluationBatchService $evaluationBatch;

    /**
     * The constructor.
     *
     * @param AlertEvaluationBatchService $evaluationBatch The alert evaluation batch service.
     * @param AlertUpsertService $alertUpsert The alert upsert service.
     */
    public function __construct(AlertEvaluationBatchService $evaluationBatch, AlertUpsertService $alertUpsert)
    {
        $this->alertUpsert = $alertUpsert;
        $this->evaluationBatch = $evaluationBatch;
    }

    /**
     * Show the form to create a new alert.
     */
    public function create(): View
    {
        return \view('horizon.alerts.form', $this->private__buildFormViewVariables(new Alert));
    }

    /**
     * Delete an alert.
     */
    public function destroy(Alert $alert): RedirectResponse
    {
        $alert->delete();

        return redirect()
            ->route('horizon.alerts.index')
            ->with('status', FlashStatus::success("Alert {$alert->name} deleted."));
    }

    /**
     * Show the form to edit an existing alert.
     */
    public function edit(Alert $alert): View
    {
        return \view('horizon.alerts.form', $this->private__buildFormViewVariables($alert));
    }

    /**
     * Evaluate a single alert immediately.
     */
    public function evaluateAlert(Alert $alert, AlertEngine $engine): JsonResponse
    {
        return \response()->json($engine->evaluateAlert($alert));
    }

    /**
     * Evaluate all enabled alerts using a background batch job.
     */
    public function evaluateAllAlerts(): JsonResponse
    {
        return \response()->json($this->evaluationBatch->startEvaluateAll());
    }

    /**
     * Get evaluation batch status and progress.
     */
    public function evaluationStatus(string $evaluationId): JsonResponse
    {
        return \response()->json($this->evaluationBatch->getEvaluationStatus($evaluationId));
    }

    /**
     * Display the list of alerts.
     */
    public function index(): View
    {
        return \view('horizon.alerts.index', [
            'alerts' => collect(),
            'defer' => true,
            'evaluateAllAlertsVisible' => Alert::query()->enabled()->exists(),
            'header' => 'Alerts',
        ]);
    }

    /**
     * Retry a failed alert log delivery.
     */
    public function retryLog(AlertLog $log, AlertEngine $engine): RedirectResponse
    {
        if ($log->status === 'failed') {
            $engine->retryAlertLog($log);
        }

        return redirect()
            ->route('horizon.alerts.show', [$log->alert_id])
            ->with('status', FlashStatus::success('Retry requested for alert delivery.'));
    }

    /**
     * Show alert detail.
     */
    public function show(Alert $alert, Request $request): View
    {
        $statusFilter = (string) $request->query('status', '');
        $serviceFilter = $request->query('service_id');
        $perPage = (int) max(1, $request->query('per_page', config('horizonhub.jobs_per_page')));

        $logsQuery = $alert->alertLogs()
            ->with('service')
            ->orderByDesc('sent_at');

        if (! empty($serviceFilter)) {
            $logsQuery->where('service_id', (int) $serviceFilter);
        }

        if (! empty($statusFilter)) {
            $logsQuery->where('status', $statusFilter);
        }

        $logs = $logsQuery->paginate($perPage)->withQueryString();

        $selectedLogId = $request->query('log');
        $selectedLog = null;

        if (! empty($selectedLogId)) {
            $selectedLog = AlertLog::with('service')
                ->where('alert_id', $alert->id)
                ->find($selectedLogId);
        }

        return \view('horizon.alerts.show', [
            'alert' => $alert,
            'alertName' => $alert->name,
            'logs' => $logs,
            'chartData' => new \stdClass,
            'defer' => true,
            'services' => Service::query()->enabled()->orderBy('name')->get(),
            'selectedLog' => $selectedLog,
            'initialDeliveryLogPayload' => AlertDeliveryLogPresenter::payloadFromLog($selectedLog),
            'filters' => [
                'status' => $statusFilter,
                'service_id' => ! empty($serviceFilter) ? (string) $serviceFilter : '',
                'per_page' => $perPage,
            ],
            'header' => $alert->name,
        ]);
    }

    /**
     * Store a new alert.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $this->alertUpsert->validateAlert($request);
        $alert = Alert::create($data['alert']);
        $alert->notificationProviders()->sync($data['provider_ids']);

        return redirect()
            ->route('horizon.alerts.index')
            ->with('status', FlashStatus::success('Alert created.'));
    }

    /**
     * Toggle whether an alert is enabled.
     */
    public function toggleEnabled(Alert $alert): JsonResponse
    {
        $alert->enabled = ! $alert->enabled;
        $alert->save();

        return \response()->json([
            'alert_id' => $alert->id,
            'enabled' => $alert->enabled,
        ]);
    }

    /**
     * Update an existing alert.
     */
    public function update(Request $request, Alert $alert): RedirectResponse
    {
        $data = $this->alertUpsert->validateAlert($request);
        $alert->update($data['alert']);
        $alert->notificationProviders()->sync($data['provider_ids']);

        return redirect()
            ->route('horizon.alerts.index')
            ->with('status', FlashStatus::success('Alert updated.'));
    }

    /**
     * Build the form view variables.
     *
     * @param Alert $alert The alert.
     *
     * @return array<string, mixed>
     */
    private function private__buildFormViewVariables(Alert $alert): array
    {
        $selectedIds = $alert->exists ? $alert->service_ids : [];
        $services = Service::query()
            ->where(function (Builder $query) use ($selectedIds): void {
                $query->enabled();

                if ($selectedIds !== []) {
                    $query->orWhere(fn (Builder $q) => $q->disabled()->whereIn('id', $selectedIds));
                }
            })
            ->orderBy('name')
            ->get();

        return [
            'alert' => $alert,
            'services' => $services,
            'providers' => NotificationProvider::orderBy('type')->orderBy('name')->get(),
            'ruleTypes' => AlertRuleCatalog::ruleTypeLabels(),
            'formRuleMetadata' => AlertRuleCatalog::formRuleMetadata(),
            'selectedProviderIds' => $alert->exists ? $alert->notificationProviders()->pluck('notification_providers.id')->all() : [],
            'selectedServiceIds' => $alert->service_ids,
            'header' => $alert->exists ? 'Edit alert' : 'New alert',
        ];
    }
}
