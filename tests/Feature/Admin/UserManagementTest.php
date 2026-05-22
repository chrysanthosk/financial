<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_admin_can_create_user(): void
    {
        $response = $this->actingAs($this->admin())->post(route('admin.users.store'), [
            'first_name' => 'New',
            'last_name' => 'Person',
            'email' => 'new.person@example.com',
            'role' => 'user',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseHas('users', [
            'email' => 'new.person@example.com',
            'name' => 'New Person',
            'role' => 'user',
        ]);
    }

    public function test_create_requires_unique_email(): void
    {
        $admin = $this->admin();
        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'email' => 'taken@example.com',
            'role' => 'user',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_update_keeps_password_when_left_blank(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create(['email' => 'edit.me@example.com']);
        $originalHash = $target->password;

        $response = $this->actingAs($admin)->put(route('admin.users.update', $target), [
            'first_name' => 'Edited',
            'last_name' => 'Name',
            'email' => 'edit.me@example.com',
            'role' => 'user',
            // no password fields
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame($originalHash, $target->fresh()->password);
        $this->assertSame('Edited Name', $target->fresh()->name);
    }

    public function test_non_admin_cannot_access_user_management(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)->get(route('admin.users.index'))->assertForbidden();
    }
}
