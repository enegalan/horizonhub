<?php

namespace App\Livewire\Horizon;

use App\Models\Alert;
use Livewire\Component;
use Illuminate\Contracts\View\View;

class AlertList extends Component {
    /**
     * The ID of the alert to confirm deletion.
     *
     * @var int|null
     */
    public ?int $confirmingAlertId = null;

    /**
     * The name of the alert to confirm deletion.
     *
     * @var string|null
     */
    public ?string $confirmingAlertName = null;

    /**
     * Confirm the deletion of an alert.
     *
     * @param int $id
     * @return void
     */
    public function confirmDeleteAlert(int $id): void {
        $alert = Alert::find($id);
        if (! $alert) {
            return;
        }
        $this->confirmingAlertId = $alert->id;
        $this->confirmingAlertName = $alert->name ?: ('Alert #' . $alert->id);
    }

    /**
     * Cancel the deletion of an alert.
     *
     * @return void
     */
    public function cancelDeleteAlert(): void {
        $this->confirmingAlertId = null;
        $this->confirmingAlertName = null;
    }

    /**
     * Perform the deletion of an alert.
     *
     * @return void
     */
    public function performDeleteAlert(): void {
        if ($this->confirmingAlertId === null) {
            return;
        }
        $id = $this->confirmingAlertId;
        $this->confirmingAlertId = null;
        $this->confirmingAlertName = null;

        $this->deleteAlert($id);
    }

    /**
     * Delete an alert.
     *
     * @param int $id
     * @return void
     */
    public function deleteAlert(int $id): void {
        $alert = Alert::find($id);
        if ($alert) {
            $alert->delete();
            $this->dispatch('toast', type: 'success', message: 'Alert deleted.');
        }
    }

    /**
     * Render the alert list component.
     *
     * @return View
     */
    public function render(): View {
        $alerts = Alert::with('service')
            ->withCount('alertLogs')
            ->withMax('alertLogs', 'sent_at')
            ->orderByDesc('created_at')
            ->get();

        return \view('livewire.horizon.alert-list', [
            'alerts' => $alerts,
        ])->layout('layouts.app', ['header' => 'Horizon Hub – Alerts']);
    }
}
