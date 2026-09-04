<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_orders', function (Blueprint $table): void {
            if (! collect(Schema::getIndexes('marketplace_orders'))->contains('name', 'orders_exact_match_index')) {
                $table->index(
                    ['user_id', 'order_number', 'product_key', 'variation_key', 'quantity', 'unit_price'],
                    'orders_exact_match_index'
                );
            }
        });

        Schema::table('marketplace_income', function (Blueprint $table): void {
            if (! collect(Schema::getIndexes('marketplace_income'))->contains('name', 'income_exact_match_index')) {
                $table->index(
                    ['user_id', 'order_number', 'product_key', 'variation_key', 'quantity', 'product_price', 'total_income'],
                    'income_exact_match_index'
                );
            }
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_orders', function (Blueprint $table): void {
            $table->dropIndex('orders_exact_match_index');
        });

        Schema::table('marketplace_income', function (Blueprint $table): void {
            $table->dropIndex('income_exact_match_index');
        });
    }
};
