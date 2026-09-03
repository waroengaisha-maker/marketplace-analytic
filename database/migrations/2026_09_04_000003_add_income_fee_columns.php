<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_income', function (Blueprint $table): void {
            $table->decimal('free_shipping_xtra_fee', 18, 2)->nullable()->after('order_processing_fee');
            $table->decimal('promo_xtra_service_fee', 18, 2)->nullable()->after('service_fee');
            $table->decimal('pph22', 18, 2)->nullable()->after('other_fee');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_income', function (Blueprint $table): void {
            $table->dropColumn(['free_shipping_xtra_fee', 'promo_xtra_service_fee', 'pph22']);
        });
    }
};