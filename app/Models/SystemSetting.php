<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class SystemSetting extends Model
{
    protected $table = 'system_settings';

    protected $fillable = [
        'header_name',
        'footer_name',
    ];

    private static ?self $cached = null;

    public static function current(): self
    {
        if (self::$cached) {
            return self::$cached;
        }

        self::$cached = static::firstOrCreate(
            ['id' => 1],
            [
                'header_name' => config('app.name', 'Financial'),
                'footer_name' => config('app.name', 'Financial'),
            ]
        );

        return self::$cached;
    }

    public static function safeCurrent(): ?self
    {
        try {
            if (!Schema::hasTable('system_settings')) {
                return null;
            }
            return static::current();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
