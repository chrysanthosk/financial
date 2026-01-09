<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ExpenseCategorySeeder extends Seeder
{
    public function run(): void
    {
        // Default categories (configurable later from Settings)
        $defaults = [
            ['name' => 'Rent',               'sort_order' => 10,  'is_active' => true],
            ['name' => 'Utilities',          'sort_order' => 20,  'is_active' => true],
            ['name' => 'Suppliers',          'sort_order' => 30,  'is_active' => true],
            ['name' => 'Salaries',           'sort_order' => 40,  'is_active' => true],
            ['name' => 'Marketing',          'sort_order' => 50,  'is_active' => true],
            ['name' => 'Transport',          'sort_order' => 60,  'is_active' => true],
            ['name' => 'Maintenance',        'sort_order' => 70,  'is_active' => true],
            ['name' => 'Professional Fees',  'sort_order' => 80,  'is_active' => true],
            ['name' => 'Other',              'sort_order' => 999, 'is_active' => true],
        ];

        foreach ($defaults as $row) {
            DB::table('expense_categories')->updateOrInsert(
                ['name' => $row['name']],
                [
                    'sort_order' => $row['sort_order'],
                    'is_active'  => (bool) $row['is_active'],
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}
