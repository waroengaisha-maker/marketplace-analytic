<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role')->default('user')->after('password')->index();
            $table->string('account_status')->default('pending')->after('role')->index();
            $table->timestamp('trial_started_at')->nullable()->after('account_status');
            $table->timestamp('trial_ends_at')->nullable()->after('trial_started_at');
            $table->timestamp('subscription_ends_at')->nullable()->after('trial_ends_at');
            $table->timestamp('activated_at')->nullable()->after('subscription_ends_at');
            $table->timestamp('suspended_at')->nullable()->after('activated_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'role',
                'account_status',
                'trial_started_at',
                'trial_ends_at',
                'subscription_ends_at',
                'activated_at',
                'suspended_at',
            ]);
        });
    }
};
