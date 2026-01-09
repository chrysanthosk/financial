<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();

            $table->date('expense_date')->index();

            // "Name of the company" / payee
            $table->string('payee_name');

            // Category + payment method (configurable later)
            $table->foreignId('expense_category_id')->constrained('expense_categories');
            $table->foreignId('payment_method_id')->constrained('payment_methods');

            // Amount
            $table->decimal('amount', 12, 2);

            // Cheque number optional
            $table->string('cheque_no')->nullable();

            // Reason/description optional
            $table->string('reason')->nullable();

            // Auditing
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['expense_date', 'expense_category_id']);
            $table->index(['expense_date', 'payment_method_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
