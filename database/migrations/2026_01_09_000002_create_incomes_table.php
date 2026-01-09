<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('incomes', function (Blueprint $table) {
            $table->id();

            $table->date('income_date');
            $table->decimal('amount', 12, 2);

            $table->foreignId('income_source_id')->constrained('income_sources')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('note', 255)->nullable();

            $table->foreignId('created_by')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->timestamps();

            $table->index(['income_date']);
            $table->index(['income_source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incomes');
    }
};
