<?php

namespace Tests\Unit;

use Tests\TestCase;

class AlertPatternListPartialTest extends TestCase
{
    public function test_pattern_list_renders_all_wildcard_validation_errors(): void
    {
        $view = $this->withViewErrors([
            'job_patterns.0' => 'The job_patterns.0 field must not be greater than 255 characters.',
            'job_patterns.1' => 'The job_patterns.1 field must not be greater than 255 characters.',
        ])->view('horizon.alerts.partials.form.pattern-list', [
            'sectionTitle' => 'Job patterns',
            'errorKey' => 'job_patterns',
            'hint' => 'Match job class substrings.',
            'placeholder' => 'App\\Jobs\\Sync',
        ]);

        $view->assertSee('The job_patterns.0 field must not be greater than 255 characters.', false);
        $view->assertSee('The job_patterns.1 field must not be greater than 255 characters.', false);
        $this->assertSame(
            2,
            \substr_count((string) $view, 'text-xs text-destructive'),
        );
    }
}
