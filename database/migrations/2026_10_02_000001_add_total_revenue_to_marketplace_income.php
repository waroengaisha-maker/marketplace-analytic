<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_income', function (Blueprint $table): void {
            $table->decimal('total_revenue', 18, 2)->nullable()->after('total_income');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_income', function (Blueprint $table): void {
            $table->dropColumn('total_revenue');
        });
    }
};
