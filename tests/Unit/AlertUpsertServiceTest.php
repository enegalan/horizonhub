<?php

namespace Tests\Unit;

use App\Models\Alert;
use App\Models\Service;
use App\Services\Alerts\AlertUpsertService;
use App\Services\Alerts\Rules\Strategies\FailureCount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlertUpsertServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_form_rule_types_exclude_removed_job_failure_variants(): void
    {
        $service = new AlertUpsertService;
        $vars = $service->buildFormViewVariables(new Alert);
        $keys = array_keys($vars['ruleTypes']);

        $this->assertNotContains('job_specific_failure', $keys);
        $this->assertNotContains('job_type_failure', $keys);
        $this->assertContains(FailureCount::type(), $keys);
    }

    public function test_form_services_include_disabled_services_still_in_alert_scope(): void
    {
        $enabled = Service::factory()->create(['name' => 'alpha', 'enabled' => true]);
        $disabled = Service::factory()->create(['name' => 'beta', 'enabled' => false]);
        $otherDisabled = Service::factory()->create(['name' => 'gamma', 'enabled' => false]);
        $alert = Alert::query()->create([
            'rule_type' => FailureCount::type(),
            'enabled' => true,
            'service_ids' => [$enabled->id, $disabled->id],
        ]);

        $services = (new AlertUpsertService)->buildFormViewVariables($alert)['services'];

        $this->assertSame(
            [$enabled->id, $disabled->id],
            $services->pluck('id')->all(),
        );
        $this->assertFalse($services->firstWhere('id', $disabled->id)->enabled);
        $this->assertNull($services->firstWhere('id', $otherDisabled->id));
    }
}
