<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationLogTest extends TestCase
{
    use RefreshDatabase;

    private ?string $logFile = null;

    protected function tearDown(): void
    {
        if ($this->logFile && is_file($this->logFile)) {
            @unlink($this->logFile);
        }

        parent::tearDown();
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function seedLog(): void
    {
        $this->logFile = storage_path('logs/testing_'.uniqid().'.log');

        file_put_contents($this->logFile, implode("\n", [
            '[2026-05-20 10:00:00] production.INFO: User logged in',
            '[2026-05-21 11:00:00] production.ERROR: Payment gateway failed',
            '#0 /app/Services/Pay.php(42): charge()',
            '#1 {main}',
            '[2026-05-22 12:00:00] production.WARNING: Disk almost full',
            '',
        ]));

        config(['logging.channels.single.path' => $this->logFile]);
    }

    public function test_admin_sees_all_entries_newest_first(): void
    {
        $this->seedLog();

        $response = $this->actingAs($this->admin())->get(route('admin.settings.logs.index'));

        $response->assertOk();
        $logs = $response->viewData('logs');

        $this->assertSame(3, $logs->total());
        // Newest first => the WARNING entry leads.
        $this->assertSame('WARNING', $logs->items()[0]['level']);
    }

    public function test_filter_by_level_includes_stack_trace(): void
    {
        $this->seedLog();

        $response = $this->actingAs($this->admin())
            ->get(route('admin.settings.logs.index', ['level' => 'error']));

        $response->assertOk();
        $items = $response->viewData('logs')->items();

        $this->assertCount(1, $items);
        $this->assertStringContainsString('Payment gateway failed', $items[0]['message']);
        $this->assertStringContainsString('charge()', $items[0]['trace']);
    }

    public function test_search_matches_message_text(): void
    {
        $this->seedLog();

        $response = $this->actingAs($this->admin())
            ->get(route('admin.settings.logs.index', ['search' => 'Disk']));

        $this->assertSame(1, $response->viewData('logs')->total());
    }

    public function test_date_range_filter(): void
    {
        $this->seedLog();

        $response = $this->actingAs($this->admin())
            ->get(route('admin.settings.logs.index', ['from' => '2026-05-22']));

        $this->assertSame(1, $response->viewData('logs')->total());
    }

    public function test_default_sort_is_newest_first(): void
    {
        $this->seedLog();

        $items = $this->actingAs($this->admin())
            ->get(route('admin.settings.logs.index'))
            ->viewData('logs')->items();

        $this->assertSame('2026-05-22 12:00:00', $items[0]['timestamp']);
        $this->assertSame('2026-05-20 10:00:00', $items[2]['timestamp']);
    }

    public function test_sort_by_time_ascending(): void
    {
        $this->seedLog();

        $items = $this->actingAs($this->admin())
            ->get(route('admin.settings.logs.index', ['sort' => 'time', 'direction' => 'asc']))
            ->viewData('logs')->items();

        $this->assertSame('2026-05-20 10:00:00', $items[0]['timestamp']);
        $this->assertSame('2026-05-22 12:00:00', $items[2]['timestamp']);
    }

    public function test_sort_by_level_severity(): void
    {
        $this->seedLog();

        // asc = most severe first (error < warning < info by severity rank)
        $items = $this->actingAs($this->admin())
            ->get(route('admin.settings.logs.index', ['sort' => 'level', 'direction' => 'asc']))
            ->viewData('logs')->items();

        $this->assertSame('ERROR', $items[0]['level']);
        $this->assertSame('INFO', $items[2]['level']);
    }

    public function test_invalid_sort_falls_back_to_time(): void
    {
        $this->seedLog();

        $this->actingAs($this->admin())
            ->get(route('admin.settings.logs.index', ['sort' => 'bogus']))
            ->assertOk();
    }

    public function test_non_admin_is_forbidden(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'user']))
            ->get(route('admin.settings.logs.index'))
            ->assertForbidden();
    }
}
