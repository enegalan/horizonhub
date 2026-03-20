<?php

namespace App\Services\Alerts\Rules\Strategies;

use App\Models\Alert;
use App\Models\Service;
use App\Services\Alerts\Rules\AlertRuleEvaluationSupport;
use App\Services\Alerts\Rules\Contracts\AlertRuleStrategyInterface;
use App\Services\HorizonApiProxyService;
use Carbon\Carbon;

final class QueueBlockedAlertRuleStrategy implements AlertRuleStrategyInterface {

    /**
     * The evaluation support.
     *
     * @var AlertRuleEvaluationSupport
     */
    private AlertRuleEvaluationSupport $support;

    /**
     * The Horizon API proxy service.
     *
     * @var HorizonApiProxyService
     */

    /**
     * Construct the strategy.
     *
     * @param AlertRuleEvaluationSupport $support
     * @param HorizonApiProxyService $horizonApi
     */
    private HorizonApiProxyService $horizonApi;

    /**
     * Construct the strategy.
     *
     * @param AlertRuleEvaluationSupport $support
     * @param HorizonApiProxyService $horizonApi
     */
    public function __construct(
        AlertRuleEvaluationSupport $support,
        HorizonApiProxyService $horizonApi,
    ) {
        $this->support = $support;
        $this->horizonApi = $horizonApi;
    }

    /**
     * Evaluate the rule and return whether it triggered plus triggering job UUIDs (if applicable).
     *
     * @param Alert $alert
     * @param int $serviceId
     * @param string|null $jobUuid
     * @return array{triggered: bool, job_uuids: array<int, string>}
     */
    public function evaluateWithTriggeringJobs(Alert $alert, int $serviceId, ?string $jobUuid): array {
        return [
            'triggered' => $this->private__evaluateQueueBlocked($alert, $serviceId),
            'job_uuids' => [],
        ];
    }

    /**
     * Evaluate the queue blocked.
     *
     * @param Alert $alert
     * @param int $serviceId
     * @return bool
     */
    private function private__evaluateQueueBlocked(Alert $alert, int $serviceId): bool {
        $threshold = $alert->threshold ?? [];
        $minutes = (int) ($threshold['minutes'] ?? 30);

        $service = Service::find($serviceId);
        if (! $service || ! $service->base_url) {
            return false;
        }

        $response = $this->horizonApi->getCompletedJobs($service, ['starting_at' => 0]);
        $data = $response['data'] ?? null;

        if (! ($response['success'] ?? false) || ! \is_array($data)) {
            return false;
        }

        $jobs = collect($data['jobs'] ?? []);

        $queuePatterns = $this->support->resolveQueuePatterns($alert);
        if ($queuePatterns !== []) {
            $jobs = $jobs->filter(function ($job) use ($alert) {
                return \is_array($job) && $this->support->jobMatchesQueuePatterns($alert, $job);
            });
        }

        $lastProcessed = $jobs->map(function ($job) {
            if (! \is_array($job)) {
                return null;
            }
            $completedRaw = $job['completed_at'] ?? null;
            if (! \is_string($completedRaw) || $completedRaw === '') {
                return null;
            }
            try {
                return Carbon::parse($completedRaw);
            } catch (\Throwable $e) {
                return null;
            }
        })->filter()->sort()->last();

        if (! $lastProcessed) {
            return false;
        }

        return $lastProcessed->copy()->addMinutes($minutes)->isPast();
    }
}
