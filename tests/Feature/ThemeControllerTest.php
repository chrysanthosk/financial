<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_persists_valid_theme(): void
    {
        $user = User::factory()->create(['theme' => 'light']);

        $response = $this->actingAs($user)->postJson(route('theme.update'), ['theme' => 'dark']);

        $response->assertOk()->assertJson(['ok' => true]);
        $this->assertSame('dark', $user->fresh()->theme);
    }

    public function test_rejects_invalid_theme(): void
    {
        $user = User::factory()->create(['theme' => 'light']);

        $response = $this->actingAs($user)->postJson(route('theme.update'), ['theme' => 'rainbow']);

        $response->assertStatus(422);
        $this->assertSame('light', $user->fresh()->theme);
    }

    public function test_requires_authentication(): void
    {
        $this->postJson(route('theme.update'), ['theme' => 'dark'])->assertUnauthorized();
    }
}
