<?php

namespace App\Http\Controllers\Horizon;

use App\Contracts\HorizonHubStore;
use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Models\AlertLog;
use App\Services\Alerts\AlertEvaluationBatchService;
use App\Services\Alerts\AlertUpsertService;
use App\Services\Alerts\Engine\AlertEngine;
use App\Support\Alerts\AlertDeliveryLogPresenter;
use App\Support\Alerts\AlertRuleCatalog;
use App\Support\FlashStatus;
use Illuminate\Contracts\View\View;
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
     * The store.
     */
    private HorizonHubStore $store;

    /**
     * The constructor.
     *
     * @param AlertEvaluationBatchService $evaluationBatch The alert evaluation batch service.
     * @param AlertUpsertService $alertUpsert The alert upsert service.
     * @param HorizonHubStore $store The store.
     */
    public function __construct(AlertEvaluationBatchService $evaluationBatch, AlertUpsertService $alertUpsert, HorizonHubStore $store)
    {
        $this->alertUpsert = $alertUpsert;
        $this->evaluationBatch = $evaluationBatch;
        $this->store = $store;
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
        $this->store->deleteAlert($alert);

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
            'evaluateAllAlertsVisible' => $this->store->enabledAlertsExist(),
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

        $logs = $this->store->paginateAlertLogsForAlert($alert, [
            'status' => $statusFilter,
            'service_id' => $serviceFilter,
            'per_page' => $perPage,
            'page' => (int) $request->query('page', 1),
        ]);

        $selectedLogId = $request->query('log');
        $selectedLog = null;

        if (! empty($selectedLogId)) {
            $selectedLog = $this->store->findAlertLogForAlert((int) $alert->id, $selectedLogId);
        }

        return \view('horizon.alerts.show', [
            'alert' => $alert,
            'alertName' => $alert->name,
            'logs' => $logs,
            'chartData' => new \stdClass,
            'defer' => true,
            'services' => $this->store->enabledServicesOrdered(),
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
        $this->store->createAlert($data['alert'], $data['provider_ids']);

        return redirect()
            ->route('horizon.alerts.index')
            ->with('status', FlashStatus::success('Alert created.'));
    }

    /**
     * Toggle whether an alert is enabled.
     */
    public function toggleEnabled(Alert $alert): JsonResponse
    {
        $this->store->toggleAlertEnabled($alert);

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
        $this->store->updateAlert($alert, $data['alert'], $data['provider_ids']);

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

        return [
            'alert' => $alert,
            'services' => $this->store->servicesForAlertForm($selectedIds),
            'providers' => $this->store->providersOrdered(),
            'ruleTypes' => AlertRuleCatalog::ruleTypeLabels(),
            'formRuleMetadata' => AlertRuleCatalog::formRuleMetadata(),
            'selectedProviderIds' => $alert->exists ? $alert->notificationProviders->pluck('id')->all() : [],
            'selectedServiceIds' => $alert->service_ids,
            'header' => $alert->exists ? 'Edit alert' : 'New alert',
        ];
    }
}
