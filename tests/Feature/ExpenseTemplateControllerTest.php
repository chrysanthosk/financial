<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ExpenseTemplate;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseTemplateControllerTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        $cat = ExpenseCategory::factory()->create();
        $method = PaymentMethod::factory()->create();

        return array_merge([
            'name' => 'Rent',
            'payee_name' => 'Landlord Ltd',
            'expense_category_id' => $cat->id,
            'payment_method_id' => $method->id,
            'amount' => '1200.00',
            'day_of_month' => 1,
            'auto_create' => '1',
            'is_active' => '1',
            'is_paid_default' => '1',
        ], $overrides);
    }

    public function test_index_lists_templates(): void
    {
        $tpl = ExpenseTemplate::factory()->create(['name' => 'Insurance']);

        $this->actingAs(User::factory()->create())
            ->get(route('expenses.recurring.index'))
            ->assertOk()
            ->assertSee('Insurance');
    }

    public function test_store_creates_template(): void
    {
        $user = User::factory()->create();
        $data = $this->payload();

        $response = $this->actingAs($user)->post(route('expenses.recurring.store'), $data);

        $response->assertSessionHasNoErrors()->assertRedirect(route('expenses.recurring.index'));
        $this->assertDatabaseHas('expense_templates', [
            'name' => 'Rent',
            'payee_name' => 'Landlord Ltd',
            'amount' => '1200.00',
            'auto_create' => true,
            'created_by' => $user->id,
        ]);
    }

    public function test_store_rejects_unknown_category(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->post(route('expenses.recurring.store'), $this->payload(['expense_category_id' => 9999]));

        $response->assertSessionHasErrors('expense_category_id');
        $this->assertDatabaseCount('expense_templates', 0);
    }

    public function test_store_rejects_day_of_month_above_28(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->post(route('expenses.recurring.store'), $this->payload(['day_of_month' => 31]));

        $response->assertSessionHasErrors('day_of_month');
    }

    public function test_update_modifies_template(): void
    {
        $tpl = ExpenseTemplate::factory()->create();

        $response = $this->actingAs(User::factory()->create())
            ->put(route('expenses.recurring.update', $tpl), $this->payload([
                'name' => 'Updated Name',
                'amount' => '999.00',
            ]));

        $response->assertSessionHasNoErrors()->assertRedirect(route('expenses.recurring.index'));
        $this->assertDatabaseHas('expense_templates', [
            'id' => $tpl->id,
            'name' => 'Updated Name',
            'amount' => '999.00',
        ]);
    }

    public function test_destroy_deletes_template(): void
    {
        $tpl = ExpenseTemplate::factory()->create();

        $this->actingAs(User::factory()->create())
            ->delete(route('expenses.recurring.destroy', $tpl))
            ->assertRedirect(route('expenses.recurring.index'));

        $this->assertDatabaseMissing('expense_templates', ['id' => $tpl->id]);
    }

    public function test_insert_action_creates_expense_for_today(): void
    {
        $user = User::factory()->create();
        $tpl = ExpenseTemplate::factory()->create(['is_active' => true]);

        $response = $this->actingAs($user)->post(route('expenses.recurring.insert', $tpl));

        $response->assertRedirect(route('expenses.index'));
        $this->assertDatabaseHas('expenses', [
            'payee_name' => $tpl->payee_name,
            'created_by' => $user->id,
        ]);
        $this->assertSame(
            now()->toDateString(),
            Expense::firstWhere('payee_name', $tpl->payee_name)->expense_date->toDateString()
        );
    }

    public function test_insert_rejects_inactive_template(): void
    {
        $tpl = ExpenseTemplate::factory()->inactive()->create();

        $this->actingAs(User::factory()->create())
            ->from(route('expenses.recurring.index'))
            ->post(route('expenses.recurring.insert', $tpl))
            ->assertSessionHasErrors('template');

        $this->assertDatabaseCount('expenses', 0);
    }
}
