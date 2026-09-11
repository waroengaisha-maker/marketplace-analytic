<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_cost_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('order_line_identity', 64);
            $table->foreignId('master_product_id')->nullable()->constrained('master_products')->nullOnDelete();
            $table->foreignId('master_unit_id')->nullable()->constrained('master_product_units')->nullOnDelete();
            $table->foreignId('effective_hpp_record_id')->nullable()->constrained('master_product_hpp')->nullOnDelete();
            $table->decimal('hpp_per_base_unit', 18, 6);
            $table->decimal('quantity_base_unit', 18, 6);
            $table->decimal('total_hpp', 18, 2);
            $table->string('cost_status', 32)->default('ok');
            $table->timestamps();

            $table->unique(['user_id', 'order_line_identity'], 'order_cost_allocations_user_line_unique');
            $table->index(['user_id', 'master_product_id'], 'order_cost_allocations_user_product_index');
            $table->index('effective_hpp_record_id', 'order_cost_allocations_hpp_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_cost_allocations');
    }
};
