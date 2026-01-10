<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Casts\Attribute;

class User extends Authenticatable
{
    use Notifiable;

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

        // Laravel supports encrypted casts
        'two_factor_secret' => 'encrypted',
        'two_factor_recovery_codes' => 'encrypted:array',
    ];

    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: function () {
                $fn = trim((string)($this->first_name ?? ''));
                $ln = trim((string)($this->last_name ?? ''));
                $full = trim($fn . ' ' . $ln);

                if ($full !== '') return $full;
                return $this->name ?: $this->email;
            }
        );
    }

    public function hasTwoFactorEnabled(): bool
    {
        return !empty($this->two_factor_secret) && !is_null($this->two_factor_confirmed_at);
    }
}
