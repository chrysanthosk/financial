<?php

namespace Tests\Feature;

use App\Models\ExpenseCategory;
use App\Models\IncomeSource;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseIncomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_expense_can_be_created_with_valid_data(): void
    {
        $user = User::factory()->create();
        $cat = ExpenseCategory::create(['name' => 'Office', 'sort_order' => 1, 'is_active' => true]);
        $method = PaymentMethod::create(['name' => 'Cash', 'sort_order' => 1, 'is_active' => true]);

        $response = $this->actingAs($user)->post(route('expenses.store'), [
            'expense_date' => '2026-05-01',
            'payee_name' => 'Acme Ltd',
            'expense_category_id' => $cat->id,
            'payment_method_id' => $method->id,
            'amount' => '123.45',
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect(route('expenses.index'));

        $this->assertDatabaseHas('expenses', [
            'payee_name' => 'Acme Ltd',
            'amount' => '123.45',
            'created_by' => $user->id,
            'is_paid' => false,
        ]);
    }

    public function test_expense_rejects_inactive_or_unknown_category(): void
    {
        $user = User::factory()->create();
        $method = PaymentMethod::create(['name' => 'Cash', 'sort_order' => 1, 'is_active' => true]);

        $response = $this->actingAs($user)->post(route('expenses.store'), [
            'expense_date' => '2026-05-01',
            'payee_name' => 'Acme Ltd',
            'expense_category_id' => 9999,
            'payment_method_id' => $method->id,
            'amount' => '10',
        ]);

        $response->assertSessionHasErrors('expense_category_id');
        $this->assertDatabaseCount('expenses', 0);
    }

    public function test_income_can_be_created_with_valid_data(): void
    {
        $user = User::factory()->create();
        $source = IncomeSource::create(['name' => 'Sales', 'sort_order' => 1, 'is_active' => true]);

        $response = $this->actingAs($user)->post(route('income.store'), [
            'income_date' => '2026-05-01',
            'income_source_id' => $source->id,
            'amount' => '500.00',
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect(route('income.index'));
        $this->assertDatabaseHas('incomes', [
            'income_source_id' => $source->id,
            'amount' => '500.00',
            'created_by' => $user->id,
        ]);
    }

    public function test_income_rejects_unknown_source(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('income.store'), [
            'income_date' => '2026-05-01',
            'income_source_id' => 4242,
            'amount' => '500.00',
        ]);

        $response->assertSessionHasErrors('income_source_id');
        $this->assertDatabaseCount('incomes', 0);
    }
}
