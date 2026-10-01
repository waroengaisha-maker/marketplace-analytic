<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_orders', function (Blueprint $table): void {
            $table->unsignedInteger('fulfilled_quantity')->nullable()->after('quantity');
            $table->unsignedInteger('cancelled_quantity')->nullable()->after('returned_quantity');
        });

        Schema::table('order_cost_allocations', function (Blueprint $table): void {
            $table->decimal('quantity_base_unit', 18, 6)->nullable()->change();
            $table->decimal('total_hpp', 18, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('order_cost_allocations', function (Blueprint $table): void {
            $table->decimal('quantity_base_unit', 18, 6)->nullable(false)->change();
            $table->decimal('total_hpp', 18, 2)->nullable(false)->change();
        });

        Schema::table('marketplace_orders', function (Blueprint $table): void {
            $table->dropColumn(['fulfilled_quantity', 'cancelled_quantity']);
        });
    }
};
