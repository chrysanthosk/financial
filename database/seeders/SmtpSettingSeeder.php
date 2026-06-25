<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SmtpSettingSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure exactly one default row exists (idempotent)
        if (DB::table('smtp_settings')->count() === 0) {
            DB::table('smtp_settings')->insert([
                'enabled' => 0,
                'host' => null,
                'port' => null,
                'username' => null,
                'password' => null,
                'encryption' => null,
                'from_address' => null,
                'from_name' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
