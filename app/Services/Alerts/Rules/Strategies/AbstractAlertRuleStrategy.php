<?php

namespace App\Services\Alerts\Rules\Strategies;

use App\Services\Alerts\Rules\Contracts\AlertRuleStrategy;
use App\Support\Alerts\AlertRuleEvaluation;

abstract class AbstractAlertRuleStrategy implements AlertRuleStrategy
{
    /**
     * The evaluation support.
     */
    protected ?AlertRuleEvaluation $support = null;

    /**
     * The constructor.
     *
     * @param AlertRuleEvaluation|null $support The evaluation support.
     */
    public function __construct(?AlertRuleEvaluation $support = null)
    {
        $this->support = $support;
    }

    /**
     * Build the not-triggered result payload.
     *
     * @return array{triggered: bool, job_uuids: array<int, string>}
     */
    protected function notTriggered(): array
    {
        return ['triggered' => false, 'job_uuids' => []];
    }
}
