<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_income', function (Blueprint $table): void {
            if (! Schema::hasColumn('marketplace_income', 'source_row')) {
                $table->unsignedInteger('source_row')->nullable()->after('order_number');
            }
            $table->dropForeign(['user_id']);
            $table->dropUnique('income_user_scope_unique');
            $table->unique(['user_id', 'order_number', 'row_type', 'source_row'], 'income_user_row_unique');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void {}
};