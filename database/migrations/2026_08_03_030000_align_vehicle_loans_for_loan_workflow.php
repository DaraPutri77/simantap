<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const LOAN_TABLE = 'vehicle_loans';

    private const HISTORY_TABLE = 'vehicle_loan_status_histories';

    public function up(): void
    {
        if (! Schema::hasTable(self::LOAN_TABLE)) {
            throw new RuntimeException(
                'Tabel vehicle_loans harus tersedia sebelum workflow peminjaman diselaraskan.',
            );
        }

        $this->addMissingLoanColumns();
        $this->ensureLoanForeignKeys();
        $this->backfillLoanSnapshots();
        $this->ensureLoanIndexes();
        $this->ensureStatusHistoryTable();
    }

    public function down(): void
    {
        Schema::dropIfExists(self::HISTORY_TABLE);

        if (! Schema::hasTable(self::LOAN_TABLE)) {
            return;
        }

        if (Schema::hasIndex(
            self::LOAN_TABLE,
            'vehicle_loan_status_schedule_index',
        )) {
            Schema::table(
                self::LOAN_TABLE,
                static function (Blueprint $table): void {
                    $table->dropIndex(
                        'vehicle_loan_status_schedule_index',
                    );
                },
            );
        }

        if (Schema::hasIndex(
            self::LOAN_TABLE,
            'vehicle_loans_public_id_unique',
        )) {
            Schema::table(
                self::LOAN_TABLE,
                static function (Blueprint $table): void {
                    $table->dropUnique(
                        'vehicle_loans_public_id_unique',
                    );
                },
            );
        }

        if (Schema::hasColumn(self::LOAN_TABLE, 'reviewed_by')) {
            Schema::table(
                self::LOAN_TABLE,
                static function (Blueprint $table): void {
                    if (Schema::hasForeignKey(
                        self::LOAN_TABLE,
                        ['reviewed_by'],
                    )) {
                        $table->dropForeign(['reviewed_by']);
                    }
                },
            );
        }

        $this->dropLoanColumnsIfPresent([
            'public_id',
            'borrower_name_snapshot',
            'employee_number_snapshot',
            'work_unit_snapshot',
            'vehicle_code_snapshot',
            'license_plate_snapshot',
            'vehicle_name_snapshot',
            'submitted_at',
            'reviewed_by',
            'reviewed_at',
            'rejected_at',
            'cancelled_at',
            'cancellation_reason',
            'admin_notes',
        ]);
    }

    private function addMissingLoanColumns(): void
    {
        $this->addLoanColumnIfMissing(
            'public_id',
            static fn (Blueprint $table) => $table
                ->uuid('public_id')
                ->nullable()
                ->after('id'),
        );
        $this->addLoanColumnIfMissing(
            'borrower_name_snapshot',
            static fn (Blueprint $table) => $table
                ->string('borrower_name_snapshot')
                ->nullable()
                ->after('borrower_id'),
        );
        $this->addLoanColumnIfMissing(
            'employee_number_snapshot',
            static fn (Blueprint $table) => $table
                ->string('employee_number_snapshot', 50)
                ->nullable()
                ->after('borrower_name_snapshot'),
        );
        $this->addLoanColumnIfMissing(
            'work_unit_snapshot',
            static fn (Blueprint $table) => $table
                ->string('work_unit_snapshot')
                ->nullable()
                ->after('employee_number_snapshot'),
        );
        $this->addLoanColumnIfMissing(
            'vehicle_code_snapshot',
            static fn (Blueprint $table) => $table
                ->string('vehicle_code_snapshot', 80)
                ->nullable()
                ->after('vehicle_id'),
        );
        $this->addLoanColumnIfMissing(
            'license_plate_snapshot',
            static fn (Blueprint $table) => $table
                ->string('license_plate_snapshot', 30)
                ->nullable()
                ->after('vehicle_code_snapshot'),
        );
        $this->addLoanColumnIfMissing(
            'vehicle_name_snapshot',
            static fn (Blueprint $table) => $table
                ->string('vehicle_name_snapshot')
                ->nullable()
                ->after('license_plate_snapshot'),
        );
        $this->addLoanColumnIfMissing(
            'submitted_at',
            static fn (Blueprint $table) => $table
                ->timestamp('submitted_at')
                ->nullable()
                ->after('status'),
        );
        $this->addLoanColumnIfMissing(
            'reviewed_by',
            static fn (Blueprint $table) => $table
                ->foreignId('reviewed_by')
                ->nullable()
                ->after('submitted_at')
                ->constrained('users')
                ->nullOnDelete(),
        );
        $this->addLoanColumnIfMissing(
            'reviewed_at',
            static fn (Blueprint $table) => $table
                ->timestamp('reviewed_at')
                ->nullable()
                ->after('reviewed_by'),
        );
        $this->addLoanColumnIfMissing(
            'rejected_at',
            static fn (Blueprint $table) => $table
                ->timestamp('rejected_at')
                ->nullable()
                ->after('approved_at'),
        );
        $this->addLoanColumnIfMissing(
            'cancelled_at',
            static fn (Blueprint $table) => $table
                ->timestamp('cancelled_at')
                ->nullable()
                ->after('rejection_reason'),
        );
        $this->addLoanColumnIfMissing(
            'cancellation_reason',
            static fn (Blueprint $table) => $table
                ->text('cancellation_reason')
                ->nullable()
                ->after('cancelled_at'),
        );
        $this->addLoanColumnIfMissing(
            'admin_notes',
            static fn (Blueprint $table) => $table
                ->text('admin_notes')
                ->nullable()
                ->after('cancellation_reason'),
        );
    }

    private function ensureLoanForeignKeys(): void
    {
        if (Schema::hasForeignKey(self::LOAN_TABLE, ['reviewed_by'])) {
            return;
        }

        Schema::table(
            self::LOAN_TABLE,
            static function (Blueprint $table): void {
                $table->foreign('reviewed_by')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            },
        );
    }

    private function backfillLoanSnapshots(): void
    {
        $seenPublicIds = [];

        DB::table(self::LOAN_TABLE)
            ->select([
                'id',
                'public_id',
                'borrower_id',
                'borrower_name_snapshot',
                'employee_number_snapshot',
                'work_unit_snapshot',
                'vehicle_id',
                'vehicle_code_snapshot',
                'license_plate_snapshot',
                'vehicle_name_snapshot',
            ])
            ->orderBy('id')
            ->eachById(static function (object $loan) use (
                &$seenPublicIds,
            ): void {
                $publicId = trim((string) $loan->public_id);
                $publicIdKey = strtolower($publicId);

                if (
                    $publicId === ''
                    || isset($seenPublicIds[$publicIdKey])
                ) {
                    do {
                        $publicId = (string) Str::uuid();
                        $publicIdKey = strtolower($publicId);
                    } while (isset($seenPublicIds[$publicIdKey]));
                }

                $seenPublicIds[$publicIdKey] = true;
                $borrower = DB::table('users')
                    ->where('id', $loan->borrower_id)
                    ->first([
                        'name',
                        'employee_number',
                        'work_unit',
                    ]);
                $vehicle = DB::table('vehicles')
                    ->where('id', $loan->vehicle_id)
                    ->first([
                        'vehicle_code',
                        'license_plate',
                        'brand',
                        'model',
                    ]);

                DB::table(self::LOAN_TABLE)
                    ->where('id', $loan->id)
                    ->update([
                        'public_id' => $publicId,
                        'borrower_name_snapshot' => filled(
                            $loan->borrower_name_snapshot,
                        )
                            ? $loan->borrower_name_snapshot
                            : $borrower?->name,
                        'employee_number_snapshot' => filled(
                            $loan->employee_number_snapshot,
                        )
                            ? $loan->employee_number_snapshot
                            : $borrower?->employee_number,
                        'work_unit_snapshot' => filled(
                            $loan->work_unit_snapshot,
                        )
                            ? $loan->work_unit_snapshot
                            : $borrower?->work_unit,
                        'vehicle_code_snapshot' => filled(
                            $loan->vehicle_code_snapshot,
                        )
                            ? $loan->vehicle_code_snapshot
                            : $vehicle?->vehicle_code,
                        'license_plate_snapshot' => filled(
                            $loan->license_plate_snapshot,
                        )
                            ? $loan->license_plate_snapshot
                            : $vehicle?->license_plate,
                        'vehicle_name_snapshot' => filled(
                            $loan->vehicle_name_snapshot,
                        )
                            ? $loan->vehicle_name_snapshot
                            : trim(implode(' ', array_filter([
                                $vehicle?->brand,
                                $vehicle?->model,
                            ]))),
                    ]);
            });
    }

    private function ensureLoanIndexes(): void
    {
        if (! $this->hasUniquePublicIdIndex()) {
            Schema::table(
                self::LOAN_TABLE,
                static function (Blueprint $table): void {
                    $table->unique('public_id');
                },
            );
        }

        if (! Schema::hasIndex(
            self::LOAN_TABLE,
            [
                'status',
                'planned_start_at',
                'planned_end_at',
            ],
        )) {
            Schema::table(
                self::LOAN_TABLE,
                static function (Blueprint $table): void {
                    $table->index(
                        [
                            'status',
                            'planned_start_at',
                            'planned_end_at',
                        ],
                        'vehicle_loan_status_schedule_index',
                    );
                },
            );
        }
    }

    private function ensureStatusHistoryTable(): void
    {
        if (! Schema::hasTable(self::HISTORY_TABLE)) {
            Schema::create(
                self::HISTORY_TABLE,
                static function (Blueprint $table): void {
                    $table->id();
                    $table->foreignId('vehicle_loan_id')
                        ->constrained(self::LOAN_TABLE)
                        ->cascadeOnDelete();
                    $table->string('previous_status', 40)->nullable();
                    $table->string('new_status', 40);
                    $table->text('notes')->nullable();
                    $table->foreignId('changed_by')
                        ->nullable()
                        ->constrained('users')
                        ->nullOnDelete();
                    $table->timestamp('changed_at')->useCurrent();

                    $table->index(
                        ['vehicle_loan_id', 'changed_at'],
                        'vehicle_loan_status_history_timeline_index',
                    );
                },
            );

            return;
        }

        $this->addHistoryColumnIfMissing(
            'vehicle_loan_id',
            static fn (Blueprint $table) => $table
                ->foreignId('vehicle_loan_id'),
        );
        $this->addHistoryColumnIfMissing(
            'previous_status',
            static fn (Blueprint $table) => $table
                ->string('previous_status', 40)
                ->nullable(),
        );
        $this->addHistoryColumnIfMissing(
            'new_status',
            static fn (Blueprint $table) => $table
                ->string('new_status', 40)
                ->default('draft'),
        );
        $this->addHistoryColumnIfMissing(
            'notes',
            static fn (Blueprint $table) => $table
                ->text('notes')
                ->nullable(),
        );
        $this->addHistoryColumnIfMissing(
            'changed_by',
            static fn (Blueprint $table) => $table
                ->foreignId('changed_by')
                ->nullable(),
        );
        $this->addHistoryColumnIfMissing(
            'changed_at',
            static fn (Blueprint $table) => $table
                ->timestamp('changed_at')
                ->useCurrent(),
        );

        if (! Schema::hasForeignKey(
            self::HISTORY_TABLE,
            ['vehicle_loan_id'],
        )) {
            Schema::table(
                self::HISTORY_TABLE,
                static function (Blueprint $table): void {
                    $table->foreign('vehicle_loan_id')
                        ->references('id')
                        ->on(self::LOAN_TABLE)
                        ->cascadeOnDelete();
                },
            );
        }

        if (! Schema::hasForeignKey(
            self::HISTORY_TABLE,
            ['changed_by'],
        )) {
            Schema::table(
                self::HISTORY_TABLE,
                static function (Blueprint $table): void {
                    $table->foreign('changed_by')
                        ->references('id')
                        ->on('users')
                        ->nullOnDelete();
                },
            );
        }

        if (! Schema::hasIndex(
            self::HISTORY_TABLE,
            ['vehicle_loan_id', 'changed_at'],
        )) {
            Schema::table(
                self::HISTORY_TABLE,
                static function (Blueprint $table): void {
                    $table->index(
                        ['vehicle_loan_id', 'changed_at'],
                        'vehicle_loan_status_history_timeline_index',
                    );
                },
            );
        }
    }

    private function addLoanColumnIfMissing(
        string $column,
        Closure $definition,
    ): void {
        if (Schema::hasColumn(self::LOAN_TABLE, $column)) {
            return;
        }

        Schema::table(
            self::LOAN_TABLE,
            static function (Blueprint $table) use ($definition): void {
                $definition($table);
            },
        );
    }

    private function addHistoryColumnIfMissing(
        string $column,
        Closure $definition,
    ): void {
        if (Schema::hasColumn(self::HISTORY_TABLE, $column)) {
            return;
        }

        Schema::table(
            self::HISTORY_TABLE,
            static function (Blueprint $table) use ($definition): void {
                $definition($table);
            },
        );
    }

    /**
     * @param  list<string>  $columns
     */
    private function dropLoanColumnsIfPresent(array $columns): void
    {
        $existingColumns = array_values(array_filter(
            $columns,
            static fn (string $column): bool => Schema::hasColumn(
                self::LOAN_TABLE,
                $column,
            ),
        ));

        if ($existingColumns === []) {
            return;
        }

        Schema::table(
            self::LOAN_TABLE,
            static function (Blueprint $table) use (
                $existingColumns,
            ): void {
                $table->dropColumn($existingColumns);
            },
        );
    }

    private function hasUniquePublicIdIndex(): bool
    {
        foreach (Schema::getIndexes(self::LOAN_TABLE) as $index) {
            if (
                ($index['columns'] ?? []) === ['public_id']
                && ($index['unique'] ?? false) === true
            ) {
                return true;
            }
        }

        return false;
    }
};
