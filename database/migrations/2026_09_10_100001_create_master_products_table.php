<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('template_item_code', 64);
            $table->text('template_name')->nullable();
            $table->string('normalized_name', 255)->nullable();
            $table->string('status', 32)->default('active');
            $table->timestamps();

            $table->unique(['user_id', 'template_item_code'], 'master_products_user_template_unique');
            $table->index(['user_id', 'normalized_name'], 'master_products_user_normalized_name_index');
            $table->index('status', 'master_products_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_products');
    }
};
