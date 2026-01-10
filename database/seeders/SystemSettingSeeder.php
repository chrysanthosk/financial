<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SystemSettingSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('system_settings')->updateOrInsert(
            ['id' => 1],
            [
                'header_name' => config('app.name', 'Financial'),
                'footer_name' => config('app.name', 'Financial'),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
