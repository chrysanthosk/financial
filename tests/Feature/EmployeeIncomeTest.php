<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeIncome;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeIncomeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_employee_income_can_be_created(): void
    {
        $admin = $this->admin();
        $employee = Employee::create(['name' => 'Jane', 'sort_order' => 1, 'is_active' => true]);

        $response = $this->actingAs($admin)->post(route('admin.emp_income.store'), [
            'employee_id' => $employee->id,
            'month' => 5,
            'year' => 2026,
            'total_amount' => '1000.00',
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect(route('admin.emp_income.index'));
        $this->assertDatabaseHas('employee_incomes', [
            'employee_id' => $employee->id,
            'month' => 5,
            'year' => 2026,
        ]);
    }

    public function test_duplicate_employee_month_year_is_rejected(): void
    {
        $admin = $this->admin();
        $employee = Employee::create(['name' => 'Jane', 'sort_order' => 1, 'is_active' => true]);

        EmployeeIncome::create([
            'employee_id' => $employee->id,
            'month' => 5,
            'year' => 2026,
            'total_amount' => '1000.00',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.emp_income.store'), [
            'employee_id' => $employee->id,
            'month' => 5,
            'year' => 2026,
            'total_amount' => '2000.00',
        ]);

        $response->assertSessionHasErrors('employee_id');
        $this->assertDatabaseCount('employee_incomes', 1);
    }

    public function test_update_allows_keeping_same_month_year(): void
    {
        $admin = $this->admin();
        $employee = Employee::create(['name' => 'Jane', 'sort_order' => 1, 'is_active' => true]);

        $row = EmployeeIncome::create([
            'employee_id' => $employee->id,
            'month' => 5,
            'year' => 2026,
            'total_amount' => '1000.00',
        ]);

        // Updating the same row with its own month/year must not trip the unique rule.
        $response = $this->actingAs($admin)->put(route('admin.emp_income.update', $row), [
            'employee_id' => $employee->id,
            'month' => 5,
            'year' => 2026,
            'total_amount' => '1500.00',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('employee_incomes', ['id' => $row->id, 'total_amount' => '1500.00']);
    }

    public function test_index_shows_suggested_bonus_column(): void
    {
        $admin = $this->admin();
        $employee = Employee::create(['name' => 'Jane', 'sort_order' => 1, 'is_active' => true]);

        // 3000 falls in the [2800, 4000) band => 4% => bonus 120.00.
        EmployeeIncome::create([
            'employee_id' => $employee->id,
            'month' => 5,
            'year' => 2026,
            'total_amount' => '3000.00',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.emp_income.index', ['year' => 2026]));

        $response->assertOk()
            ->assertSee('Bonus')
            ->assertSee('120.00')
            ->assertSee('4%');
    }

    public function test_non_admin_cannot_create_employee_income(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $employee = Employee::create(['name' => 'Jane', 'sort_order' => 1, 'is_active' => true]);

        $this->actingAs($user)->post(route('admin.emp_income.store'), [
            'employee_id' => $employee->id,
            'month' => 5,
            'year' => 2026,
            'total_amount' => '1000.00',
        ])->assertForbidden();

        $this->assertDatabaseCount('employee_incomes', 0);
    }
}
