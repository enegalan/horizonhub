<?php

namespace Database\Factories;

use App\Models\Alert;
use App\Services\Alerts\Rules\Strategies\FailureCount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Alert>
 */
class AlertFactory extends Factory
{
    protected $model = Alert::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'service_ids' => [],
            'rule_type' => FailureCount::type(),
            'threshold' => ['count' => 1, 'minutes' => 5],
            'enabled' => true,
            'email_interval_minutes' => 0,
        ];
    }
}
