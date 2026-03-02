<?php

namespace App\Livewire\Horizon;

use App\Models\Alert;
use App\Models\AlertLog;
use App\Services\AlertEngine;
use Carbon\Carbon;
use Livewire\Component;
use Livewire\WithPagination;

class AlertDetail extends Component {
    use WithPagination;

    public Alert $alert;

    public string $statusFilter = '';

    public int $perPage = 20;

    public ?int $selectedLogId = null;

    public function retryLog(int $id): void {
        $log = AlertLog::with('alert')->find($id);
        if (! $log) {
            return;
        }
        if ($log->status !== 'failed') {
            return;
        }
        app(AlertEngine::class)->retryAlertLog($log);
        $this->resetPage();
    }

    public function openLogModal(int $id): void {
        $this->selectedLogId = $id;
    }

    public function closeLogModal(): void {
        $this->selectedLogId = null;
    }

    public function updatedStatusFilter(): void {
        $this->resetPage();
    }

    public function updatedPerPage(): void {
        $this->resetPage();
    }

    public function getListeners(): array {
        return [
            'echo:horizon-hub.dashboard,HorizonEvent' => '$refresh',
        ];
    }

    /**
     * @return array{xAxis: list<string>, sent: list<int>, failed: list<int>}
     */
    private function getChart24h(): array {
        $since = now()->subDay();
        $bucketFormatPhp = 'Y-m-d H:00';
        $bucketFormatSql = '%Y-%m-%d %H:00';
        $buckets = array();
        for ($i = 0; $i < 24; $i++) {
            $key = now()->subHours(23 - $i)->format($bucketFormatPhp);
            $buckets[$key] = array('sent' => 0, 'failed' => 0);
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
        $xAxis = array();
        $sent = array();
        $failed = array();
        foreach ($buckets as $k => $v) {
            $xAxis[] = Carbon::parse($k)->format('H:i');
            $sent[] = $v['sent'];
            $failed[] = $v['failed'];
        }
        return array('xAxis' => $xAxis, 'sent' => $sent, 'failed' => $failed);
    }

    /**
     * @return array{xAxis: list<string>, sent: list<int>, failed: list<int>}
     */
    private function getChart7d(): array {
        $since = now()->subDays(6)->startOfDay();
        $bucketFormatPhp = 'Y-m-d';
        $bucketFormatSql = '%Y-%m-%d';
        $buckets = array();
        for ($i = 0; $i < 7; $i++) {
            $key = now()->subDays(6 - $i)->format($bucketFormatPhp);
            $buckets[$key] = array('sent' => 0, 'failed' => 0);
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
        $xAxis = array();
        $sent = array();
        $failed = array();
        foreach ($buckets as $k => $v) {
            $xAxis[] = Carbon::parse($k)->format('M j');
            $sent[] = $v['sent'];
            $failed[] = $v['failed'];
        }
        return array('xAxis' => $xAxis, 'sent' => $sent, 'failed' => $failed);
    }

    /**
     * @return array{xAxis: list<string>, sent: list<int>, failed: list<int>}
     */
    private function getChart30d(): array {
        $since = now()->subDays(29)->startOfDay();
        $bucketFormatPhp = 'Y-m-d';
        $bucketFormatSql = '%Y-%m-%d';
        $buckets = array();
        for ($i = 0; $i < 30; $i++) {
            $key = now()->subDays(29 - $i)->format($bucketFormatPhp);
            $buckets[$key] = array('sent' => 0, 'failed' => 0);
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
        $xAxis = array();
        $sent = array();
        $failed = array();
        foreach ($buckets as $k => $v) {
            $xAxis[] = Carbon::parse($k)->format('M j');
            $sent[] = $v['sent'];
            $failed[] = $v['failed'];
        }
        return array('xAxis' => $xAxis, 'sent' => $sent, 'failed' => $failed);
    }

    public function render() {
        $logs = $this->alert->alertLogs()
            ->with('service')
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->orderByDesc('sent_at')
            ->paginate((int) $this->perPage);

        $chartData = array(
            'chart24h' => $this->getChart24h(),
            'chart7d' => $this->getChart7d(),
            'chart30d' => $this->getChart30d(),
        );

        $alertName = $this->alert->name ?: ('Alert #' . $this->alert->id);
        $selectedLog = null;
        if ($this->selectedLogId !== null) {
            $selectedLog = $logs->firstWhere('id', $this->selectedLogId);
        }

        return view('livewire.horizon.alert-detail', [
            'logs' => $logs,
            'chartData' => $chartData,
            'alertName' => $alertName,
            'selectedLog' => $selectedLog,
        ])->layout('layouts.app', [
            'header' => 'Horizon Hub – ' . $alertName,
        ]);
    }
}
