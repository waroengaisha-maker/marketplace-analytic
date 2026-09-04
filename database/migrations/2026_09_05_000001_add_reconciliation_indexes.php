<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_orders', function (Blueprint $table): void {
            if (! collect(Schema::getIndexes('marketplace_orders'))->contains('name', 'orders_reconciliation_lookup_index')) {
                $table->index(
                    ['user_id', 'order_number', 'product_key', 'item_index'],
                    'orders_reconciliation_lookup_index'
                );
            }
        });

        Schema::table('marketplace_income', function (Blueprint $table): void {
            if (! collect(Schema::getIndexes('marketplace_income'))->contains('name', 'income_reconciliation_lookup_index')) {
                $table->index(
                    ['user_id', 'order_number', 'product_key', 'item_index', 'total_income'],
                    'income_reconciliation_lookup_index'
                );
            }
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_orders', function (Blueprint $table): void {
            $table->dropIndex('orders_reconciliation_lookup_index');
        });

        Schema::table('marketplace_income', function (Blueprint $table): void {
            $table->dropIndex('income_reconciliation_lookup_index');
        });
    }
};
