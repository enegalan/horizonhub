<?php

namespace App\Livewire\Horizon;

use App\Models\Alert;
use App\Models\NotificationProvider;
use App\Models\Service;
use Livewire\Component;

class AlertForm extends Component {
    public ?Alert $alert = null;

    public string $name = '';

    public ?int $service_id = null;

    public string $rule_type = 'failure_count';

    public string $queue = '';

    public string $job_type = '';

    public int $thresholdCount = 5;

    public int $thresholdMinutes = 15;

    public float $thresholdSeconds = 60;

    public bool $enabled = true;

    /** @var array<int> */
    public array $provider_ids = array();

    public static function getRuleTypes(): array {
        return array(
            'job_specific_failure' => 'Job failed (any)',
            'job_type_failure' => 'Job type failed',
            'failure_count' => 'Failure count in window',
            'avg_execution_time' => 'Avg execution time exceeded',
            'queue_blocked' => 'Queue blocked',
            'worker_offline' => 'Worker offline',
        );
    }

    public function mount(?Alert $alert = null): void {
        if ($alert !== null) {
            $this->alert = $alert;
        }

        if ($this->alert) {
            $this->name = (string) $this->alert->name;
            $this->service_id = $this->alert->service_id;
            $this->rule_type = $this->alert->rule_type;
            $this->queue = (string) $this->alert->queue;
            $this->job_type = (string) $this->alert->job_type;
            $this->enabled = (bool) $this->alert->enabled;
            $this->provider_ids = $this->alert->notificationProviders->pluck('id')->all();
        }
    }

    public function save(): void {
        $this->validateRuleType();
        $this->validate([
            'provider_ids' => 'required|array|min:1',
            'provider_ids.*' => 'integer|exists:notification_providers,id',
        ]);

        $threshold = $this->buildThreshold();

        $data = array(
            'name' => $this->name ?: null,
            'service_id' => $this->service_id ?: null,
            'rule_type' => $this->rule_type,
            'threshold' => $threshold,
            'queue' => $this->queue ?: null,
            'job_type' => $this->job_type ?: null,
            'notification_channels' => array(),
            'enabled' => $this->enabled,
        );

        if ($this->alert) {
            $this->alert->update($data);
            $this->alert->notificationProviders()->sync($this->provider_ids);
            $this->dispatch('toast', type: 'success', message: 'Alert updated.');
            $this->js('if(window.toast)window.toast.success(' . json_encode('Alert updated.') . ')');
        } else {
            $alert = Alert::create($data);
            $alert->notificationProviders()->sync($this->provider_ids);
            $this->dispatch('toast', type: 'success', message: 'Alert created.');
            $this->js('if(window.toast)window.toast.success(' . json_encode('Alert created.') . ')');
        }

        $this->redirect(route('horizon.alerts.index'), navigate: true);
    }

    private function validateRuleType(): void {
        $rules = array(
            'rule_type' => 'required|in:job_specific_failure,job_type_failure,failure_count,avg_execution_time,queue_blocked,worker_offline',
            'service_id' => 'nullable|exists:services,id',
            'queue' => 'nullable|string|max:255',
            'job_type' => 'nullable|string|max:255',
            'thresholdCount' => 'nullable|integer|min:1',
            'thresholdMinutes' => 'nullable|integer|min:1',
            'thresholdSeconds' => 'nullable|numeric|min:0.1',
        );

        if ($this->rule_type === 'job_type_failure') {
            $rules['job_type'] = 'required|string|max:255';
        }
        if (in_array($this->rule_type, array('failure_count', 'avg_execution_time', 'queue_blocked', 'worker_offline'), true)) {
            $rules['thresholdMinutes'] = 'required|integer|min:1';
        }
        if ($this->rule_type === 'failure_count') {
            $rules['thresholdCount'] = 'required|integer|min:1';
        }
        if ($this->rule_type === 'avg_execution_time') {
            $rules['thresholdSeconds'] = 'required|numeric|min:0.1';
        }

        $this->validate($rules);
    }

    private function buildThreshold(): array {
        $t = array();
        if (in_array($this->rule_type, array('failure_count', 'avg_execution_time', 'queue_blocked', 'worker_offline'), true)) {
            $t['minutes'] = $this->thresholdMinutes;
        }
        if ($this->rule_type === 'failure_count') {
            $t['count'] = $this->thresholdCount;
        }
        if ($this->rule_type === 'avg_execution_time') {
            $t['seconds'] = $this->thresholdSeconds;
        }

        return $t;
    }

    public function render() {
        $services = Service::orderBy('name')->get();
        $providers = NotificationProvider::orderBy('type')->orderBy('name')->get();
        $header = $this->alert ? 'Edit alert' : 'New alert';

        return view('livewire.horizon.alert-form', [
            'services' => $services,
            'providers' => $providers,
            'ruleTypes' => self::getRuleTypes(),
        ])->layout('layouts.app', ['header' => 'Horizon Hub – ' . $header]);
    }
}
