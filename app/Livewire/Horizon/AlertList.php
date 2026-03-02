<?php

namespace App\Livewire\Horizon;

use App\Models\Alert;
use Livewire\Component;

class AlertList extends Component {
    public ?int $confirmingAlertId = null;

    public ?string $confirmingAlertName = null;

    public function confirmDeleteAlert(int $id): void {
        $alert = Alert::find($id);
        if (! $alert) {
            return;
        }
        $this->confirmingAlertId = $alert->id;
        $this->confirmingAlertName = $alert->name ?: ('Alert #' . $alert->id);
    }

    public function cancelDeleteAlert(): void {
        $this->confirmingAlertId = null;
        $this->confirmingAlertName = null;
    }

    public function performDeleteAlert(): void {
        if ($this->confirmingAlertId === null) {
            return;
        }
        $id = $this->confirmingAlertId;
        $this->confirmingAlertId = null;
        $this->confirmingAlertName = null;

        $this->deleteAlert($id);
    }

    public function deleteAlert(int $id): void {
        $alert = Alert::find($id);
        if ($alert) {
            $alert->delete();
            $this->dispatch('toast', type: 'success', message: 'Alert deleted.');
            $this->js('if(window.toast)window.toast.success(' . json_encode('Alert deleted.') . ')');
        }
    }

    public function render() {
        $alerts = Alert::with('service')
            ->withCount('alertLogs')
            ->withMax('alertLogs', 'sent_at')
            ->orderByDesc('created_at')
            ->get();

        return view('livewire.horizon.alert-list', [
            'alerts' => $alerts,
        ])->layout('layouts.app', ['header' => 'Horizon Hub – Alerts']);
    }
}
