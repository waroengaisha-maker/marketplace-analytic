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

        // MySQL rejects DELETE statements that read from the same target table
        // through a subquery (ERROR 1093). The extra derived-table layer makes
        // the duplicate-id set materialized before the DELETE executes.
        DB::statement(<<<'SQL'
            DELETE FROM marketplace_income
            WHERE id IN (
                SELECT id
                FROM (
                    SELECT duplicate.id
                    FROM marketplace_income AS duplicate
                    INNER JOIN marketplace_income AS keeper
                        ON keeper.user_id = duplicate.user_id
                        AND keeper.line_identity = duplicate.line_identity
                        AND keeper.refund_event_identity = duplicate.refund_event_identity
                        AND keeper.id < duplicate.id
                    WHERE duplicate.application_number IS NOT NULL
                      AND duplicate.application_number <> ''
                ) AS duplicate_ids
            )
        SQL);

        Schema::table('marketplace_income', function (Blueprint $table): void {
            $indexes = collect(Schema::getIndexes('marketplace_income'));
            foreach (['income_user_line_identity_unique', 'income_line_identity_unique'] as $legacyIndex) {
                if ($indexes->contains('name', $legacyIndex)) {
                    $table->dropUnique($legacyIndex);
                }
            }
            if (! $indexes->contains('name', 'income_user_line_event_identity_unique')) {
                $table->unique(['user_id', 'line_identity', 'refund_event_identity'], 'income_user_line_event_identity_unique');
            }
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
            $table->unique(['user_id', 'line_identity'], 'income_line_identity_unique');
        });
    }
};
