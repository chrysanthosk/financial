<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', // keep for compatibility
        'first_name',
        'last_name',
        'email',
        'password',
        'role',
        'theme',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
        'two_factor_last_used_counter',
        'pending_email',
        'pending_email_token',
        'pending_email_requested_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'pending_email_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'two_factor_confirmed_at' => 'datetime',
        'pending_email_requested_at' => 'datetime',

        // Encrypted at rest (Laravel will encrypt/decrypt automatically)
        'two_factor_secret' => 'encrypted',
        'two_factor_recovery_codes' => 'encrypted:array',
    ];

    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: function () {
                $fn = trim((string) ($this->first_name ?? ''));
                $ln = trim((string) ($this->last_name ?? ''));
                $full = trim($fn.' '.$ln);

                if ($full !== '') {
                    return $full;
                }

                return $this->name ?: $this->email;
            }
        );
    }

    public function hasTwoFactorEnabled(): bool
    {
        return ! empty($this->two_factor_secret) && ! is_null($this->two_factor_confirmed_at);
    }

    /**
     * Generate a fresh set of one-time recovery codes.
     *
     * Only the SHA-256 hashes are stored (still encrypted at rest via the
     * model cast); the plaintext is returned so it can be shown to the user
     * exactly once. The caller is responsible for persisting the model.
     *
     * @return array<int, string> Plaintext codes for one-time display.
     */
    public function generateRecoveryCodes(int $count = 10): array
    {
        $plain = [];
        $hashes = [];

        for ($i = 0; $i < $count; $i++) {
            $code = strtoupper(Str::random(10)).'-'.strtoupper(Str::random(10));
            $plain[] = $code;
            $hashes[] = hash('sha256', $code);
        }

        $this->two_factor_recovery_codes = $hashes;

        return $plain;
    }

    /**
     * Consume a recovery code if it matches a stored hash.
     *
     * On success the code is removed from the set (single use) and true is
     * returned. The caller is responsible for persisting the model.
     */
    public function useRecoveryCode(string $code): bool
    {
        $code = strtoupper(trim($code));
        $hashes = $this->two_factor_recovery_codes ?? [];

        if (! is_array($hashes) || $code === '') {
            return false;
        }

        $candidate = hash('sha256', $code);

        foreach ($hashes as $index => $stored) {
            if (hash_equals((string) $stored, $candidate)) {
                unset($hashes[$index]);
                $this->two_factor_recovery_codes = array_values($hashes);

                return true;
            }
        }

        return false;
    }

    /**
     * Number of unused recovery codes remaining.
     */
    public function recoveryCodesRemaining(): int
    {
        $hashes = $this->two_factor_recovery_codes ?? [];

        return is_array($hashes) ? count($hashes) : 0;
    }

    public function trustedDevices(): HasMany
    {
        return $this->hasMany(TwoFactorTrustedDevice::class);
    }

    /**
     * Revoke all server-side trusted-device tokens for this user. The browser
     * cookie becomes useless once its row is gone; callers may also expire it.
     */
    public function revokeTrustedDevices(): void
    {
        $this->trustedDevices()->delete();
    }
}
