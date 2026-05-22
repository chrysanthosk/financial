<?php

namespace Tests\Feature\Admin;

use App\Models\ExpenseCategory;
use App\Models\Income;
use App\Models\IncomeSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfigurationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_income_source_can_be_created_active_by_default(): void
    {
        $response = $this->actingAs($this->admin())
            ->post(route('admin.settings.config.income_sources.store'), [
                'name' => 'Consulting',
                'sort_order' => 3,
                'is_active' => '1',
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('income_sources', [
            'name' => 'Consulting',
            'sort_order' => 3,
            'is_active' => true,
        ]);
    }

    public function test_unchecked_is_active_creates_inactive_entity(): void
    {
        // No is_active key submitted (checkbox unchecked) => inactive.
        $response = $this->actingAs($this->admin())
            ->post(route('admin.settings.config.expense_categories.store'), [
                'name' => 'Archived',
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('expense_categories', [
            'name' => 'Archived',
            'is_active' => false,
        ]);
    }

    public function test_income_source_in_use_cannot_be_deleted(): void
    {
        $admin = $this->admin();
        $source = IncomeSource::create(['name' => 'Sales', 'sort_order' => 1, 'is_active' => true]);
        Income::create([
            'income_date' => '2026-05-01',
            'income_source_id' => $source->id,
            'amount' => '10.00',
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)
            ->delete(route('admin.settings.config.income_sources.destroy', $source));

        $response->assertSessionHasErrors('config');
        $this->assertDatabaseHas('income_sources', ['id' => $source->id]);
    }

    public function test_unused_entity_can_be_deleted(): void
    {
        $cat = ExpenseCategory::create(['name' => 'Temp', 'sort_order' => 1, 'is_active' => true]);

        $response = $this->actingAs($this->admin())
            ->delete(route('admin.settings.config.expense_categories.destroy', $cat));

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('expense_categories', ['id' => $cat->id]);
    }
}
