<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_income', function (Blueprint $table): void {
            $indexes = collect(Schema::getIndexes('marketplace_income'));

            if ($indexes->contains('name', 'income_user_item_unique')) {
                $table->dropUnique('income_user_item_unique');
            }

            if ($indexes->contains('name', 'income_user_product_item_unique')) {
                $table->dropUnique('income_user_product_item_unique');
            }
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_income', function (Blueprint $table): void {
            $table->unique(['user_id', 'order_number', 'item_index'], 'income_user_item_unique');
        });
    }
};
