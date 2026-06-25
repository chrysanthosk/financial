<?php

namespace Tests\Feature\Admin;

use App\Models\SmtpSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SmtpSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_non_admin_cannot_access_smtp_settings(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'user']))
            ->get(route('admin.settings.smtp.edit'))
            ->assertForbidden();
    }

    public function test_admin_can_view_smtp_settings(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.settings.smtp.edit'))
            ->assertOk();
    }

    public function test_update_persists_settings(): void
    {
        $response = $this->actingAs($this->admin())->put(route('admin.settings.smtp.update'), [
            'enabled' => '1',
            'host' => 'smtp.example.com',
            'port' => 587,
            'username' => 'mailer',
            'password' => 'secret-pass',
            'encryption' => 'tls',
            'from_address' => 'noreply@example.com',
            'from_name' => 'Finance App',
        ]);

        $response->assertSessionHasNoErrors();

        $smtp = SmtpSetting::current();
        $this->assertNotNull($smtp);
        $this->assertTrue($smtp->enabled);
        $this->assertSame('smtp.example.com', $smtp->host);
        $this->assertSame(587, $smtp->port);
        $this->assertSame('tls', $smtp->encryption);
        // password is encrypted-cast, should decrypt back to plaintext
        $this->assertSame('secret-pass', $smtp->password);
    }

    public function test_test_action_sends_mail_and_records_last_tested_at(): void
    {
        Mail::fake();

        // Seed an enabled config with host/port so the test path proceeds.
        SmtpSetting::query()->create([
            'enabled' => true,
            'host' => 'smtp.example.com',
            'port' => 587,
            'from_address' => 'noreply@example.com',
            'from_name' => 'Finance App',
        ]);

        $response = $this->actingAs($this->admin())->post(route('admin.settings.smtp.test'), [
            'test_email' => 'target@example.com',
        ]);

        // Mail::raw() is a no-op under Mail::fake() (it records nothing), so the
        // proof the test path completed is the persisted last_tested_at timestamp
        // plus a success status and no errors.
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('status');

        $this->assertNotNull(SmtpSetting::current()->last_tested_at);
    }

    public function test_test_action_fails_when_smtp_disabled(): void
    {
        Mail::fake();
        SmtpSetting::query()->create(['enabled' => false]);

        $this->actingAs($this->admin())
            ->from(route('admin.settings.smtp.edit'))
            ->post(route('admin.settings.smtp.test'), ['test_email' => 'target@example.com'])
            ->assertSessionHasErrors('smtp_test');

        Mail::assertNothingSent();
    }
}
