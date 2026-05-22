<?php

namespace Tests\Feature;

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
