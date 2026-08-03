<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'vehicle_loans';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            throw new RuntimeException(
                'Tabel vehicle_loans harus tersedia sebelum kontrak peminjaman direkonsiliasi.',
            );
        }

        $this->addColumnIfMissing(
            'overdue_at',
            static fn (Blueprint $table) => $table
                ->timestamp('overdue_at')
                ->nullable(),
        );
        $this->addColumnIfMissing(
            'approved_by',
            static fn (Blueprint $table) => $table
                ->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete(),
        );
        $this->addColumnIfMissing(
            'approved_at',
            static fn (Blueprint $table) => $table
                ->timestamp('approved_at')
                ->nullable(),
        );
        $this->addColumnIfMissing(
            'rejection_reason',
            static fn (Blueprint $table) => $table
                ->text('rejection_reason')
                ->nullable(),
        );
        $this->addColumnIfMissing(
            'notes',
            static fn (Blueprint $table) => $table
                ->text('notes')
                ->nullable(),
        );

        $this->ensureApproverForeignKey();
    }

    public function down(): void
    {
        // Kolom-kolom ini merupakan kontrak lintas versi dan mungkin sudah
        // tersedia sebelum migration ini. Rollback sengaja non-destruktif.
    }

    private function addColumnIfMissing(
        string $column,
        Closure $definition,
    ): void {
        if (Schema::hasColumn(self::TABLE, $column)) {
            return;
        }

        Schema::table(
            self::TABLE,
            static function (Blueprint $table) use ($definition): void {
                $definition($table);
            },
        );
    }

    private function ensureApproverForeignKey(): void
    {
        if (Schema::hasForeignKey(self::TABLE, ['approved_by'])) {
            return;
        }

        DB::table(self::TABLE)
            ->whereNotNull('approved_by')
            ->whereNotExists(
                static fn ($query) => $query
                    ->selectRaw('1')
                    ->from('users')
                    ->whereColumn(
                        'users.id',
                        self::TABLE.'.approved_by',
                    ),
            )
            ->update(['approved_by' => null]);

        Schema::table(
            self::TABLE,
            static function (Blueprint $table): void {
                $table->foreign('approved_by')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            },
        );
    }
};
