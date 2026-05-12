<?php

namespace Tests\Unit;

use App\Models\Alert;
use App\Models\AlertLog;
use App\Models\NotificationProvider;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelAndProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_alert_service_scope_and_relations(): void
    {
        $s1 = Service::query()->create(['name' => 'A', 'base_url' => 'https://a.test', 'status' => 'online']);
        $s2 = Service::query()->create(['name' => 'B', 'base_url' => 'https://b.test', 'status' => 'online']);
        $alert = Alert::query()->create([
            'name' => 'z',
            'service_ids' => [$s1->id, $s2->id],
            'rule_type' => Alert::RULE_FAILURE_COUNT,
            'enabled' => true,
        ]);

        $this->assertSame([$s1->id, $s2->id], $alert->service_ids);
        $this->assertTrue($alert->appliesToServiceId($s1->id));
        $this->assertFalse($alert->appliesToServiceId(999));

        $globalAlert = Alert::query()->create([
            'name' => 'global',
            'service_ids' => [],
            'rule_type' => Alert::RULE_FAILURE_COUNT,
            'enabled' => true,
        ]);
        $this->assertTrue($globalAlert->appliesToServiceId($s1->id));

        $defaultScopeAlert = new Alert([
            'name' => 'defaults',
            'rule_type' => Alert::RULE_FAILURE_COUNT,
            'enabled' => true,
        ]);
        $this->assertSame([], $defaultScopeAlert->service_ids);
    }

    public function test_notification_provider_helpers_and_alert_log_casts(): void
    {
        $email = NotificationProvider::query()->create([
            'name' => 'mail',
            'type' => NotificationProvider::TYPE_EMAIL,
            'config' => ['to' => ['  a@example.com ', '']],
        ]);
        $slack = NotificationProvider::query()->create([
            'name' => 'slack',
            'type' => NotificationProvider::TYPE_SLACK,
            'config' => ['webhook_url' => 'https://hooks.slack.test'],
        ]);

        $this->assertSame(['a@example.com'], $email->getToEmails());
        $this->assertSame([], $slack->getToEmails());
        $this->assertSame('https://hooks.slack.test', $slack->getWebhookUrl());

        $alert = Alert::query()->create(['name' => 'a', 'rule_type' => Alert::RULE_FAILURE_COUNT, 'enabled' => true]);
        $service = Service::query()->create(['name' => 'svc', 'base_url' => 'https://x.test', 'status' => 'online']);
        $log = AlertLog::query()->create([
            'alert_id' => $alert->id,
            'service_id' => $service->id,
            'trigger_count' => 1,
            'job_uuids' => ['u1'],
            'status' => 'sent',
            'sent_at' => now()->toDateTimeString(),
        ]);
        $this->assertIsArray($log->job_uuids);
        $this->assertNotNull($log->alert);
        $this->assertNotNull($log->service);
    }
}
