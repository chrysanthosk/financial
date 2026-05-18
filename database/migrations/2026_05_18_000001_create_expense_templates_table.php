<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('expense_templates', function (Blueprint $table) {
            $table->id();

            $table->string('name', 120);
            $table->string('payee_name', 120);

            $table->foreignId('expense_category_id')
                ->constrained('expense_categories')
                ->restrictOnDelete();

            $table->foreignId('payment_method_id')
                ->constrained('payment_methods')
                ->restrictOnDelete();

            $table->decimal('amount', 12, 2);

            $table->string('cheque_no', 80)->nullable();
            $table->string('reason', 255)->nullable();

            $table->boolean('is_paid_default')->default(true);
            $table->boolean('auto_create')->default(false);
            $table->unsignedTinyInteger('day_of_month')->default(1);
            $table->boolean('is_active')->default(true);
            $table->date('last_generated_on')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['is_active', 'auto_create']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_templates');
    }
};
