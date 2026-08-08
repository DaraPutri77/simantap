<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $hasPublicId = Schema::hasColumn('maintenance_records', 'public_id');
        $hasVehicleStatusBefore = Schema::hasColumn('maintenance_records', 'vehicle_status_before');
        $hasApprovedBy = Schema::hasColumn('maintenance_records', 'approved_by');
        $hasApprovedAt = Schema::hasColumn('maintenance_records', 'approved_at');
        $hasApprovalNotes = Schema::hasColumn('maintenance_records', 'approval_notes');
        $hasStartedAt = Schema::hasColumn('maintenance_records', 'started_at');
        $hasCompletedAt = Schema::hasColumn('maintenance_records', 'completed_at');
        $hasCancelledBy = Schema::hasColumn('maintenance_records', 'cancelled_by');
        $hasCancelledAt = Schema::hasColumn('maintenance_records', 'cancelled_at');
        $hasCancellationReason = Schema::hasColumn('maintenance_records', 'cancellation_reason');

        Schema::table('maintenance_records', function (Blueprint $table) use (
            $hasPublicId,
            $hasVehicleStatusBefore,
            $hasApprovedBy,
            $hasApprovedAt,
            $hasApprovalNotes,
            $hasStartedAt,
            $hasCompletedAt,
            $hasCancelledBy,
            $hasCancelledAt,
            $hasCancellationReason,
        ): void {
            if (! $hasPublicId) {
                $table->uuid('public_id')->nullable()->after('id');
            }

            if (! $hasVehicleStatusBefore) {
                $table->string('vehicle_status_before', 40)
                    ->nullable()
                    ->after('vehicle_snapshot');
            }

            if (! $hasApprovedBy) {
                $table->foreignId('approved_by')
                    ->nullable()
                    ->after('handled_by')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! $hasApprovedAt) {
                $table->timestamp('approved_at')
                    ->nullable()
                    ->after('approved_by');
            }

            if (! $hasApprovalNotes) {
                $table->text('approval_notes')
                    ->nullable()
                    ->after('approved_at');
            }

            if (! $hasStartedAt) {
                $table->timestamp('started_at')
                    ->nullable()
                    ->after('start_date');
            }

            if (! $hasCompletedAt) {
                $table->timestamp('completed_at')
                    ->nullable()
                    ->after('completion_date');
            }

            if (! $hasCancelledBy) {
                $table->foreignId('cancelled_by')
                    ->nullable()
                    ->after('completed_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! $hasCancelledAt) {
                $table->timestamp('cancelled_at')
                    ->nullable()
                    ->after('cancelled_by');
            }

            if (! $hasCancellationReason) {
                $table->text('cancellation_reason')
                    ->nullable()
                    ->after('cancelled_at');
            }
        });

        DB::table('maintenance_records')
            ->whereNull('public_id')
            ->orderBy('id')
            ->eachById(static function (object $record): void {
                DB::table('maintenance_records')
                    ->where('id', $record->id)
                    ->update([
                        'public_id' => (string) Str::uuid(),
                    ]);
            });

        if (! $hasPublicId) {
            Schema::table('maintenance_records', static function (Blueprint $table): void {
                $table->unique('public_id');
            });
        }

        if (! Schema::hasTable('maintenance_status_histories')) {
            Schema::create('maintenance_status_histories', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('maintenance_record_id')
                    ->constrained('maintenance_records')
                    ->restrictOnDelete();
                $table->string('previous_status', 40)->nullable();
                $table->string('new_status', 40);
                $table->text('notes')->nullable();
                $table->foreignId('changed_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
                $table->timestamp('changed_at');

                $table->index(
                    ['maintenance_record_id', 'changed_at'],
                    'maintenance_histories_record_changed_index',
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_status_histories');

        $columns = array_values(array_filter([
            Schema::hasColumn('maintenance_records', 'cancellation_reason') ? 'cancellation_reason' : null,
            Schema::hasColumn('maintenance_records', 'cancelled_at') ? 'cancelled_at' : null,
            Schema::hasColumn('maintenance_records', 'cancelled_by') ? 'cancelled_by' : null,
            Schema::hasColumn('maintenance_records', 'completed_at') ? 'completed_at' : null,
            Schema::hasColumn('maintenance_records', 'started_at') ? 'started_at' : null,
            Schema::hasColumn('maintenance_records', 'approval_notes') ? 'approval_notes' : null,
            Schema::hasColumn('maintenance_records', 'approved_at') ? 'approved_at' : null,
            Schema::hasColumn('maintenance_records', 'approved_by') ? 'approved_by' : null,
            Schema::hasColumn('maintenance_records', 'vehicle_status_before') ? 'vehicle_status_before' : null,
            Schema::hasColumn('maintenance_records', 'public_id') ? 'public_id' : null,
        ]));

        if ($columns === []) {
            return;
        }

        Schema::table('maintenance_records', static function (Blueprint $table) use ($columns): void {
            if (in_array('public_id', $columns, true)) {
                $table->dropUnique('maintenance_records_public_id_unique');
            }

            foreach (['approved_by', 'cancelled_by'] as $foreignColumn) {
                if (in_array($foreignColumn, $columns, true)) {
                    $table->dropForeign([''.$foreignColumn]);
                }
            }

            $table->dropColumn($columns);
        });
    }
};
