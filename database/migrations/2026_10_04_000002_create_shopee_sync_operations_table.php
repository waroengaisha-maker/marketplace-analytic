<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shopee_sync_operations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('connection_id')->constrained('shopee_api_connections')->cascadeOnDelete();
            $table->string('operation', 20);
            $table->string('status', 20)->index();
            $table->string('fingerprint', 64);
            $table->json('options')->nullable();
            $table->json('result')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'operation', 'fingerprint']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopee_sync_operations');
    }
};
