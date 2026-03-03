<?php

namespace App\Livewire\Horizon;

use App\Models\NotificationProvider;
use App\Models\Setting;
use Livewire\Component;
use Illuminate\Contracts\View\View;

class Settings extends Component {
    /**
     * The tab to display.
     *
     * @var string
     */
    public string $tab = 'appearance';

    /**
     * The email interval minutes for alerts.
     *
     * @var string
     */
    public string $alert_email_interval_minutes = '5';

    /**
     * The ID of the provider being confirmed for deletion.
     *
     * @var int|null
     */
    public ?int $confirmingProviderId = null;

    /**
     * The name of the provider being confirmed for deletion.
     *
     * @var string|null
     */
    public ?string $confirmingProviderName = null;

    /**
     * The message of the provider being confirmed for deletion.
     *
     * @var string|null
     */
    public ?string $confirmingProviderMessage = null;

    /**
     * Mount the settings component.
     *
     * @return void
     */
    public function mount(): void {
        $queryTab = request()->query('tab', '');
        if (in_array($queryTab, array('appearance', 'alerts', 'providers'), true)) {
            $this->tab = $queryTab;
        }
        $interval = Setting::get('alerts.email_interval_minutes');
        $this->alert_email_interval_minutes = $interval !== null ? (string) $interval : '5';
    }

    /**
     * Save the alerts.
     *
     * @return void
     */
    public function saveAlerts(): void {
        $this->validate([
            'alert_email_interval_minutes' => 'required|integer|min:0|max:1440',
        ]);
        Setting::set('alerts.email_interval_minutes', (int) $this->alert_email_interval_minutes);
        $this->dispatch('alerts-saved');
    }

    /**
     * Confirm the deletion of a provider.
     *
     * @param int $id
     * @return void
     */
    public function confirmDeleteProvider(int $id): void {
        $provider = NotificationProvider::find($id);

        $this->confirmingProviderId = $id;
        $this->confirmingProviderName = $provider ? $provider->name : ('Provider #' . $id);
        $name = $this->confirmingProviderName;
        $this->confirmingProviderMessage = 'Are you sure you want to delete provider ' . $name . '? Alerts using it will stop notifying through it.';
    }

    /**
     * Cancel the deletion of a provider.
     *
     * @return void
     */
    public function cancelDeleteProvider(): void {
        $this->confirmingProviderId = null;
        $this->confirmingProviderName = null;
        $this->confirmingProviderMessage = null;
    }

    /**
     * Perform the deletion of a provider.
     *
     * @return void
     */
    public function performDeleteProvider(): void {
        if ($this->confirmingProviderId === null) {
            return;
        }
        $id = $this->confirmingProviderId;
        $this->confirmingProviderId = null;
        $this->confirmingProviderName = null;
        $this->confirmingProviderMessage = null;

        $this->deleteProvider($id);
    }

    /**
     * Delete a provider.
     *
     * @param int $id
     * @return void
     */
    public function deleteProvider(int $id): void {
        $provider = NotificationProvider::find($id);
        if ($provider) {
            $provider->delete();
            $this->dispatch('toast', type: 'success', message: 'Provider deleted.');
        }
    }

    /**
     * Render the settings component.
     *
     * @return View
     */
    public function render(): View {
        $providers = NotificationProvider::orderBy('type')->orderBy('name')->get();

        return view('livewire.horizon.settings', [
            'providers' => $providers,
        ])->layout('layouts.app', ['header' => 'Settings']);
    }
}
