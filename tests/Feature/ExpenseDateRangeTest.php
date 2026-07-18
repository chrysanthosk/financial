<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\IncomeSource;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExpenseDateRangeTest extends TestCase
{
    use RefreshDatabase;

    private function makeExpense(string $date, string $amount): Expense
    {
        return Expense::create([
            'expense_date' => $date,
            'payee_name' => 'Acme Ltd',
            'expense_category_id' => ExpenseCategory::firstOrCreate(
                ['name' => 'Office'], ['sort_order' => 1, 'is_active' => true]
            )->id,
            'payment_method_id' => PaymentMethod::firstOrCreate(
                ['name' => 'Cash'], ['sort_order' => 1, 'is_active' => true]
            )->id,
            'amount' => $amount,
            'created_by' => User::factory()->create()->id,
        ]);
    }

    public function test_expense_date_is_persisted_without_a_time_component(): void
    {
        $this->makeExpense('2025-01-31', '100.00');

        // A bare 'date' cast writes "2025-01-31 00:00:00" on SQLite, which then
        // fails every inclusive "<= Y-m-d" comparison in the app.
        $this->assertSame('2025-01-31', (string) DB::table('expenses')->value('expense_date'));
    }

    public function test_expense_on_the_last_day_of_a_range_is_included_in_totals(): void
    {
        $this->makeExpense('2025-01-15', '100.00');
        $this->makeExpense('2025-01-31', '75.66');

        $total = Expense::whereBetween('expense_date', ['2025-01-01', '2025-01-31'])->sum('amount');

        $this->assertSame('175.66', number_format((float) $total, 2, '.', ''));
    }

    public function test_month_total_is_correct_beyond_the_first_page(): void
    {
        $user = User::factory()->create();

        for ($i = 1; $i <= 25; $i++) {
            $this->makeExpense('2025-01-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT), '10.00');
        }

        // paginate() applies limit/offset to the shared builder; a sum taken
        // afterwards returns 0 from page 2 onwards.
        $response = $this->actingAs($user)->get(route('expenses.index', [
            'month' => '2025-01',
            'page' => 2,
        ]));

        $response->assertOk();
        $this->assertSame(250.0, (float) $response->viewData('monthTotal'));
    }

    public function test_expense_on_the_first_day_of_the_month_is_listed_and_totalled(): void
    {
        $user = User::factory()->create();
        $this->makeExpense('2025-01-01', '10.00');
        $this->makeExpense('2025-01-15', '10.00');

        // The listing compares against the range bounds directly, so a
        // datetime bound would exclude the date-only first-of-month row.
        $response = $this->actingAs($user)->get(route('expenses.index', ['month' => '2025-01']));

        $response->assertOk();
        $this->assertSame(20.0, (float) $response->viewData('monthTotal'));
        $this->assertCount(2, $response->viewData('expenses'));
    }

    public function test_expense_date_year_is_bounded(): void
    {
        $user = User::factory()->create();
        $cat = ExpenseCategory::create(['name' => 'Office', 'sort_order' => 1, 'is_active' => true]);
        $method = PaymentMethod::create(['name' => 'Cash', 'sort_order' => 1, 'is_active' => true]);

        // "0205-02-10" is a plausible typo for 2025 and would skew all-time reports.
        $response = $this->actingAs($user)->post(route('expenses.store'), [
            'expense_date' => '0205-02-10',
            'payee_name' => 'Acme Ltd',
            'expense_category_id' => $cat->id,
            'payment_method_id' => $method->id,
            'amount' => '123.45',
        ]);

        $response->assertSessionHasErrors('expense_date');
        $this->assertDatabaseCount('expenses', 0);
    }

    public function test_income_date_year_is_bounded(): void
    {
        $user = User::factory()->create();
        $source = IncomeSource::create(['name' => 'Cash', 'sort_order' => 1, 'is_active' => true]);

        $response = $this->actingAs($user)->post(route('income.store'), [
            'income_date' => '0205-02-10',
            'amounts' => [$source->id => '100.00'],
        ]);

        $response->assertSessionHasErrors('income_date');
        $this->assertDatabaseCount('incomes', 0);
    }
}
