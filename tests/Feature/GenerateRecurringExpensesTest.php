<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseTemplate;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerateRecurringExpensesTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeTemplate(array $overrides = []): ExpenseTemplate
    {
        return ExpenseTemplate::factory()
            ->autoCreate()
            ->create(array_merge([
                'day_of_month' => 10,
                'created_by' => User::factory(),
            ], $overrides));
    }

    public function test_active_auto_create_template_generates_current_month_expense(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-25'));
        $tpl = $this->makeTemplate();

        $this->artisan('expenses:generate-recurring')->assertSuccessful();

        $this->assertDatabaseCount('expenses', 1);
        $this->assertDatabaseHas('expenses', [
            'payee_name' => $tpl->payee_name,
            'expense_date' => '2026-06-10',
            'created_by' => $tpl->created_by,
        ]);
        $this->assertSame('2026-06-10', $tpl->fresh()->last_generated_on->toDateString());
    }

    public function test_dry_run_writes_nothing(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-25'));
        $tpl = $this->makeTemplate();

        $this->artisan('expenses:generate-recurring', ['--dry-run' => true])->assertSuccessful();

        $this->assertDatabaseCount('expenses', 0);
        $this->assertNull($tpl->fresh()->last_generated_on);
    }

    public function test_second_run_does_not_duplicate(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-25'));
        $this->makeTemplate();

        $this->artisan('expenses:generate-recurring')->assertSuccessful();
        $this->artisan('expenses:generate-recurring')->assertSuccessful();

        $this->assertDatabaseCount('expenses', 1);
    }

    public function test_inactive_or_non_auto_templates_are_skipped(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-25'));

        ExpenseTemplate::factory()->autoCreate()->inactive()->create(['day_of_month' => 10]);
        ExpenseTemplate::factory()->create(['day_of_month' => 10, 'auto_create' => false]);

        $this->artisan('expenses:generate-recurring')->assertSuccessful();

        $this->assertDatabaseCount('expenses', 0);
    }

    public function test_template_last_generated_in_a_past_month_backfills_every_missed_month(): void
    {
        // Last run was Feb; "now" is late June. The command should backfill
        // every missed month (Mar, Apr, May) plus the current month (Jun),
        // one expense each, exactly once.
        Carbon::setTestNow(Carbon::parse('2026-06-25'));

        $tpl = $this->makeTemplate([
            'day_of_month' => 10,
            'last_generated_on' => '2026-02-10',
        ]);

        $this->artisan('expenses:generate-recurring')->assertSuccessful();

        $this->assertDatabaseCount('expenses', 4);
        foreach (['2026-03-10', '2026-04-10', '2026-05-10', '2026-06-10'] as $date) {
            $this->assertDatabaseHas('expenses', [
                'expense_date' => $date,
                'payee_name' => $tpl->payee_name,
            ]);
        }
        $this->assertSame('2026-06-10', $tpl->fresh()->last_generated_on->toDateString());

        // Running again the same month generates nothing further (idempotent).
        $this->artisan('expenses:generate-recurring')->assertSuccessful();
        $this->assertDatabaseCount('expenses', 4);
    }

    public function test_template_already_generated_this_month_is_skipped(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-25'));

        // last_generated_on is already within the current month -> skipped.
        $this->makeTemplate([
            'day_of_month' => 10,
            'last_generated_on' => '2026-06-10',
        ]);

        $this->artisan('expenses:generate-recurring')->assertSuccessful();

        $this->assertDatabaseCount('expenses', 0);
    }

    public function test_current_month_not_generated_when_target_day_not_yet_arrived(): void
    {
        // Today is the 5th, template fires on the 10th -> nothing this month.
        Carbon::setTestNow(Carbon::parse('2026-06-05'));
        $this->makeTemplate(['day_of_month' => 10]);

        $this->artisan('expenses:generate-recurring')->assertSuccessful();

        $this->assertDatabaseCount('expenses', 0);
    }
}
