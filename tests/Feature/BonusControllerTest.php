<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BonusControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_bonus_page_is_displayed(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('bonus.index'))
            ->assertOk();
    }

    public function test_calculate_returns_band_result(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->post(route('bonus.calculate'), ['amount' => '2000']);

        $response->assertOk();
        $response->assertViewHas('rate', 0.03);
        $response->assertViewHas('bonus', 60.00);
    }

    public function test_calculate_rejects_negative_amount(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->from(route('bonus.index'))
            ->post(route('bonus.calculate'), ['amount' => '-5']);

        $response->assertSessionHasErrors('amount');
    }

    public function test_calculate_requires_amount(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->from(route('bonus.index'))
            ->post(route('bonus.calculate'), []);

        $response->assertSessionHasErrors('amount');
    }
}
