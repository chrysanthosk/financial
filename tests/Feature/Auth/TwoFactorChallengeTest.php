<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OTPHP\TOTP;
use Tests\TestCase;

class TwoFactorChallengeTest extends TestCase
{
    use RefreshDatabase;

    /** A valid base32 TOTP secret for tests. */
    private const SECRET = 'JBSWY3DPEHPK3PXP';

    private function userWithTwoFactor(): array
    {
        $user = User::factory()->create([
            'two_factor_secret' => self::SECRET,
            'two_factor_confirmed_at' => now(),
        ]);

        $codes = $user->generateRecoveryCodes();
        $user->save();

        return [$user, $codes];
    }

    public function test_recovery_codes_are_stored_hashed_not_plaintext(): void
    {
        [$user, $codes] = $this->userWithTwoFactor();

        $stored = $user->fresh()->two_factor_recovery_codes;

        $this->assertNotContains($codes[0], $stored);
        foreach ($stored as $hash) {
            $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $hash);
        }
    }

    public function test_valid_totp_passes_the_challenge(): void
    {
        [$user] = $this->userWithTwoFactor();

        $code = TOTP::create(self::SECRET)->now();

        $response = $this
            ->withSession(['2fa:user:id' => $user->id])
            ->post('/two-factor-challenge', ['code' => $code]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_recovery_code_passes_the_challenge_and_is_consumed(): void
    {
        [$user, $codes] = $this->userWithTwoFactor();

        $response = $this
            ->withSession(['2fa:user:id' => $user->id])
            ->post('/two-factor-challenge', ['code' => $codes[0]]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user->fresh());

        // The used code is consumed (9 of 10 remain).
        $this->assertSame(9, $user->fresh()->recoveryCodesRemaining());
    }

    public function test_used_recovery_code_cannot_be_reused(): void
    {
        [$user, $codes] = $this->userWithTwoFactor();

        // Consume it directly so the second attempt is a clean, unauthenticated request.
        $user->useRecoveryCode($codes[0]);
        $user->save();

        $response = $this
            ->withSession(['2fa:user:id' => $user->id])
            ->post('/two-factor-challenge', ['code' => $codes[0]]);

        $response->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_invalid_code_fails_the_challenge(): void
    {
        [$user] = $this->userWithTwoFactor();

        $response = $this
            ->withSession(['2fa:user:id' => $user->id])
            ->post('/two-factor-challenge', ['code' => 'not-a-real-code']);

        $response->assertSessionHasErrors('code');
        $this->assertGuest();
    }
}
