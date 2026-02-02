<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('employee_incomes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('employee_id')
                ->constrained('employees')
                ->cascadeOnDelete();

            $table->unsignedTinyInteger('month'); // 1..12
            $table->unsignedSmallInteger('year'); // 2000..2100
            $table->decimal('total_amount', 12, 2)->default(0);

            $table->timestamps();

            $table->unique(['employee_id', 'month', 'year'], 'emp_income_unique_employee_month_year');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_incomes');
    }
};
