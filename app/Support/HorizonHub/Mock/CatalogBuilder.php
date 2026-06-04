<?php

namespace App\Support\HorizonHub\Mock;

use App\Services\Alerts\Rules\Strategies\AvgExecutionTime;
use App\Services\Alerts\Rules\Strategies\FailureCount;
use App\Services\Alerts\Rules\Strategies\HorizonOffline;
use App\Services\Alerts\Rules\Strategies\QueueBlocked;
use App\Services\Alerts\Rules\Strategies\SupervisorOffline;
use App\Services\Alerts\Rules\Strategies\WorkerOffline;
use App\Services\Notifiers\DiscordNotifierService;
use App\Services\Notifiers\EmailNotifierService;
use App\Services\Notifiers\SlackNotifierService;
use Illuminate\Support\Carbon;

final class CatalogBuilder
{
    /**
     * @param array{service_count: int, provider_count: int, alert_count: int, alert_log_count: int} $volumes
     */
    public function __construct(
        private readonly array $volumes,
    ) {}

    public function build(): array
    {
        $serviceCount = $this->volumes['service_count'];
        $providerCount = $this->volumes['provider_count'];
        $alertCount = $this->volumes['alert_count'];
        $alertLogCount = $this->volumes['alert_log_count'];

        $now = now();
        $pinnedJobUuid = MockDataset::PINNED_JOB_UUID;

        $services = $this->private__canonical_services($now);
        $services = \array_merge($services, $this->private__generated_services($now, 4, $serviceCount));

        $providers = $this->private__canonical_providers();
        $providers = \array_merge($providers, $this->private__generated_providers($providerCount, \count($providers) + 1));

        $alerts = $this->private__canonical_alerts($now, $pinnedJobUuid);
        $alerts = \array_merge($alerts, $this->private__generated_alerts($now, $alertCount, \count($alerts) + 1, $serviceCount, \count($providers)));

        $alertLogs = $this->private__canonical_alert_logs($now, $pinnedJobUuid);
        $alertLogs = \array_merge($alertLogs, $this->private__generated_alert_logs(
            $now,
            $pinnedJobUuid,
            $alertLogCount,
            \count($alertLogs) + 1,
            $alerts,
            $services,
        ));

        $headers = [
            ['service_id' => 1, 'name' => 'X-Api-Key', 'value' => 'demo-billing-key'],
        ];
        $headers = \array_merge($headers, $this->private__generated_service_headers($services));

        $catalog = [
            'services' => $services,
            'service_headers' => $headers,
            'notification_providers' => $providers,
            'alerts' => $alerts,
            'alert_logs' => $alertLogs,
            'pinned_job_uuid' => $pinnedJobUuid,
        ];

        return $catalog;
    }

    private function private__alert_name(int $id, string $ruleType): string
    {
        $labels = [
            FailureCount::type() => 'Failure burst',
            HorizonOffline::type() => 'Horizon unreachable',
            QueueBlocked::type() => 'Queue stalled',
            WorkerOffline::type() => 'Worker missing',
            SupervisorOffline::type() => 'Supervisor down',
            AvgExecutionTime::type() => 'Slow jobs',
        ];

        return ($labels[$ruleType] ?? 'Alert') . " #{$id}";
    }

    private function private__canonical_alert_logs(Carbon $now, string $pinnedJobUuid): array
    {
        return [
            [
                'id' => 1,
                'alert_id' => 1,
                'service_id' => 1,
                'trigger_count' => 2,
                'job_uuids' => [$pinnedJobUuid],
                'status' => 'sent',
                'failure_message' => null,
                'sent_at' => $now->copy()->subHours(2),
            ],
            [
                'id' => 2,
                'alert_id' => 1,
                'service_id' => 1,
                'trigger_count' => 1,
                'job_uuids' => null,
                'status' => 'failed',
                'failure_message' => 'Webhook timeout',
                'sent_at' => $now->copy()->subHours(5),
            ],
            [
                'id' => 3,
                'alert_id' => 2,
                'service_id' => 3,
                'trigger_count' => 1,
                'job_uuids' => null,
                'status' => 'sent',
                'failure_message' => null,
                'sent_at' => $now->copy()->subHours(1),
            ],
            [
                'id' => 4,
                'alert_id' => 2,
                'service_id' => 3,
                'trigger_count' => 1,
                'job_uuids' => null,
                'status' => 'sent',
                'failure_message' => null,
                'sent_at' => $now->copy()->subDays(1),
            ],
            [
                'id' => 5,
                'alert_id' => 1,
                'service_id' => 1,
                'trigger_count' => 3,
                'job_uuids' => [$pinnedJobUuid, 'a1b2c3d4-e5f6-7890-abcd-ef1234567890'],
                'status' => 'sent',
                'failure_message' => null,
                'sent_at' => $now->copy()->subMinutes(30),
            ],
        ];
    }

    private function private__canonical_alerts(Carbon $now, string $pinnedJobUuid): array
    {
        return [
            [
                'id' => 1,
                'name' => 'Billing failures spike',
                'service_ids' => [1],
                'rule_type' => FailureCount::type(),
                'threshold' => ['count' => 5, 'minutes' => 15, 'queue_patterns' => ['billing'], 'job_patterns' => []],
                'enabled' => true,
                'email_interval_minutes' => 0,
                'provider_ids' => [1, 3],
                'created_at' => $now->copy()->subDays(10),
            ],
            [
                'id' => 2,
                'name' => 'Reporting Horizon offline',
                'service_ids' => [3],
                'rule_type' => HorizonOffline::type(),
                'threshold' => ['minutes' => 5],
                'enabled' => true,
                'email_interval_minutes' => 30,
                'provider_ids' => [2],
                'created_at' => $now->copy()->subDays(3),
            ],
        ];
    }

    private function private__canonical_providers(): array
    {
        return [
            [
                'id' => 1,
                'name' => 'Ops Slack',
                'type' => SlackNotifierService::type(),
                'config' => ['webhook_url' => 'https://hooks.slack.demo/example'],
            ],
            [
                'id' => 2,
                'name' => 'On-call Discord',
                'type' => DiscordNotifierService::type(),
                'config' => ['webhook_url' => 'https://discord.demo/webhooks/example'],
            ],
            [
                'id' => 3,
                'name' => 'Team email',
                'type' => EmailNotifierService::type(),
                'config' => ['to' => ['ops@demo.test', 'oncall@demo.test']],
            ],
        ];
    }

    private function private__canonical_services(Carbon $now): array
    {
        return [
            [
                'id' => 1,
                'name' => 'billing-api',
                'base_url' => 'https://billing.demo.test',
                'public_url' => 'https://billing.demo.test',
                'status' => 'online',
                'enabled' => true,
                'tags' => ['billing', 'production', 'payments'],
                'last_seen_at' => $now,
            ],
            [
                'id' => 2,
                'name' => 'notifications',
                'base_url' => 'https://notifications.demo.test',
                'public_url' => null,
                'status' => 'stand_by',
                'enabled' => true,
                'tags' => ['messaging', 'production'],
                'last_seen_at' => $now->copy()->subMinutes(3),
            ],
            [
                'id' => 3,
                'name' => 'reporting',
                'base_url' => 'https://reporting.demo.test',
                'public_url' => 'https://reporting.demo.test',
                'status' => 'offline',
                'enabled' => true,
                'tags' => ['analytics', 'batch'],
                'last_seen_at' => $now->copy()->subHours(2),
            ],
        ];
    }

    private function private__generated_alert_logs(
        Carbon $now,
        string $pinnedJobUuid,
        int $targetCount,
        int $firstId,
        array $alerts,
        array $services,
    ): array {
        $enabledServiceIds = [];

        foreach ($services as $service) {
            if (($service['enabled'] ?? true) === true) {
                $enabledServiceIds[] = (int) $service['id'];
            }
        }

        if ($enabledServiceIds === []) {
            return [];
        }

        $alertIds = \array_map(static fn (array $alert): int => (int) $alert['id'], $alerts);
        $failureMessages = [
            null,
            'Webhook timeout',
            'SMTP rejected',
            'Rate limited',
            'Invalid payload',
            'Connection reset',
        ];
        $logs = [];

        for ($id = $firstId; $id <= $targetCount; $id++) {
            $alertId = $alertIds[$id % \count($alertIds)];
            $serviceId = $enabledServiceIds[$id % \count($enabledServiceIds)];
            $status = $id % 7 === 0 ? 'failed' : 'sent';
            $triggerCount = 1 + ($id % 12);
            $jobUuids = null;

            if ($id % 5 === 0) {
                $jobUuids = [
                    $id % 3 === 0 ? $pinnedJobUuid : MockDataset::jobUuid($serviceId, $id),
                ];

                if ($id % 10 === 0) {
                    $jobUuids[] = MockDataset::jobUuid($serviceId, $id + 1000);
                }
            }

            $logs[] = [
                'id' => $id,
                'alert_id' => $alertId,
                'service_id' => $serviceId,
                'trigger_count' => $triggerCount,
                'job_uuids' => $jobUuids,
                'status' => $status,
                'failure_message' => $status === 'failed' ? $failureMessages[$id % \count($failureMessages)] : null,
                'sent_at' => $now->copy()->subMinutes($id % (60 * 24 * 45)),
            ];
        }

        return $logs;
    }

    private function private__generated_alerts(
        Carbon $now,
        int $targetCount,
        int $firstId,
        int $serviceCount,
        int $providerCount,
    ): array {
        $ruleTypes = [
            FailureCount::type(),
            HorizonOffline::type(),
            QueueBlocked::type(),
            WorkerOffline::type(),
            SupervisorOffline::type(),
            AvgExecutionTime::type(),
        ];
        $alerts = [];

        for ($id = $firstId; $id <= $targetCount; $id++) {
            $ruleType = $ruleTypes[$id % \count($ruleTypes)];
            $serviceIdCount = 1 + ($id % 4);
            $serviceIds = [];

            for ($s = 0; $s < $serviceIdCount; $s++) {
                $serviceIds[] = 1 + (($id * 11 + $s * 17) % $serviceCount);
            }

            $serviceIds = \array_values(\array_unique($serviceIds));
            $providerIds = [];

            for ($p = 0; $p < 1 + ($id % 3); $p++) {
                $providerIds[] = 1 + (($id + $p) % $providerCount);
            }

            $providerIds = \array_values(\array_unique($providerIds));
            $threshold = $this->private__threshold_for_rule($ruleType, $id);

            $alerts[] = [
                'id' => $id,
                'name' => $this->private__alert_name($id, $ruleType),
                'service_ids' => $serviceIds,
                'rule_type' => $ruleType,
                'threshold' => $threshold,
                'enabled' => $id % 13 !== 0,
                'email_interval_minutes' => [0, 5, 15, 30, 60][$id % 5],
                'provider_ids' => $providerIds,
                'created_at' => $now->copy()->subDays($id % 90)->subHours($id % 24),
            ];
        }

        return $alerts;
    }

    private function private__generated_providers(int $targetCount, int $firstId): array
    {
        $teams = ['Platform', 'Payments', 'Data', 'Growth', 'Infra', 'SRE', 'Support', 'Security'];
        $providers = [];
        $types = [
            SlackNotifierService::type(),
            DiscordNotifierService::type(),
            EmailNotifierService::type(),
        ];

        for ($id = $firstId; $id <= $targetCount; $id++) {
            $type = $types[$id % 3];
            $team = $teams[$id % \count($teams)];
            $config = match ($type) {
                SlackNotifierService::type() => ['webhook_url' => "https://hooks.slack.demo/{$id}"],
                DiscordNotifierService::type() => ['webhook_url' => "https://discord.demo/webhooks/{$id}"],
                default => ['to' => ["{$team}-{$id}@demo.test", "pager-{$id}@demo.test"]],
            };

            $providers[] = [
                'id' => $id,
                'name' => "{$team} {$type} #{$id}",
                'type' => $type,
                'config' => $config,
            ];
        }

        return $providers;
    }

    private function private__generated_service_headers(array $services): array
    {
        $headers = [];

        foreach ($services as $service) {
            $serviceId = (int) $service['id'];

            if ($serviceId <= 3 || $serviceId % 6 !== 0) {
                continue;
            }

            $headers[] = [
                'service_id' => $serviceId,
                'name' => 'Authorization',
                'value' => 'Bearer demo-token-' . $serviceId,
            ];

            if ($serviceId % 12 === 0) {
                $headers[] = [
                    'service_id' => $serviceId,
                    'name' => 'X-Trace-Id',
                    'value' => 'trace-' . $serviceId,
                ];
            }
        }

        return $headers;
    }

    private function private__generated_services(Carbon $now, int $firstId, int $lastId): array
    {
        $prefixes = [
            'api-gateway', 'auth', 'catalog', 'checkout', 'crm', 'cdn', 'etl', 'inventory',
            'legacy-bridge', 'media', 'ml-inference', 'orders', 'payments', 'recommendations',
            'search', 'shipping', 'subscriptions', 'tax', 'users', 'webhooks', 'worker',
            'import', 'export', 'sync', 'scheduler', 'fraud', 'loyalty', 'pricing', 'warehouse',
        ];
        $regions = ['eu', 'us', 'apac', 'latam'];
        $envs = ['prod', 'staging', 'sandbox'];
        $tagPool = [
            'production', 'staging', 'analytics', 'messaging', 'batch', 'realtime',
            'pci', 'internal', 'public', 'legacy', 'critical', 'experimental', 'on-call',
            'payments', 'fraud', 'search', 'cdn',
        ];
        $statuses = ['online', 'online', 'online', 'online', 'online', 'stand_by', 'stand_by', 'offline'];
        $services = [];

        for ($id = $firstId; $id <= $lastId; $id++) {
            $prefix = $prefixes[($id - 4) % \count($prefixes)];
            $region = $regions[$id % \count($regions)];
            $env = $envs[($id >> 2) % \count($envs)];
            $status = $statuses[$id % \count($statuses)];
            $enabled = $id % 19 !== 0;
            $tagCount = 1 + ($id % 3);
            $tags = [];

            for ($t = 0; $t < $tagCount; $t++) {
                $tags[] = $tagPool[($id + $t * 7) % \count($tagPool)];
            }

            $tags = \array_values(\array_unique($tags));
            $lastSeen = match ($status) {
                'online' => $now->copy()->subSeconds($id % 120),
                'stand_by' => $now->copy()->subMinutes(2 + ($id % 45)),
                default => $now->copy()->subHours(1 + ($id % 72)),
            };

            $services[] = [
                'id' => $id,
                'name' => "{$prefix}-{$region}-{$env}-{$id}",
                'base_url' => "https://{$prefix}-{$id}.demo.test",
                'public_url' => $id % 4 === 0 ? "https://{$prefix}-{$id}.demo.test" : null,
                'status' => $status,
                'enabled' => $enabled,
                'tags' => $tags,
                'last_seen_at' => $lastSeen,
            ];
        }

        return $services;
    }

    private function private__threshold_for_rule(string $ruleType, int $id): array
    {
        return match ($ruleType) {
            FailureCount::type() => [
                'count' => 3 + ($id % 20),
                'minutes' => 5 + ($id % 55),
                'queue_patterns' => $id % 2 === 0 ? ['default', 'high'] : [],
                'job_patterns' => $id % 3 === 0 ? ['Process', 'Sync'] : [],
            ],
            AvgExecutionTime::type() => [
                'seconds' => 10 + ($id % 120),
                'minutes' => 5 + ($id % 30),
            ],
            default => ['minutes' => 1 + ($id % 45)],
        };
    }
}
