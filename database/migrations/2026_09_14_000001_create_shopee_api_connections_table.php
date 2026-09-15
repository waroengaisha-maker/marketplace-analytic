<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shopee_api_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('environment', 20)->default('production');
            $table->string('region', 60)->default('global');
            $table->text('partner_id')->nullable();
            $table->text('partner_key')->nullable();
            $table->text('shop_id')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('access_token_expires_at')->nullable();
            $table->timestamp('refresh_token_expires_at')->nullable();
            $table->string('shop_name', 255)->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->string('last_sync_status', 40)->nullable();
            $table->text('last_sync_error')->nullable();
            $table->string('last_sync_order_cursor', 255)->nullable();
            $table->json('staging_orders')->nullable();
            $table->json('staging_income')->nullable();
            $table->timestamps();

            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopee_api_connections');
    }
};
