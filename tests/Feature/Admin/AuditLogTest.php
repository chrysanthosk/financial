<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_admin_can_view_audit_log(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.audit.index'))
            ->assertOk();
    }

    public function test_sort_by_action_ascending(): void
    {
        $admin = $this->admin();
        foreach (['b.event', 'a.event', 'c.event'] as $action) {
            AuditLog::create(['user_id' => null, 'action' => $action, 'category' => 'test']);
        }

        $items = $this->actingAs($admin)
            ->get(route('admin.audit.index', ['sort' => 'action', 'direction' => 'asc']))
            ->viewData('logs')->items();

        $this->assertSame('a.event', $items[0]->action);
        $this->assertSame('c.event', $items[2]->action);
    }

    public function test_sort_by_user_email_via_join(): void
    {
        $admin = $this->admin();
        $zoe = User::factory()->create(['email' => 'zoe@example.com']);
        $amy = User::factory()->create(['email' => 'amy@example.com']);

        AuditLog::create(['user_id' => $zoe->id, 'action' => 'x', 'category' => 'test']);
        AuditLog::create(['user_id' => $amy->id, 'action' => 'y', 'category' => 'test']);

        $items = $this->actingAs($admin)
            ->get(route('admin.audit.index', ['sort' => 'user', 'direction' => 'asc']))
            ->viewData('logs')->items();

        // amy@ sorts before zoe@; eager-loaded user relation still intact.
        $this->assertSame('amy@example.com', $items[0]->user->email);
    }

    public function test_sort_by_user_combined_with_date_filter(): void
    {
        $admin = $this->admin();
        AuditLog::create(['user_id' => $admin->id, 'action' => 'x', 'category' => 'test']);

        // Sorting by user joins the users table; the date filter must stay
        // unambiguous (both tables have created_at).
        $this->actingAs($admin)
            ->get(route('admin.audit.index', ['sort' => 'user', 'from' => '2000-01-01']))
            ->assertOk();
    }

    public function test_invalid_sort_falls_back_to_time(): void
    {
        AuditLog::create(['user_id' => null, 'action' => 'x', 'category' => 'test']);

        $this->actingAs($this->admin())
            ->get(route('admin.audit.index', ['sort' => 'bogus']))
            ->assertOk();
    }

    public function test_non_admin_is_forbidden(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'user']))
            ->get(route('admin.audit.index'))
            ->assertForbidden();
    }
}
