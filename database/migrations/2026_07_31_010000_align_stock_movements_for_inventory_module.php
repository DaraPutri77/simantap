<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('stock_movements')) {
            return;
        }

        $missingColumns = collect([
            'transaction_number',
            'movement_type',
            'reference_type',
            'reference_id',
            'quantity_in',
            'quantity_out',
            'stock_before',
            'stock_after',
            'transaction_date',
            'description',
            'created_by',
            'created_at',
            'updated_at',
        ])->reject(
            static fn (string $column): bool => Schema::hasColumn(
                'stock_movements',
                $column,
            ),
        )->all();

        if ($missingColumns !== []) {
            Schema::table(
                'stock_movements',
                function (Blueprint $table) use ($missingColumns): void {
                    if (in_array(
                        'transaction_number',
                        $missingColumns,
                        true,
                    )) {
                        $table
                            ->string('transaction_number', 80)
                            ->nullable()
                            ->unique();
                    }

                    if (in_array('movement_type', $missingColumns, true)) {
                        $table
                            ->string('movement_type', 40)
                            ->nullable()
                            ->index();
                    }

                    if (in_array('reference_type', $missingColumns, true)) {
                        $table
                            ->string('reference_type')
                            ->nullable();
                    }

                    if (in_array('reference_id', $missingColumns, true)) {
                        $table
                            ->unsignedBigInteger('reference_id')
                            ->nullable();
                    }

                    if (in_array('quantity_in', $missingColumns, true)) {
                        $table
                            ->decimal('quantity_in', 15, 2)
                            ->default(0);
                    }

                    if (in_array('quantity_out', $missingColumns, true)) {
                        $table
                            ->decimal('quantity_out', 15, 2)
                            ->default(0);
                    }

                    if (in_array('stock_before', $missingColumns, true)) {
                        $table
                            ->decimal('stock_before', 15, 2)
                            ->nullable();
                    }

                    if (in_array('stock_after', $missingColumns, true)) {
                        $table
                            ->decimal('stock_after', 15, 2)
                            ->nullable();
                    }

                    if (in_array('transaction_date', $missingColumns, true)) {
                        $table
                            ->dateTime('transaction_date')
                            ->nullable()
                            ->index();
                    }

                    if (in_array('description', $missingColumns, true)) {
                        $table
                            ->text('description')
                            ->nullable();
                    }

                    if (in_array('created_by', $missingColumns, true)) {
                        $table
                            ->unsignedBigInteger('created_by')
                            ->nullable();
                    }

                    if (in_array('created_at', $missingColumns, true)) {
                        $table
                            ->timestamp('created_at')
                            ->nullable();
                    }

                    if (in_array('updated_at', $missingColumns, true)) {
                        $table
                            ->timestamp('updated_at')
                            ->nullable();
                    }
                },
            );
        }

        $legacyNumberColumn = collect([
            'movement_number',
            'reference_number',
        ])->first(
            static fn (string $column): bool => Schema::hasColumn(
                'stock_movements',
                $column,
            ),
        );

        DB::table('stock_movements')
            ->select([
                'id',
                ...($legacyNumberColumn === null
                    ? []
                    : [$legacyNumberColumn]),
            ])
            ->whereNull('transaction_number')
            ->orderBy('id')
            ->chunkById(
                100,
                static function ($movements) use (
                    $legacyNumberColumn,
                ): void {
                    foreach ($movements as $movement) {
                        $legacyNumber = $legacyNumberColumn === null
                            ? ''
                            : trim(
                                (string) $movement
                                    ->{$legacyNumberColumn},
                            );
                        $transactionNumber = $legacyNumber !== ''
                            ? $legacyNumber
                            : sprintf(
                                'LEGACY-STK/%010d',
                                $movement->id,
                            );

                        DB::table('stock_movements')
                            ->where('id', $movement->id)
                            ->update([
                                'transaction_number' => $transactionNumber,
                            ]);
                    }
                },
                'id',
            );

        DB::table('stock_movements')
            ->where('movement_type', 'return')
            ->update([
                'movement_type' => 'return_in',
            ]);

        DB::table('stock_movements')
            ->where('movement_type', 'damaged')
            ->update([
                'movement_type' => 'damaged_out',
            ]);
    }

    public function down(): void
    {
        // Compatibility alignment intentionally preserves ledger data.
    }
};
