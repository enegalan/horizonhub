<?php

namespace Database\Factories;

use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    protected $model = Service::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->slug(2),
            'base_url' => 'https://' . $this->faker->unique()->domainName(),
            'public_url' => null,
            'status' => 'online',
            'enabled' => true,
            'last_seen_at' => now(),
        ];
    }
}
