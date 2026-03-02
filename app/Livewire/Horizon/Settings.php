<?php

namespace App\Livewire\Horizon;

use App\Models\NotificationProvider;
use App\Models\Setting;
use Livewire\Component;

class Settings extends Component {
    public string $tab = 'appearance';

    public string $alert_email_interval_minutes = '5';

    public ?int $confirmingProviderId = null;

    public ?string $confirmingProviderName = null;

    public function mount(): void {
        $queryTab = request()->query('tab', '');
        if (in_array($queryTab, array('appearance', 'alerts', 'providers'), true)) {
            $this->tab = $queryTab;
        }
        $interval = Setting::get('alerts.email_interval_minutes');
        $this->alert_email_interval_minutes = $interval !== null ? (string) $interval : '5';
    }

    public function saveAlerts(): void {
        $this->validate([
            'alert_email_interval_minutes' => 'required|integer|min:0|max:1440',
        ]);
        Setting::set('alerts.email_interval_minutes', (int) $this->alert_email_interval_minutes);
        $this->dispatch('alerts-saved');
        $this->js('if(window.toast)window.toast.success(' . json_encode('Alerts saved.') . ')');
    }

    public function confirmDeleteProvider(int $id): void {
        $provider = NotificationProvider::find($id);
        if (! $provider) {
            return;
        }
        $this->confirmingProviderId = $provider->id;
        $this->confirmingProviderName = $provider->name;
    }

    public function cancelDeleteProvider(): void {
        $this->confirmingProviderId = null;
        $this->confirmingProviderName = null;
    }

    public function performDeleteProvider(): void {
        if ($this->confirmingProviderId === null) {
            return;
        }
        $id = $this->confirmingProviderId;
        $this->confirmingProviderId = null;
        $this->confirmingProviderName = null;

        $this->deleteProvider($id);
    }

    public function deleteProvider(int $id): void {
        $provider = NotificationProvider::find($id);
        if ($provider) {
            $provider->delete();
            $this->dispatch('toast', type: 'success', message: 'Provider deleted.');
            $this->js('if(window.toast)window.toast.success(' . json_encode('Provider deleted.') . ')');
        }
    }

    public function render() {
        $providers = NotificationProvider::orderBy('type')->orderBy('name')->get();

        return view('livewire.horizon.settings', [
            'providers' => $providers,
        ])->layout('layouts.app', ['header' => 'Settings']);
    }
}
