<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_item_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kode_item', 64);
            $table->string('barcode', 128)->nullable();
            $table->string('sku', 128)->nullable();
            $table->string('nama_item', 255)->nullable();
            $table->string('jenis', 128)->nullable();
            $table->string('merek', 128)->nullable();
            $table->string('rak', 128)->nullable();
            $table->decimal('conversion_to_base', 18, 6)->default(1);
            $table->string('tipe_item', 32)->nullable();
            $table->string('satuan', 32);
            $table->decimal('hpp_amount', 18, 2)->default(0);
            $table->decimal('harga_jual', 18, 2)->nullable();
            $table->text('keterangan')->nullable();
            $table->unsignedInteger('poin')->default(0);
            $table->unsignedInteger('komisi_sales')->default(0);
            $table->string('source_file', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'kode_item', 'satuan'], 'template_item_rows_user_kode_satuan_unique');
            $table->index(['user_id', 'kode_item'], 'template_item_rows_user_kode_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_item_rows');
    }
};
