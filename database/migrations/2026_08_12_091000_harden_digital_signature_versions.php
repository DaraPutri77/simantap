<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('digital_signatures')) {
            return;
        }

        $hasContextConflicts = DB::table('digital_signatures')
            ->select([
                'signable_type',
                'signable_id',
                'purpose',
            ])
            ->groupBy(
                'signable_type',
                'signable_id',
                'purpose',
            )
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasContextConflicts) {
            throw new RuntimeException(
                'Migrasi dibatalkan karena terdapat lebih dari satu tanda '
                .'tangan untuk signable dan purpose yang sama. Data harus '
                .'direkonsiliasi sebelum versioning append-only diaktifkan.',
            );
        }

        Schema::table('digital_signatures', function (Blueprint $table): void {
            $table->unsignedInteger('version')->default(1);
        });

        Schema::table('digital_signatures', function (Blueprint $table): void {
            $table->dropUnique('digital_signatures_context_unique');
            $table->unique(
                [
                    'signable_type',
                    'signable_id',
                    'purpose',
                    'version',
                ],
                'digital_signatures_context_version_unique',
            );
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                'ALTER TABLE `digital_signatures` '
                .'MODIFY `signed_at` DATETIME NOT NULL',
            );
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('digital_signatures')) {
            return;
        }

        $hasVersionConflicts = DB::table('digital_signatures')
            ->select([
                'signable_type',
                'signable_id',
                'signer_id',
                'purpose',
            ])
            ->groupBy(
                'signable_type',
                'signable_id',
                'signer_id',
                'purpose',
            )
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasVersionConflicts) {
            throw new RuntimeException(
                'Rollback dibatalkan karena terdapat lebih dari satu versi '
                .'tanda tangan untuk konteks signer yang sama.',
            );
        }

        Schema::table('digital_signatures', function (Blueprint $table): void {
            $table->dropUnique(
                'digital_signatures_context_version_unique',
            );
        });

        Schema::table('digital_signatures', function (Blueprint $table): void {
            $table->dropColumn('version');
            $table->unique(
                [
                    'signable_type',
                    'signable_id',
                    'signer_id',
                    'purpose',
                ],
                'digital_signatures_context_unique',
            );
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                'ALTER TABLE `digital_signatures` '
                .'MODIFY `signed_at` TIMESTAMP NOT NULL '
                .'DEFAULT CURRENT_TIMESTAMP '
                .'ON UPDATE CURRENT_TIMESTAMP',
            );
        }
    }
};
