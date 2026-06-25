<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'two_factor_last_used_counter')) {
                // Stores the TOTP time-step (floor(timestamp / 30)) of the last
                // accepted code so a code cannot be replayed within its window.
                $table->unsignedBigInteger('two_factor_last_used_counter')->nullable()->after('two_factor_confirmed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'two_factor_last_used_counter')) {
                $table->dropColumn('two_factor_last_used_counter');
            }
        });
    }
};
