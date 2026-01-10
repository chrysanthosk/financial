<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class SmtpSetting extends Model
{
    protected $table = 'smtp_settings';

    protected $fillable = [
        'enabled',
        'host',
        'port',
        'username',
        'password',
        'encryption',
        'from_address',
        'from_name',
    ];

    protected $casts = [
        'enabled'  => 'boolean',
        'password' => 'encrypted',
        'port'     => 'integer',
        'last_tested_at' => 'datetime',
    ];

    /**
     * Returns the first SMTP settings row or null if table/row doesn't exist.
     * Safe to call even before migrations run.
     */
    public static function current(): ?self
    {
        if (!Schema::hasTable('smtp_settings')) {
            return null;
        }

        return self::query()->first();
    }

    /**
     * Returns existing row or creates a default disabled row (only after table exists).
     */
    public static function currentOrCreate(): self
    {
        return self::query()->first() ?? self::query()->create(['enabled' => false]);
    }
}
