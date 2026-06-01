<?php

namespace App\Services\Alerts\Rules;

use App\Models\Alert;
use App\Services\Alerts\Rules\Contracts\AlertRuleStrategy;
use App\Services\Alerts\Rules\Strategies\NullRule;

final class AlertRuleStrategyRegistry
{
    /**
     * The null strategy.
     */
    private NullRule $nullStrategy;

    /**
     * The strategies.
     *
     * @var array<string, AlertRuleStrategy>
     */
    private array $strategies;

    /**
     * The constructor.
     *
     * @param NullRule $nullStrategy The null strategy.
     */
    public function __construct(NullRule $nullStrategy)
    {
        $this->nullStrategy = $nullStrategy;

        foreach (Alert::getProviders() as $ruleType => $strategyClass) {
            $this->strategies[$ruleType] = app($strategyClass);
        }
    }

    /**
     * Resolve the strategy for the given rule type.
     *
     * @param string $ruleType The rule type.
     */
    public function resolve(string $ruleType): AlertRuleStrategy
    {
        return $this->strategies[$ruleType] ?? $this->nullStrategy;
    }
}
