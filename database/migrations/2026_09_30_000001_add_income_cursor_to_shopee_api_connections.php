<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shopee_api_connections', function (Blueprint $table) {
            $table->text('last_sync_income_cursor')->nullable()->after('last_sync_order_cursor');
        });
    }

    public function down(): void
    {
        Schema::table('shopee_api_connections', function (Blueprint $table) {
            $table->dropColumn('last_sync_income_cursor');
        });
    }
};
