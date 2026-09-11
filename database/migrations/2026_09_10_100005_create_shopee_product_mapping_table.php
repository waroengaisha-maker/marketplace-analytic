<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shopee_product_mapping', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('master_product_id')->constrained('master_products')->cascadeOnDelete();
            $table->foreignId('master_unit_id')->nullable()->constrained('master_product_units')->nullOnDelete();
            $table->string('shopee_product_id', 128)->nullable();
            $table->string('shopee_variant_id', 128)->nullable();
            $table->text('shopee_product_name')->nullable();
            $table->text('shopee_variant_name')->nullable();
            $table->string('normalized_shopee_name', 255)->nullable();
            $table->string('match_method', 32);
            $table->decimal('match_confidence', 5, 2)->default(0.00);
            $table->boolean('is_active')->default(true);
            $table->boolean('ambiguous')->default(false);
            $table->foreignId('manual_override_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('manual_override_note')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'master_product_id'], 'shopee_product_mapping_user_product_index');
            $table->index(['user_id', 'shopee_product_id', 'shopee_variant_id'], 'shopee_product_mapping_user_identifier_index');
            $table->index(['match_method', 'ambiguous', 'is_active'], 'shopee_product_mapping_status_index');
            $table->unique(['user_id', 'shopee_product_id', 'shopee_variant_id'], 'shopee_product_mapping_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopee_product_mapping');
    }
};
