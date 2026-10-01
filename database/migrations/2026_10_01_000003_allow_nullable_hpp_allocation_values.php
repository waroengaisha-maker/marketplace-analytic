<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_cost_allocations', function (Blueprint $table): void {
            $table->decimal('hpp_per_base_unit', 18, 6)->nullable()->change();
            $table->decimal('quantity_base_unit', 18, 6)->nullable()->change();
            $table->decimal('total_hpp', 18, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('order_cost_allocations', function (Blueprint $table): void {
            $table->decimal('hpp_per_base_unit', 18, 6)->nullable(false)->change();
            $table->decimal('quantity_base_unit', 18, 6)->nullable(false)->change();
            $table->decimal('total_hpp', 18, 2)->nullable(false)->change();
        });
    }
};
