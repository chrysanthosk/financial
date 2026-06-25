<?php

namespace Database\Factories;

use App\Models\Income;
use App\Models\IncomeSource;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Income>
 *
 * Note: incomes has a unique constraint on (income_date, income_source_id).
 * The default uses a random date over a wide window; for many rows on a
 * single source use a date sequence to avoid collisions.
 */
class IncomeFactory extends Factory
{
    protected $model = Income::class;

    public function definition(): array
    {
        return [
            'income_date' => $this->faker->dateTimeBetween('-2 years', 'now')->format('Y-m-d'),
            'amount' => $this->faker->randomFloat(2, 1, 5000),
            'income_source_id' => IncomeSource::factory(),
            'note' => $this->faker->optional()->sentence(),
            'created_by' => User::factory(),
        ];
    }
}
