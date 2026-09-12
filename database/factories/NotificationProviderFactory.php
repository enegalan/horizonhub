<?php

namespace Database\Factories;

use App\Enums\NotificationProviderType;
use App\Models\NotificationProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationProvider>
 */
class NotificationProviderFactory extends Factory
{
    protected $model = NotificationProvider::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true),
            'type' => NotificationProviderType::Email->value,
            'config' => ['to' => [$this->faker->safeEmail()]],
        ];
    }
}
