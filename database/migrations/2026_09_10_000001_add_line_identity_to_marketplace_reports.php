<?php

use App\Services\ReportLineIdentity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['marketplace_orders', 'marketplace_income'] as $tableName) {
            if (! Schema::hasColumn($tableName, 'line_identity')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->string('line_identity', 64)->nullable()->after('item_index');
                });
            }

            if (DB::table($tableName)->whereNull('line_identity')->exists()) {
                throw new RuntimeException("Cannot add a unique line_identity index to {$tableName} while existing rows still have a null line_identity.");
            }

            $priceColumns = $tableName === 'marketplace_income'
                ? ['unit_price', 'product_price']
                : ['unit_price', 'discounted_price'];

            DB::table($tableName)
                ->select(array_merge(['id', 'order_number', 'product_key', 'variation_key', 'quantity'], $priceColumns))
                ->whereNull('line_identity')
                ->orderBy('id')
                ->each(function (object $row) use ($tableName): void {
                    $unitPrice = $row->unit_price ?? $row->product_price ?? $row->discounted_price ?? null;
                    $identity = ReportLineIdentity::make(
                        (string) $row->order_number,
                        $row->product_key,
                        $row->variation_key,
                        $unitPrice === null ? null : (float) $unitPrice,
                        $row->quantity === null ? null : (int) $row->quantity,
                    );

                    DB::table($tableName)->where('id', $row->id)->update(['line_identity' => $identity]);
                });
        }

        Schema::table('marketplace_orders', function (Blueprint $table): void {
            $table->unique(['user_id', 'line_identity'], 'orders_user_line_identity_unique');
        });

        Schema::table('marketplace_income', function (Blueprint $table): void {
            $table->unique(['user_id', 'line_identity'], 'income_user_line_identity_unique');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_orders', function (Blueprint $table): void {
            $table->dropUnique('orders_user_line_identity_unique');
        });

        Schema::table('marketplace_income', function (Blueprint $table): void {
            $table->dropUnique('income_user_line_identity_unique');
        });

        foreach (['marketplace_orders', 'marketplace_income'] as $tableName) {
            if (Schema::hasColumn($tableName, 'line_identity')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->dropColumn('line_identity');
                });
            }
        }
    }
};
