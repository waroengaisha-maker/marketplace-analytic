<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_income', function (Blueprint $table): void {
            $table->id();
            $table->string('order_number')->index();
            $table->string('row_type')->nullable();
            $table->string('application_number')->nullable();
            $table->string('product_id')->nullable();
            $table->text('product_name')->nullable();
            $table->timestamp('order_created_at')->nullable();
            $table->timestamp('fund_released_at')->nullable();
            $table->string('release_method')->nullable();
            $table->string('order_type')->nullable();
            $table->decimal('total_income', 18, 2)->nullable();
            $table->decimal('product_price', 18, 2)->nullable();
            $table->decimal('buyer_shipping_paid', 18, 2)->nullable();
            $table->decimal('platform_fee', 18, 2)->nullable();
            $table->decimal('order_processing_fee', 18, 2)->nullable();
            $table->decimal('shipping_fee', 18, 2)->nullable();
            $table->decimal('service_fee', 18, 2)->nullable();
            $table->decimal('promotion_fee', 18, 2)->nullable();
            $table->decimal('other_fee', 18, 2)->nullable();
            $table->decimal('refund_to_buyer', 18, 2)->nullable();
            $table->string('buyer_username')->nullable();
            $table->decimal('buyer_paid_amount', 18, 2)->nullable();
            $table->string('buyer_payment_method')->nullable();
            $table->string('shipping_provider')->nullable();
            $table->string('voucher_code')->nullable();
            $table->json('raw_data');
            $table->timestamps();
            $table->index('fund_released_at');
            $table->index(['order_number', 'row_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_income');
    }
};