<?php

namespace App\Livewire\Horizon;

use App\Models\Alert;
use App\Models\NotificationProvider;
use App\Models\Service;
use Livewire\Component;
use Illuminate\Contracts\View\View;

class AlertForm extends Component {
    /**
     * The alert to edit.
     *
     * @var Alert|null
     */
    public ?Alert $alert = null;

    /**
     * The name of the alert.
     *
     * @var string
     */
    public string $name = '';

    /**
     * The ID of the service.
     *
     * @var int|null
     */
    public ?int $service_id = null;

    /**
     * The type of rule.
     *
     * @var string
     */
    public string $rule_type = 'failure_count';

    /**
     * The queue name.
     *
     * @var string
     */
    public string $queue = '';

    /**
     * The type of job.
     *
     * @var string
     */
    public string $job_type = '';

    /**
     * The threshold count.
     *
     * @var int
     */
    public int $thresholdCount = 5;

    /**
     * The threshold minutes.
     *
     * @var int
     */
    public int $thresholdMinutes = 15;

    /**
     * The threshold seconds.
     *
     * @var float
     */
    public float $thresholdSeconds = 60;

    /**
     * Whether the alert is enabled.
     *
     * @var bool
     */
    public bool $enabled = true;

    /**
     * The IDs of the notification providers.
     *
     * @var array<int>
     */
    public array $provider_ids = [];

    /**
     * Minimum minutes between notifications (throttle). 0 = send on every trigger.
     *
     * @var string
     */
    public string $email_interval_minutes = '5';

    /**
     * Get the rule types.
     *
     * @return array<string, string>
     */
    public static function getRuleTypes(): array {
        return [
            'job_specific_failure' => 'Job failed (any)',
            'job_type_failure' => 'Job type failed',
            'failure_count' => 'Failure count in window',
            'avg_execution_time' => 'Avg execution time exceeded',
            'queue_blocked' => 'Queue blocked',
            'worker_offline' => 'Worker offline',
            'supervisor_offline' => 'Supervisor offline',
        ];
    }

    /**
     * Mount the alert form.
     *
     * @param Alert|null $alert
     * @return void
     */
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
            $interval = $this->alert->email_interval_minutes;
            $this->email_interval_minutes = $interval !== null ? (string) $interval : '5';
        }
    }

    /**
     * Save the alert.
     *
     * @return void
     */
    public function save(): void {
        $this->validateRuleType();
        $this->validate([
            'provider_ids' => 'required|array|min:1',
            'provider_ids.*' => 'integer|exists:notification_providers,id',
            'email_interval_minutes' => 'required|integer|min:0|max:1440',
        ]);

        $threshold = $this->buildThreshold();

        $data = [
            'name' => $this->name ?: null,
            'service_id' => $this->service_id ?: null,
            'rule_type' => $this->rule_type,
            'threshold' => $threshold,
            'queue' => $this->queue ?: null,
            'job_type' => $this->job_type ?: null,
            'notification_channels' => [],
            'enabled' => $this->enabled,
            'email_interval_minutes' => (int) $this->email_interval_minutes,
        ];

        if ($this->alert) {
            $this->alert->update($data);
            $this->alert->notificationProviders()->sync($this->provider_ids);
            $this->dispatch('toast', type: 'success', message: 'Alert updated.');
        } else {
            $alert = Alert::create($data);
            $alert->notificationProviders()->sync($this->provider_ids);
            $this->dispatch('toast', type: 'success', message: 'Alert created.');
        }

        $this->redirect(route('horizon.alerts.index'), navigate: true);
    }

    /**
     * Validate the rule type.
     *
     * @return void
     */
    private function validateRuleType(): void {
        $rules = [
            'rule_type' => 'required|in:job_specific_failure,job_type_failure,failure_count,avg_execution_time,queue_blocked,worker_offline,supervisor_offline',
            'service_id' => 'nullable|exists:services,id',
            'queue' => 'nullable|string|max:255',
            'job_type' => 'nullable|string|max:255',
            'thresholdCount' => 'nullable|integer|min:1',
            'thresholdMinutes' => 'nullable|integer|min:1',
            'thresholdSeconds' => 'nullable|numeric|min:0.1',
        ];

        if ($this->rule_type === 'job_type_failure') {
            $rules['job_type'] = 'required|string|max:255';
        }
        if (\in_array($this->rule_type, ['failure_count', 'avg_execution_time', 'queue_blocked', 'worker_offline', 'supervisor_offline'], true)) {
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

    /**
     * Build the threshold.
     *
     * @return array<string, int|float>
     */
    private function buildThreshold(): array {
        $t = [];
        if (\in_array($this->rule_type, ['failure_count', 'avg_execution_time', 'queue_blocked', 'worker_offline', 'supervisor_offline'], true)) {
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

    /**
     * Render the alert form.
     *
     * @return View
     */
    public function render(): View {
        $services = Service::orderBy('name')->get();
        $providers = NotificationProvider::orderBy('type')->orderBy('name')->get();
        $header = $this->alert ? 'Edit alert' : 'New alert';

        return \view('livewire.horizon.alert-form', [
            'services' => $services,
            'providers' => $providers,
            'ruleTypes' => self::getRuleTypes(),
        ])->layout('layouts.app', ['header' => 'Horizon Hub – ' . $header]);
    }
}
