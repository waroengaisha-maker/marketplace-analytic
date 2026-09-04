<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('marketplace_orders', 'product_key')) {
                $table->string('product_key', 64)->nullable()->after('product_name');
            }
            $table->dropUnique('marketplace_orders_order_number_parent_sku_variation_name_unique');
            $table->unique(['user_id', 'order_number', 'product_key', 'item_index'], 'orders_user_product_item_unique');
        });

        Schema::table('marketplace_income', function (Blueprint $table): void {
            if (! Schema::hasColumn('marketplace_income', 'product_key')) {
                $table->string('product_key', 64)->nullable()->after('product_name');
            }
            $table->dropUnique('income_user_item_unique');
            $table->unique(['user_id', 'order_number', 'product_key', 'item_index'], 'income_user_product_item_unique');
        });
    }

    public function down(): void {}
};