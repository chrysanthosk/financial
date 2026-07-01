<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeIncome;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Income;
use App\Models\IncomeSource;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Characterization tests: they lock the current numeric output of the
 * report endpoints so the ReportingService extraction cannot change behavior.
 */
class ReportsTest extends TestCase
{
    use RefreshDatabase;

    private function seedData(): User
    {
        $user = User::factory()->create();
        $source = IncomeSource::create(['name' => 'Sales', 'sort_order' => 1, 'is_active' => true]);
        $cat = ExpenseCategory::create(['name' => 'Office', 'sort_order' => 1, 'is_active' => true]);
        $method = PaymentMethod::create(['name' => 'Cash', 'sort_order' => 1, 'is_active' => true]);

        Income::create(['income_date' => '2026-01-15', 'income_source_id' => $source->id, 'amount' => '100.00', 'created_by' => $user->id]);
        Income::create(['income_date' => '2026-03-10', 'income_source_id' => $source->id, 'amount' => '200.00', 'created_by' => $user->id]);

        Expense::create(['expense_date' => '2026-01-20', 'payee_name' => 'A', 'expense_category_id' => $cat->id, 'payment_method_id' => $method->id, 'amount' => '50.00', 'created_by' => $user->id]);
        Expense::create(['expense_date' => '2026-02-05', 'payee_name' => 'B', 'expense_category_id' => $cat->id, 'payment_method_id' => $method->id, 'amount' => '30.00', 'created_by' => $user->id]);

        return $user;
    }

    public function test_monthly_profit_report(): void
    {
        $user = $this->seedData();

        $response = $this->actingAs($user)->get(route('reports.monthly_profit', ['year' => 2026]));
        $response->assertOk();

        // Jan(0), Feb(1), Mar(2) ...
        $this->assertSame([100.0, 0.0, 200.0], array_slice($response->viewData('incomeByMonth'), 0, 3));
        $this->assertSame([50.0, 30.0, 0.0], array_slice($response->viewData('expenseByMonth'), 0, 3));
        $this->assertSame([50.0, -30.0, 200.0], array_slice($response->viewData('profitByMonth'), 0, 3));
        $this->assertSame(300.0, $response->viewData('totalIncome'));
        $this->assertSame(80.0, $response->viewData('totalExpenses'));
        $this->assertSame(220.0, $response->viewData('totalProfit'));
        $this->assertSame('Jan', $response->viewData('labels')[0]);
        $this->assertSame('Dec', $response->viewData('labels')[11]);
    }

    public function test_revenue_by_year_report(): void
    {
        $user = $this->seedData();

        // Add prior-year activity so the multi-year buckets are exercised.
        $source = IncomeSource::where('name', 'Sales')->firstOrFail();
        $cat = ExpenseCategory::where('name', 'Office')->firstOrFail();
        $method = PaymentMethod::where('name', 'Cash')->firstOrFail();
        Income::create(['income_date' => '2025-06-01', 'income_source_id' => $source->id, 'amount' => '400.00', 'created_by' => $user->id]);
        Expense::create(['expense_date' => '2025-07-01', 'payee_name' => 'C', 'expense_category_id' => $cat->id, 'payment_method_id' => $method->id, 'amount' => '100.00', 'created_by' => $user->id]);

        $response = $this->actingAs($user)->get(route('reports.revenue_by_year', ['year' => 2026, 'years' => 2]));
        $response->assertOk();

        $this->assertSame(['2025', '2026'], $response->viewData('labels'));
        $this->assertSame([400.0, 300.0], $response->viewData('incomeByYear'));
        $this->assertSame([100.0, 80.0], $response->viewData('expenseByYear'));
        $this->assertSame([300.0, 220.0], $response->viewData('profitByYear'));
        $this->assertSame(700.0, $response->viewData('totalIncome'));
        $this->assertSame(180.0, $response->viewData('totalExpenses'));
        $this->assertSame(520.0, $response->viewData('totalProfit'));

        $rows = $response->viewData('rows');
        $this->assertSame(2025, $rows[0]['year']);
        $this->assertSame(2026, $rows[1]['year']);
    }

    /**
     * Adds a prior year (2025) of activity on top of seedData() for the
     * multi-year / cash-flow reports.
     */
    private function seedPriorYear(User $user): void
    {
        $source = IncomeSource::where('name', 'Sales')->firstOrFail();
        $cat = ExpenseCategory::where('name', 'Office')->firstOrFail();
        $method = PaymentMethod::where('name', 'Cash')->firstOrFail();
        Income::create(['income_date' => '2025-06-01', 'income_source_id' => $source->id, 'amount' => '400.00', 'created_by' => $user->id]);
        Expense::create(['expense_date' => '2025-07-01', 'payee_name' => 'C', 'expense_category_id' => $cat->id, 'payment_method_id' => $method->id, 'amount' => '100.00', 'created_by' => $user->id]);
    }

    public function test_profit_margin_by_year_report(): void
    {
        $user = $this->seedData();
        $this->seedPriorYear($user);

        $response = $this->actingAs($user)->get(route('reports.profit_margin_by_year', ['year' => 2026, 'years' => 2]));
        $response->assertOk();

        $this->assertSame(['2025', '2026'], $response->viewData('labels'));
        // 2025: 300/400 = 75.0 ; 2026: 220/300 = 73.3
        $this->assertSame([75.0, 73.3], $response->viewData('marginByYear'));
    }

    public function test_cash_flow_report(): void
    {
        $user = $this->seedData();
        $this->seedPriorYear($user);

        $response = $this->actingAs($user)->get(route('reports.cash_flow', ['year' => 2026]));
        $response->assertOk();

        // Opening = prior-year net (400 - 100 = 300).
        $this->assertSame(300.0, $response->viewData('opening'));
        $this->assertSame([50.0, -30.0, 200.0], array_slice($response->viewData('netByMonth'), 0, 3));
        // Running: 300 +50 -> 350, -30 -> 320, +200 -> 520 (holds through year end).
        $this->assertSame([350.0, 320.0, 520.0], array_slice($response->viewData('runningByMonth'), 0, 3));
        $this->assertSame(520.0, $response->viewData('closing'));
    }

    public function test_income_source_by_year_report(): void
    {
        $user = $this->seedData();
        $this->seedPriorYear($user);

        $response = $this->actingAs($user)->get(route('reports.income_source_by_year', ['year' => 2026, 'years' => 2]));
        $response->assertOk();

        $this->assertSame(['2025', '2026'], $response->viewData('labels'));
        $rows = $response->viewData('rows');
        $this->assertSame('Sales', $rows[0]['source']);
        $this->assertSame([400.0, 300.0], $rows[0]['series']);
        $this->assertSame([400.0, 300.0], $response->viewData('totalsByYear'));
        $this->assertSame(700.0, $response->viewData('grandTotal'));
    }

    public function test_quarterly_summary_report(): void
    {
        $user = $this->seedData();

        $response = $this->actingAs($user)->get(route('reports.quarterly_summary', ['year' => 2026]));
        $response->assertOk();

        // All 2026 activity falls in Q1.
        $this->assertSame([300.0, 0.0, 0.0, 0.0], $response->viewData('incomeByQuarter'));
        $this->assertSame([80.0, 0.0, 0.0, 0.0], $response->viewData('expenseByQuarter'));
        $this->assertSame([220.0, 0.0, 0.0, 0.0], $response->viewData('profitByQuarter'));
        $this->assertSame(220.0, $response->viewData('totalProfit'));
    }

    public function test_employee_revenue_by_year_report(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $alice = Employee::create(['name' => 'Alice', 'sort_order' => 1, 'is_active' => true]);
        $bob = Employee::create(['name' => 'Bob', 'sort_order' => 2, 'is_active' => true]);

        EmployeeIncome::create(['employee_id' => $alice->id, 'month' => 1, 'year' => 2025, 'total_amount' => '100.00']);
        EmployeeIncome::create(['employee_id' => $alice->id, 'month' => 2, 'year' => 2026, 'total_amount' => '250.00']);
        EmployeeIncome::create(['employee_id' => $bob->id, 'month' => 3, 'year' => 2026, 'total_amount' => '90.00']);

        $response = $this->actingAs($user)->get(route('reports.employee_revenue_by_year', ['year' => 2026, 'years' => 2]));
        $response->assertOk();

        $this->assertSame(['2025', '2026'], $response->viewData('labels'));
        $rows = $response->viewData('rows');
        // Alice leads (350 total) then Bob (90).
        $this->assertSame('Alice', $rows[0]['employee']);
        $this->assertSame([100.0, 250.0], $rows[0]['series']);
        $this->assertSame('Bob', $rows[1]['employee']);
        $this->assertSame([0.0, 90.0], $rows[1]['series']);
        $this->assertSame([100.0, 340.0], $response->viewData('totalsByYear'));
        $this->assertSame(440.0, $response->viewData('grandTotal'));
    }

    public function test_ytd_income_report(): void
    {
        $user = $this->seedData();

        $response = $this->actingAs($user)->get(route('reports.ytd_income', ['year' => 2026]));
        $response->assertOk();

        $this->assertSame(300.0, $response->viewData('total'));
        $this->assertSame(['Sales' => 300.0], $response->viewData('bySource'));
        $this->assertSame([100.0, 0.0, 200.0], array_slice($response->viewData('byMonth'), 0, 3));
    }

    public function test_ytd_expenses_report(): void
    {
        $user = $this->seedData();

        $response = $this->actingAs($user)->get(route('reports.ytd_expenses', ['year' => 2026]));
        $response->assertOk();

        $this->assertSame(80.0, $response->viewData('total'));
        $this->assertSame(['Office' => 80.0], $response->viewData('byCategory'));
        $this->assertSame(['Cash' => 80.0], $response->viewData('byMethod'));
        $this->assertSame([50.0, 30.0, 0.0], array_slice($response->viewData('byMonth'), 0, 3));
    }

    public function test_prev_year_comparison_report(): void
    {
        $user = $this->seedData();

        $response = $this->actingAs($user)->get(route('reports.prev_year_comparison', ['year' => 2026]));
        $response->assertOk();

        $this->assertSame(['income' => 300.0, 'expenses' => 80.0, 'profit' => 220.0], $response->viewData('current'));
        $this->assertSame(['income' => 0.0, 'expenses' => 0.0, 'profit' => 0.0], $response->viewData('previous'));
        $this->assertSame([50.0, -30.0, 200.0], array_slice($response->viewData('curProfit'), 0, 3));
    }

    public function test_prev_year_monthly_income_report(): void
    {
        $user = $this->seedData();

        $response = $this->actingAs($user)->get(route('reports.prev_year_monthly_income', ['year' => 2026]));
        $response->assertOk();

        $this->assertSame([100.0, 0.0, 200.0], array_slice($response->viewData('incomeYear'), 0, 3));
        $this->assertSame(300.0, $response->viewData('totalYear'));
        $this->assertSame(0.0, $response->viewData('totalPrev'));
    }

    public function test_category_trend_report(): void
    {
        $user = $this->seedData();

        $response = $this->actingAs($user)->get(route('reports.category_trend', ['year' => 2026]));
        $response->assertOk();

        $datasets = $response->viewData('datasets');
        $this->assertSame('Office', $datasets[0]['label']);
        $this->assertSame([50.0, 30.0, 0.0], array_slice($datasets[0]['data'], 0, 3));
    }

    public function test_index_summary(): void
    {
        $user = $this->seedData();

        $response = $this->actingAs($user)->get(route('reports.index', ['year' => 2026]));
        $response->assertOk();

        $this->assertSame(300.0, $response->viewData('incomeTotal'));
        $this->assertSame(80.0, $response->viewData('expenseTotal'));
        $this->assertSame(220.0, $response->viewData('profit'));
    }
}
