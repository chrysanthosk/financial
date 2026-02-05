<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('two_factor_trusted_devices', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // We store ONLY a hash of the token (never the raw token)
            $table->string('token_hash', 64)->index(); // sha256 hex = 64 chars

            $table->string('device_name')->nullable(); // optional future use
            $table->string('ip_address', 64)->nullable();
            $table->string('user_agent', 255)->nullable();

            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->index();

            $table->timestamps();

            // Fast lookup for cookie validations
            $table->index(['user_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('two_factor_trusted_devices');
    }
};
