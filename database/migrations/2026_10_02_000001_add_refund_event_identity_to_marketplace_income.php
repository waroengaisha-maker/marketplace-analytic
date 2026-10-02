<?php

use App\Services\RefundEventIdentity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_income', function (Blueprint $table): void {
            if (! Schema::hasColumn('marketplace_income', 'refund_event_identity')) {
                $table->string('refund_event_identity', 64)->nullable()->after('line_identity');
            }

            $indexes = collect(Schema::getIndexes('marketplace_income'));
            if ($indexes->contains('name', 'income_user_line_identity_unique')) {
                $table->dropUnique('income_user_line_identity_unique');
            }

            if (! $indexes->contains('name', 'income_user_line_event_identity_unique')) {
                $table->unique(['user_id', 'line_identity', 'refund_event_identity'], 'income_user_line_event_identity_unique');
            }
        });

        DB::table('marketplace_income')
            ->whereNotNull('application_number')
            ->where('application_number', '<>', '')
            ->orderBy('id')
            ->each(function (object $row): void {
                DB::table('marketplace_income')->where('id', $row->id)->update([
                    'refund_event_identity' => RefundEventIdentity::make((string) $row->order_number, (string) $row->application_number),
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('marketplace_income', function (Blueprint $table): void {
            $indexes = collect(Schema::getIndexes('marketplace_income'));
            if ($indexes->contains('name', 'income_user_line_event_identity_unique')) {
                $table->dropUnique('income_user_line_event_identity_unique');
            }
            if (Schema::hasColumn('marketplace_income', 'refund_event_identity')) {
                $table->dropColumn('refund_event_identity');
            }
            $table->unique(['user_id', 'line_identity'], 'income_user_line_identity_unique');
        });
    }
};
