<?php

namespace Tests\Unit;

use App\Models\Alert;
use App\Services\Alerts\AlertUpsertService;
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
        $this->assertContains(Alert::RULE_FAILURE_COUNT, $keys);
    }
}
