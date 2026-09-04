<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('marketplace_orders', 'item_index')) {
                $table->unsignedInteger('item_index')->nullable()->after('order_number');
            }
        });

        Schema::table('marketplace_income', function (Blueprint $table): void {
            if (! Schema::hasColumn('marketplace_income', 'item_index')) {
                $table->unsignedInteger('item_index')->nullable()->after('order_number');
            }
            $table->unique(['user_id', 'order_number', 'item_index'], 'income_user_item_unique');
        });
    }

    public function down(): void {}
};