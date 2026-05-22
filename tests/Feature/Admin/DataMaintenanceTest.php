<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DataMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function seedAuditLogs(int $n = 3): void
    {
        for ($i = 0; $i < $n; $i++) {
            AuditLog::create([
                'user_id' => null,
                'action' => 'test.event',
                'category' => 'test',
            ]);
        }
    }

    public function test_admin_can_view_maintenance_page(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.settings.maintenance.index'))
            ->assertOk();
    }

    public function test_truncate_clears_table_and_records_the_action(): void
    {
        $admin = $this->admin();
        $this->seedAuditLogs(3);

        $this->actingAs($admin)
            ->post(route('admin.settings.maintenance.truncate', 'audit_logs'), ['confirm' => 'audit_logs'])
            ->assertSessionHasNoErrors();

        // The 3 seeded rows are gone; only the maintenance record of this clear remains.
        $this->assertSame(1, AuditLog::count());
        $this->assertSame('maintenance.truncated', AuditLog::first()->action);
    }

    public function test_wrong_confirmation_does_not_truncate(): void
    {
        $admin = $this->admin();
        $this->seedAuditLogs(3);

        $this->actingAs($admin)
            ->from(route('admin.settings.maintenance.index'))
            ->post(route('admin.settings.maintenance.truncate', 'audit_logs'), ['confirm' => 'wrong'])
            ->assertSessionHasErrors('confirm');

        $this->assertSame(3, AuditLog::count());
    }

    public function test_unknown_target_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.settings.maintenance.truncate', 'users'), ['confirm' => 'users'])
            ->assertNotFound();

        // Sanity: the financial/core table is not in the allowlist, so it's a 404.
        $this->assertDatabaseHas('users', ['id' => $this->admin()->id]);
    }

    public function test_non_admin_is_forbidden(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)
            ->get(route('admin.settings.maintenance.index'))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('admin.settings.maintenance.truncate', 'audit_logs'), ['confirm' => 'audit_logs'])
            ->assertForbidden();
    }
}
