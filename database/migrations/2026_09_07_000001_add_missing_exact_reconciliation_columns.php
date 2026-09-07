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

            if (! Schema::hasColumn('marketplace_orders', 'variation_key')) {
                $table->string('variation_key', 64)->nullable()->after('variation_name');
            }

            if (! Schema::hasColumn('marketplace_orders', 'unit_price')) {
                $table->decimal('unit_price', 18, 2)->nullable()->after('discounted_price');
            }
        });

        Schema::table('marketplace_income', function (Blueprint $table): void {
            if (! Schema::hasColumn('marketplace_income', 'product_key')) {
                $table->string('product_key', 64)->nullable()->after('product_name');
            }

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
    }

    public function down(): void
    {
        Schema::table('marketplace_orders', function (Blueprint $table): void {
            foreach (['product_key', 'variation_key', 'unit_price'] as $column) {
                if (Schema::hasColumn('marketplace_orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('marketplace_income', function (Blueprint $table): void {
            foreach (['product_key', 'variation_key', 'unit_price', 'quantity'] as $column) {
                if (Schema::hasColumn('marketplace_income', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
