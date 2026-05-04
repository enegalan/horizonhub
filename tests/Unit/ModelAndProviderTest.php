<?php

namespace Tests\Unit;

use App\Models\Alert;
use App\Models\AlertLog;
use App\Models\NotificationProvider;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class ModelAndProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_alert_scoped_service_helpers_and_relations(): void
    {
        $s1 = Service::query()->create(['name' => 'A', 'base_url' => 'https://a.test', 'status' => 'online']);
        $s2 = Service::query()->create(['name' => 'B', 'base_url' => 'https://b.test', 'status' => 'online']);
        $alert = Alert::query()->create([
            'name' => 'z',
            'service_ids' => [$s1->id, '0', $s2->id, 'foo', $s1->id],
            'rule_type' => Alert::RULE_FAILURE_COUNT,
            'enabled' => true,
        ]);

        $this->assertSame([$s1->id, $s2->id], $alert->scopedServiceIds());
        $this->assertTrue($alert->appliesToServiceId($s1->id));
        $this->assertFalse($alert->appliesToServiceId(999));
        $this->assertSame(['A', 'B'], $alert->scopedServiceNames());
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

    public function test_user_casts_and_horizon_stream_rate_limiter_registration(): void
    {
        $user = User::factory()->create();
        $this->assertNotSame('secret', $user->password);

        $request = Request::create('/horizon');
        $request->setUserResolver(static function () use ($user) {
            return $user;
        });

        $limiter = RateLimiter::limiter('horizon-stream');
        $this->assertNotNull($limiter);
        $limit = $limiter($request);
        $this->assertNotNull($limit);
    }
}
