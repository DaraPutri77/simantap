<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->alignInventoryRequests();
        $this->alignInventoryRequestItems();
        $this->alignDigitalSignatures();
    }

    public function down(): void
    {
        $this->dropDigitalSignatureColumns();
        $this->dropInventoryRequestItemColumns();
        $this->dropInventoryRequestColumns();
    }

    private function alignInventoryRequests(): void
    {
        if (! Schema::hasTable('inventory_requests')) {
            return;
        }

        Schema::table('inventory_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('inventory_requests', 'employee_number_snapshot')) {
                $table->string('employee_number_snapshot', 50)->nullable();
            }

            if (! Schema::hasColumn('inventory_requests', 'requester_name_snapshot')) {
                $table->string('requester_name_snapshot')->nullable();
            }

            if (! Schema::hasColumn('inventory_requests', 'work_unit_snapshot')) {
                $table->string('work_unit_snapshot')->nullable();
            }

            if (! Schema::hasColumn('inventory_requests', 'reviewed_by')) {
                $table->foreignId('reviewed_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('inventory_requests', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable();
            }

            if (! Schema::hasColumn('inventory_requests', 'revision_note')) {
                $table->text('revision_note')->nullable();
            }

            if (! Schema::hasColumn('inventory_requests', 'completed_at')) {
                $table->timestamp('completed_at')->nullable();
            }

            if (! Schema::hasColumn('inventory_requests', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable();
            }

            if (! Schema::hasColumn('inventory_requests', 'cancellation_reason')) {
                $table->text('cancellation_reason')->nullable();
            }

            if (! Schema::hasColumn('inventory_requests', 'expired_at')) {
                $table->timestamp('expired_at')->nullable();
            }
        });
    }

    private function alignInventoryRequestItems(): void
    {
        if (! Schema::hasTable('inventory_request_items')) {
            return;
        }

        Schema::table('inventory_request_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('inventory_request_items', 'item_code_snapshot')) {
                $table->string('item_code_snapshot', 80)->nullable();
            }

            if (! Schema::hasColumn('inventory_request_items', 'item_name_snapshot')) {
                $table->string('item_name_snapshot')->nullable();
            }

            if (! Schema::hasColumn('inventory_request_items', 'unit_snapshot')) {
                $table->string('unit_snapshot', 100)->nullable();
            }

            if (! Schema::hasColumn('inventory_request_items', 'reserved_quantity')) {
                $table->decimal('reserved_quantity', 15, 2)->default(0);
            }

            if (! Schema::hasColumn('inventory_request_items', 'admin_notes')) {
                $table->text('admin_notes')->nullable();
            }
        });
    }

    private function alignDigitalSignatures(): void
    {
        if (! Schema::hasTable('digital_signatures')) {
            return;
        }

        Schema::table('digital_signatures', function (Blueprint $table): void {
            if (! Schema::hasColumn('digital_signatures', 'signer_name_snapshot')) {
                $table->string('signer_name_snapshot')->nullable();
            }

            if (! Schema::hasColumn('digital_signatures', 'employee_number_snapshot')) {
                $table->string('employee_number_snapshot', 50)->nullable();
            }
        });
    }

    private function dropInventoryRequestColumns(): void
    {
        if (! Schema::hasTable('inventory_requests')) {
            return;
        }

        if (Schema::hasColumn('inventory_requests', 'reviewed_by')) {
            Schema::table('inventory_requests', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('reviewed_by');
            });
        }

        $this->dropColumnsIfPresent('inventory_requests', [
            'employee_number_snapshot',
            'requester_name_snapshot',
            'work_unit_snapshot',
            'reviewed_at',
            'revision_note',
            'completed_at',
            'cancelled_at',
            'cancellation_reason',
            'expired_at',
        ]);
    }

    private function dropInventoryRequestItemColumns(): void
    {
        $this->dropColumnsIfPresent('inventory_request_items', [
            'item_code_snapshot',
            'item_name_snapshot',
            'unit_snapshot',
            'reserved_quantity',
            'admin_notes',
        ]);
    }

    private function dropDigitalSignatureColumns(): void
    {
        $this->dropColumnsIfPresent('digital_signatures', [
            'signer_name_snapshot',
            'employee_number_snapshot',
        ]);
    }

    /**
     * @param  list<string>  $columns
     */
    private function dropColumnsIfPresent(string $tableName, array $columns): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        $existing = array_values(array_filter(
            $columns,
            static fn (string $column): bool => Schema::hasColumn(
                $tableName,
                $column,
            ),
        ));

        if ($existing === []) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($existing): void {
            $table->dropColumn($existing);
        });
    }
};
