<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('marketplace_orders', 'variation_key')) {
                $table->string('variation_key', 64)->nullable()->after('variation_name');
            }
            if (! Schema::hasColumn('marketplace_orders', 'unit_price')) {
                $table->decimal('unit_price', 18, 2)->nullable()->after('discounted_price');
            }
        });

        Schema::table('marketplace_income', function (Blueprint $table): void {
            if (! Schema::hasColumn('marketplace_income', 'variation_key')) {
                $table->string('variation_key', 64)->nullable()->after('product_name');
            }
            if (! Schema::hasColumn('marketplace_income', 'unit_price')) {
                $table->decimal('unit_price', 18, 2)->nullable()->after('product_key');
            }
            if (! Schema::hasColumn('marketplace_income', 'quantity')) {
                $table->unsignedInteger('quantity')->nullable()->after('unit_price');
            }
        });

        Schema::table('marketplace_orders', function (Blueprint $table): void {
            $indexes = collect(Schema::getIndexes('marketplace_orders'));
            if ($indexes->contains('name', 'orders_user_product_item_unique')) {
                $table->dropUnique('orders_user_product_item_unique');
            }
            $table->unique(
                ['user_id', 'order_number', 'product_key', 'variation_key', 'unit_price', 'quantity'],
                'orders_line_identity_unique'
            );
        });

        Schema::table('marketplace_income', function (Blueprint $table): void {
            $indexes = collect(Schema::getIndexes('marketplace_income'));
            if ($indexes->contains('name', 'income_user_product_item_unique')) {
                $table->dropUnique('income_user_product_item_unique');
            }
            $table->unique(
                ['user_id', 'order_number', 'product_key', 'variation_key', 'unit_price', 'quantity'],
                'income_line_identity_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_orders', function (Blueprint $table): void {
            $table->dropUnique('orders_line_identity_unique');
            $table->dropColumn(['variation_key', 'unit_price']);
        });

        Schema::table('marketplace_income', function (Blueprint $table): void {
            $table->dropUnique('income_line_identity_unique');
            $table->dropColumn(['variation_key', 'unit_price', 'quantity']);
        });
    }
};
