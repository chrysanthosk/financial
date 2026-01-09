<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        // Default payment methods (configurable later from Settings)
        $defaults = [
            ['name' => 'Cash',    'sort_order' => 10,  'is_active' => true],
            ['name' => 'Revolut', 'sort_order' => 20,  'is_active' => true],
            ['name' => 'Visa',    'sort_order' => 30,  'is_active' => true],
            ['name' => 'SEPA',    'sort_order' => 40,  'is_active' => true],
            ['name' => 'Cheque',  'sort_order' => 50,  'is_active' => true],
            ['name' => 'Other',   'sort_order' => 999, 'is_active' => true],
        ];

        foreach ($defaults as $row) {
            DB::table('payment_methods')->updateOrInsert(
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
