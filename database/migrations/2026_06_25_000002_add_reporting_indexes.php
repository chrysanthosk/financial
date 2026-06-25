<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            // topVendors / recurringExpenses group and sort by payee_name.
            $table->index('payee_name');
        });

        Schema::table('employee_incomes', function (Blueprint $table) {
            // The unique key leads with employee_id, so reports filtering by
            // year (and year+month) can't use it. Add a matching index.
            $table->index(['year', 'month'], 'employee_incomes_year_month_index');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropIndex(['payee_name']);
        });

        Schema::table('employee_incomes', function (Blueprint $table) {
            $table->dropIndex('employee_incomes_year_month_index');
        });
    }
};
