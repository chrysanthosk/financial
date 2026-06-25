<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmployeeIncome;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeIncome>
 */
class EmployeeIncomeFactory extends Factory
{
    protected $model = EmployeeIncome::class;

    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'month' => $this->faker->numberBetween(1, 12),
            'year' => $this->faker->numberBetween(2020, 2030),
            'total_amount' => $this->faker->randomFloat(2, 0, 10000),
        ];
    }
}
