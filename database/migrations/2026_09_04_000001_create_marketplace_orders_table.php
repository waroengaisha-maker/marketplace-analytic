<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_orders', function (Blueprint $table): void {
            $table->id();
            $table->string('order_number')->index();
            $table->string('order_status')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->string('return_status')->nullable();
            $table->string('tracking_number')->nullable();
            $table->string('shipping_option')->nullable();
            $table->string('order_type')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('parent_sku')->nullable();
            $table->text('product_name')->nullable();
            $table->string('sku_reference')->nullable();
            $table->string('variation_name')->nullable();
            $table->decimal('original_price', 18, 2)->nullable();
            $table->decimal('discounted_price', 18, 2)->nullable();
            $table->unsignedInteger('quantity')->nullable();
            $table->unsignedInteger('returned_quantity')->nullable();
            $table->decimal('order_subtotal', 18, 2)->nullable();
            $table->decimal('total_payment', 18, 2)->nullable();
            $table->decimal('buyer_shipping_paid', 18, 2)->nullable();
            $table->decimal('estimated_shipping_discount', 18, 2)->nullable();
            $table->decimal('estimated_shipping_cost', 18, 2)->nullable();
            $table->unsignedInteger('product_count')->nullable();
            $table->decimal('total_weight', 18, 3)->nullable();
            $table->string('buyer_username')->nullable();
            $table->string('recipient_name')->nullable();
            $table->string('buyer_phone')->nullable();
            $table->text('shipping_address')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->timestamp('order_created_at')->nullable();
            $table->timestamp('payment_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('raw_data');
            $table->timestamps();
            $table->unique(['order_number', 'parent_sku', 'variation_name']);
            $table->index('order_created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_orders');
    }
};