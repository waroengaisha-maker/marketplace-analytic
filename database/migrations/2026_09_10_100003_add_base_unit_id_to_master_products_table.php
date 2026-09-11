<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('master_products', function (Blueprint $table): void {
            $table->foreignId('base_unit_id')->nullable()->after('normalized_name')->constrained('master_product_units')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('master_products', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('base_unit_id');
        });
    }
};
