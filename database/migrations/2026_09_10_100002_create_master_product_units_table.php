<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_product_units', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('master_product_id')->constrained('master_products')->cascadeOnDelete();
            $table->string('unit_code', 32);
            $table->string('unit_name', 64)->nullable();
            $table->decimal('conversion_to_base', 18, 6)->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['master_product_id', 'unit_code'], 'master_product_units_product_code_unique');
            $table->index(['master_product_id', 'is_active'], 'master_product_units_product_active_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_product_units');
    }
};
