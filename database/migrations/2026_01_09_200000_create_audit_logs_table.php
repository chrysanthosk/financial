<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            // Who did it (nullable for guest events like failed login)
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // What happened
            $table->string('action', 100);                 // e.g. smtp.updated, user.created, auth.login
            $table->string('category', 50)->default('app'); // auth|settings|users|security|app

            // Optional target (e.g. edited user)
            $table->string('target_type', 100)->nullable(); // e.g. User, SmtpSetting
            $table->string('target_id', 100)->nullable();   // e.g. user id

            // Metadata
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();

            // Optional JSON payload (changed fields, etc.)
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['action']);
            $table->index(['category']);
            $table->index(['user_id']);
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
