<?php

namespace Database\Factories;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    public function definition(): array
    {
        return [
            'expense_date' => $this->faker->dateTimeBetween('-2 years', 'now')->format('Y-m-d'),
            'payee_name' => $this->faker->company(),
            'expense_category_id' => ExpenseCategory::factory(),
            'payment_method_id' => PaymentMethod::factory(),
            'amount' => $this->faker->randomFloat(2, 1, 5000),
            'cheque_no' => $this->faker->optional()->numerify('CHQ-#####'),
            'reason' => $this->faker->optional()->sentence(),
            'is_paid' => true,
            'created_by' => User::factory(),
        ];
    }

    public function unpaid(): static
    {
        return $this->state(fn () => ['is_paid' => false]);
    }
}
