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

    public function test_income_can_be_created_for_all_sources_at_once(): void
    {
        $user = User::factory()->create();
        $cash = IncomeSource::create(['name' => 'Cash', 'sort_order' => 1, 'is_active' => true]);
        $card = IncomeSource::create(['name' => 'Card', 'sort_order' => 2, 'is_active' => true]);

        $response = $this->actingAs($user)->post(route('income.store'), [
            'income_date' => '2026-05-01',
            'amounts' => [
                $cash->id => '500.00',
                $card->id => '0',
            ],
            'note' => 'Day note',
        ]);

        $response->assertSessionHasNoErrors()
            ->assertRedirect(route('income.index', ['month' => '2026-05']));

        $this->assertDatabaseCount('incomes', 2);

        $this->assertDatabaseHas('incomes', [
            'income_date' => '2026-05-01',
            'income_source_id' => $cash->id,
            'amount' => '500.00',
            'note' => 'Day note',
            'created_by' => $user->id,
        ]);

        // Zero is a real value: the source still gets a row.
        $this->assertDatabaseHas('incomes', [
            'income_date' => '2026-05-01',
            'income_source_id' => $card->id,
            'amount' => '0.00',
        ]);
    }

    public function test_income_resubmit_for_same_date_updates_instead_of_duplicating(): void
    {
        $user = User::factory()->create();
        $source = IncomeSource::create(['name' => 'Sales', 'sort_order' => 1, 'is_active' => true]);

        $payload = fn (string $amount) => [
            'income_date' => '2026-05-01',
            'amounts' => [$source->id => $amount],
        ];

        $this->actingAs($user)->post(route('income.store'), $payload('500.00'));
        $response = $this->actingAs($user)->post(route('income.store'), $payload('750.00'));

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseCount('incomes', 1);
        $this->assertDatabaseHas('incomes', [
            'income_date' => '2026-05-01',
            'income_source_id' => $source->id,
            'amount' => '750.00',
        ]);
    }

    public function test_income_rejects_unknown_source(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('income.store'), [
            'income_date' => '2026-05-01',
            'amounts' => [4242 => '500.00'],
        ]);

        $response->assertSessionHasErrors('amounts');
        $this->assertDatabaseCount('incomes', 0);
    }

    public function test_income_create_grid_prefills_existing_amounts_for_the_date(): void
    {
        $user = User::factory()->create();
        $cash = IncomeSource::create(['name' => 'Cash', 'sort_order' => 1, 'is_active' => true]);
        $card = IncomeSource::create(['name' => 'Card', 'sort_order' => 2, 'is_active' => true]);

        $this->actingAs($user)->post(route('income.store'), [
            'income_date' => '2026-05-01',
            'amounts' => [$cash->id => '500.00', $card->id => '0'],
            'note' => 'Day note',
        ]);

        $response = $this->actingAs($user)->get(route('income.create', ['date' => '2026-05-01']));

        $response->assertOk()
            ->assertSee('Edit Income')
            ->assertSee('name="amounts['.$cash->id.']"', false)
            ->assertSee('value="500.00"', false)
            ->assertSee('Day note', false);
    }

    public function test_income_index_renders_zero_entries_and_row_actions(): void
    {
        $user = User::factory()->create();
        $source = IncomeSource::create(['name' => 'Cash', 'sort_order' => 1, 'is_active' => true]);

        $this->actingAs($user)->post(route('income.store'), [
            'income_date' => '2026-05-01',
            'amounts' => [$source->id => '0'],
        ]);

        $response = $this->actingAs($user)->get(route('income.index', ['month' => '2026-05']));

        // A day of all-zero rows is real data, so it must not fall into the empty state.
        $response->assertOk()
            ->assertDontSee('No income found for this month.')
            ->assertSee(route('income.create', ['date' => '2026-05-01']), false);
    }

    public function test_income_day_can_be_deleted(): void
    {
        $user = User::factory()->create();
        $source = IncomeSource::create(['name' => 'Sales', 'sort_order' => 1, 'is_active' => true]);

        $this->actingAs($user)->post(route('income.store'), [
            'income_date' => '2026-05-01',
            'amounts' => [$source->id => '500.00'],
        ]);

        $response = $this->actingAs($user)
            ->delete(route('income.day.destroy', ['date' => '2026-05-01']));

        $response->assertRedirect(route('income.index', ['month' => '2026-05']));
        $this->assertDatabaseCount('incomes', 0);
    }
}
