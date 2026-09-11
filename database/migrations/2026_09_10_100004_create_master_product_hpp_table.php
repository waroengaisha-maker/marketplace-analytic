<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_product_hpp', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('master_product_id')->constrained('master_products')->cascadeOnDelete();
            $table->foreignId('master_unit_id')->constrained('master_product_units')->restrictOnDelete();
            $table->decimal('hpp_amount', 18, 2);
            $table->decimal('hpp_per_base_unit', 18, 6);
            $table->dateTime('effective_from');
            $table->dateTime('effective_to')->nullable();
            $table->string('source_type', 32);
            $table->string('source_reference', 255)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['master_product_id', 'master_unit_id', 'effective_from'], 'master_product_hpp_unique');
            $table->index(['master_product_id', 'master_unit_id', 'effective_from', 'effective_to'], 'master_product_hpp_period_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_product_hpp');
    }
};
