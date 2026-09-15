<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shopee_api_connections', function (Blueprint $table) {
            $table->json('staging_escrow')->nullable()->after('staging_income');
        });
    }

    public function down(): void
    {
        Schema::table('shopee_api_connections', function (Blueprint $table) {
            $table->dropColumn('staging_escrow');
        });
    }
};
