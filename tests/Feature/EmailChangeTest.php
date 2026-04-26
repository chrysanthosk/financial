<?php

namespace Tests\Feature;

use App\Mail\ConfirmNewEmail;
use App\Mail\EmailChangeRequestedNotice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailChangeTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $email = 'old@example.com', string $password = 'password'): User
    {
        return User::create([
            'name' => 'Test User',
            'email' => $email,
            'password' => Hash::make($password),
        ]);
    }

    public function test_request_change_requires_current_password(): void
    {
        Mail::fake();

        $user = $this->makeUser();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->post('/profile/email', [
                'new_email' => 'new@example.com',
            ]);

        $response
            ->assertRedirect('/profile')
            ->assertSessionHasErrors('current_password');

        $this->assertNull($user->fresh()->pending_email);
        Mail::assertNothingSent();
    }

    public function test_request_change_rejects_wrong_password(): void
    {
        Mail::fake();

        $user = $this->makeUser();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->post('/profile/email', [
                'current_password' => 'not-the-password',
                'new_email' => 'new@example.com',
            ]);

        $response
            ->assertRedirect('/profile')
            ->assertSessionHasErrors('current_password');

        $this->assertNull($user->fresh()->pending_email);
        Mail::assertNothingSent();
    }

    public function test_request_change_with_correct_password_queues_confirmation_and_notifies_old_email(): void
    {
        Mail::fake();

        $user = $this->makeUser('old@example.com');

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->post('/profile/email', [
                'current_password' => 'password',
                'new_email' => 'new@example.com',
            ]);

        $response->assertRedirect('/profile');
        $response->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('new@example.com', $user->pending_email);
        $this->assertNotEmpty($user->pending_email_token);
        $this->assertNotNull($user->pending_email_requested_at);
        $this->assertSame('old@example.com', $user->email, 'Login email should not change before confirmation');

        Mail::assertSent(ConfirmNewEmail::class, function (ConfirmNewEmail $mail) {
            return $mail->hasTo('new@example.com');
        });

        Mail::assertSent(EmailChangeRequestedNotice::class, function (EmailChangeRequestedNotice $mail) {
            return $mail->hasTo('old@example.com')
                && $mail->newEmail === 'new@example.com';
        });
    }

    public function test_request_change_rejects_email_already_in_use(): void
    {
        Mail::fake();

        $this->makeUser('taken@example.com');
        $user = $this->makeUser('me@example.com');

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->post('/profile/email', [
                'current_password' => 'password',
                'new_email' => 'taken@example.com',
            ]);

        $response
            ->assertRedirect('/profile')
            ->assertSessionHasErrors('new_email');

        $this->assertNull($user->fresh()->pending_email);
        Mail::assertNothingSent();
    }
}
