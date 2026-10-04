<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('marketplace_income', 'sku_reference')) {
            Schema::table('marketplace_income', function (Blueprint $table): void {
                $table->string('sku_reference')->nullable()->after('product_name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('marketplace_income', 'sku_reference')) {
            Schema::table('marketplace_income', function (Blueprint $table): void {
                $table->dropColumn('sku_reference');
            });
        }
    }
};
