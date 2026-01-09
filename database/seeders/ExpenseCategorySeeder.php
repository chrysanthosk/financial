<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ExpenseCategorySeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            ['name' => 'Utilities',     'sort_order' => 10, 'is_active' => true],
            ['name' => 'Suppliers',     'sort_order' => 20, 'is_active' => true],
            ['name' => 'Rent',          'sort_order' => 30, 'is_active' => true],
            ['name' => 'Payroll',       'sort_order' => 40, 'is_active' => true],
            ['name' => 'Other',         'sort_order' => 90, 'is_active' => true],
        ];

        foreach ($defaults as $row) {
            DB::table('expense_categories')->updateOrInsert(
                ['name' => $row['name']],
                [
                    'sort_order' => $row['sort_order'],
                    'is_active'  => (bool)$row['is_active'],
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}
