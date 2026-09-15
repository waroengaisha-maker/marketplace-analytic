<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shopee_api_connections', function (Blueprint $table) {
            $table->timestamp('last_staged_at')->nullable()->after('last_sync_error');
            $table->timestamp('last_promoted_at')->nullable()->after('last_staged_at');
            $table->string('promoted_fingerprint', 64)->nullable()->after('last_promoted_at');
        });
    }

    public function down(): void
    {
        Schema::table('shopee_api_connections', function (Blueprint $table) {
            $table->dropColumn(['last_staged_at', 'last_promoted_at', 'promoted_fingerprint']);
        });
    }
};
