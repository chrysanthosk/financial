<?php

namespace Database\Factories;

use App\Models\ExpenseCategory;
use App\Models\ExpenseTemplate;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExpenseTemplate>
 */
class ExpenseTemplateFactory extends Factory
{
    protected $model = ExpenseTemplate::class;

    public function definition(): array
    {
        return [
            'name' => ucfirst($this->faker->words(2, true)),
            'payee_name' => $this->faker->company(),
            'expense_category_id' => ExpenseCategory::factory(),
            'payment_method_id' => PaymentMethod::factory(),
            'amount' => $this->faker->randomFloat(2, 1, 5000),
            'cheque_no' => null,
            'reason' => $this->faker->optional()->sentence(),
            'is_paid_default' => true,
            'auto_create' => false,
            'day_of_month' => $this->faker->numberBetween(1, 28),
            'is_active' => true,
            'last_generated_on' => null,
            'created_by' => User::factory(),
        ];
    }

    public function autoCreate(): static
    {
        return $this->state(fn () => ['auto_create' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
