<?php

namespace Database\Factories;

use App\Models\IncomeSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IncomeSource>
 */
class IncomeSourceFactory extends Factory
{
    protected $model = IncomeSource::class;

    public function definition(): array
    {
        return [
            'name' => ucfirst($this->faker->unique()->words(2, true)),
            'is_active' => true,
            'sort_order' => $this->faker->numberBetween(0, 100),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
