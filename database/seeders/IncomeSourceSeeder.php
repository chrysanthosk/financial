<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\IncomeSource;

class IncomeSourceSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            ['name' => 'Cash',    'sort_order' => 10],
            ['name' => 'Revolut', 'sort_order' => 20],
            ['name' => 'Visa',    'sort_order' => 30],
            ['name' => 'Other',   'sort_order' => 40],
            ['name' => 'Type B',  'sort_order' => 50],
        ];

        foreach ($defaults as $row) {
            IncomeSource::firstOrCreate(
                ['name' => $row['name']],
                [
                    'is_active'  => true,
                    'sort_order' => $row['sort_order'],
                ]
            );
        }
    }
}
