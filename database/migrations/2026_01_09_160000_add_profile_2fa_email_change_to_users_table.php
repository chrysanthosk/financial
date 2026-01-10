<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'first_name')) {
                $table->string('first_name')->nullable()->after('id');
            }
            if (!Schema::hasColumn('users', 'last_name')) {
                $table->string('last_name')->nullable()->after('first_name');
            }

            // If you currently use "name", keep it for compatibility, but we’ll derive it.
            // 2FA fields
            if (!Schema::hasColumn('users', 'two_factor_secret')) {
                $table->text('two_factor_secret')->nullable()->after('password'); // encrypted cast
            }
            if (!Schema::hasColumn('users', 'two_factor_recovery_codes')) {
                $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret'); // encrypted cast
            }
            if (!Schema::hasColumn('users', 'two_factor_confirmed_at')) {
                $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
            }

            // Email change flow
            if (!Schema::hasColumn('users', 'pending_email')) {
                $table->string('pending_email')->nullable()->after('email');
            }
            if (!Schema::hasColumn('users', 'pending_email_token')) {
                $table->string('pending_email_token', 64)->nullable()->after('pending_email');
                $table->index('pending_email_token');
            }
            if (!Schema::hasColumn('users', 'pending_email_requested_at')) {
                $table->timestamp('pending_email_requested_at')->nullable()->after('pending_email_token');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach ([
                'first_name','last_name',
                'two_factor_secret','two_factor_recovery_codes','two_factor_confirmed_at',
                'pending_email','pending_email_token','pending_email_requested_at'
            ] as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
