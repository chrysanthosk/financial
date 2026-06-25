<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_defaults_to_current_month(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('current month');
        $response->assertSee('Today Income');
    }

    public function test_dashboard_accepts_a_month_parameter(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard', ['month' => '2025-03']));

        $response->assertOk();
        $response->assertSee('March 2025');
        // The "Today Income" widget only renders for the current month.
        $response->assertDontSee('Today Income');
    }

    public function test_dashboard_ignores_a_malformed_month(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard', ['month' => 'not-a-month']));

        $response->assertOk();
        $response->assertSee('current month');
    }
}
