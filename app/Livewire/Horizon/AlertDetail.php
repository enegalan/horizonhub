<?php

namespace App\Livewire\Horizon;

use App\Models\Alert;
use App\Models\AlertLog;
use App\Models\Service;
use App\Services\AlertEngine;
use Carbon\Carbon;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Contracts\View\View;

class AlertDetail extends Component {
    use WithPagination;

    /**
     * The alert to display.
     *
     * @var Alert
     */
    public Alert $alert;

    /**
     * The status filter to apply to the alert logs.
     *
     * @var string
     */
    public string $statusFilter = '';

    /**
     * The number of alert logs to display per page.
     *
     * @var int
     */
    public int $perPage = 20;

    /**
     * The ID of the selected alert log.
     *
     * @var int|null
     */
    public ?int $selectedLogId = null;

    /**
     * The service filter to apply to the alert logs.
     *
     * @var int|null
     */
    public ?int $serviceFilter = null;

    /**
     * Retry a alert log.
     *
     * @param int $id
     * @return void
     */
    public function retryLog(int $id): void {
        $log = AlertLog::with('alert')->find($id);
        if (! $log) {
            return;
        }
        if ($log->status !== 'failed') {
            return;
        }
        \app(AlertEngine::class)->retryAlertLog($log);
        $this->resetPage();
    }

    /**
     * Open the alert log modal.
     *
     * @param int $id
     * @return void
     */
    public function openLogModal(int $id): void {
        $this->selectedLogId = $id;
    }

    /**
     * Close the alert log modal.
     *
     * @return void
     */
    public function closeLogModal(): void {
        $this->selectedLogId = null;
    }

    /**
     * Reset the page when the status filter is updated.
     *
     * @return void
     */
    public function updatedStatusFilter(): void {
        $this->resetPage();
    }

    /**
     * Reset the page when the per page is updated.
     *
     * Hook for Livewire.
     *
     * @return void
     */
    public function updatedPerPage(): void {
        $this->resetPage();
    }

    /**
     * Reset the page when the service filter is updated.
     *
     * Hook for Livewire.
     *
     * @return void
     */
    public function updatedServiceFilter(): void {
        $this->resetPage();
    }

    /**
     * Get the listeners for the alert detail component.
     *
     * @return array<string, string>
     */
    public function getListeners(): array {
        return [
            'echo:horizon-hub.dashboard,HorizonEvent' => '$refresh',
        ];
    }

    /**
     * Get the chart data for the last 24 hours.
     *
     * @return array{xAxis: list<string>, sent: list<int>, failed: list<int>}
     */
    private function getChart24h(): array {
        $since = \now()->subDay();
        $bucketFormatPhp = 'Y-m-d H:00';
        $bucketFormatSql = '%Y-%m-%d %H:00';
        $buckets = [];
        for ($i = 0; $i < 24; $i++) {
            $key = \now()->subHours(23 - $i)->format($bucketFormatPhp);
            $buckets[$key] = ['sent' => 0, 'failed' => 0];
        }
        $logs = AlertLog::where('alert_id', $this->alert->id)
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
            $xAxis[] = Carbon::parse($k)->format('H:i');
            $sent[] = $v['sent'];
            $failed[] = $v['failed'];
        }
        return ['xAxis' => $xAxis, 'sent' => $sent, 'failed' => $failed];
    }

    /**
     * Get the chart data for the last 7 days.
     *
     * @return array{xAxis: list<string>, sent: list<int>, failed: list<int>}
     */
    private function getChart7d(): array {
        $since = \now()->subDays(6)->startOfDay();
        $bucketFormatPhp = 'Y-m-d';
        $bucketFormatSql = '%Y-%m-%d';
        $buckets = [];
        for ($i = 0; $i < 7; $i++) {
            $key = \now()->subDays(6 - $i)->format($bucketFormatPhp);
            $buckets[$key] = ['sent' => 0, 'failed' => 0];
        }
        $logs = AlertLog::where('alert_id', $this->alert->id)
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
            $xAxis[] = Carbon::parse($k)->format('M j');
            $sent[] = $v['sent'];
            $failed[] = $v['failed'];
        }
        return ['xAxis' => $xAxis, 'sent' => $sent, 'failed' => $failed];
    }

    /**
     * Get the chart data for the last 30 days.
     *
     * @return array{xAxis: list<string>, sent: list<int>, failed: list<int>}
     */
    private function getChart30d(): array {
        $since = \now()->subDays(29)->startOfDay();
        $bucketFormatPhp = 'Y-m-d';
        $bucketFormatSql = '%Y-%m-%d';
        $buckets = [];
        for ($i = 0; $i < 30; $i++) {
            $key = \now()->subDays(29 - $i)->format($bucketFormatPhp);
            $buckets[$key] = ['sent' => 0, 'failed' => 0];
        }
        $logs = AlertLog::where('alert_id', $this->alert->id)
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
            $xAxis[] = Carbon::parse($k)->format('M j');
            $sent[] = $v['sent'];
            $failed[] = $v['failed'];
        }
        return ['xAxis' => $xAxis, 'sent' => $sent, 'failed' => $failed];
    }

    /**
     * Render the alert detail component.
     *
     * @return View
     */
    public function render(): View {
        $logs = $this->alert->alertLogs()
            ->with('service')
            ->when($this->serviceFilter !== null, fn ($q) => $q->where('service_id', $this->serviceFilter))
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->orderByDesc('sent_at')
            ->paginate((int) $this->perPage);

        $chartData = [
            'chart24h' => $this->getChart24h(),
            'chart7d' => $this->getChart7d(),
            'chart30d' => $this->getChart30d(),
        ];

        $alertName = $this->alert->name ?: 'Alert #' . $this->alert->id;
        $selectedLog = $this->selectedLogId !== null ? $logs->firstWhere('id', $this->selectedLogId) : null;

        $services = Service::orderBy('name')->get();

        $ruleConfig = [
            'rule_type' => $this->alert->rule_type,
            'threshold' => $this->alert->threshold,
            'queue' => $this->alert->queue,
            'job_type' => $this->alert->job_type,
            'service_id' => $this->alert->service_id,
        ];

        return \view('livewire.horizon.alert-detail', [
            'logs' => $logs,
            'chartData' => $chartData,
            'alertName' => $alertName,
            'selectedLog' => $selectedLog,
            'services' => $services,
            'ruleConfig' => $ruleConfig,
        ])->layout('layouts.app', [
            'header' => 'Horizon Hub – ' . $alertName,
        ]);
    }
}
