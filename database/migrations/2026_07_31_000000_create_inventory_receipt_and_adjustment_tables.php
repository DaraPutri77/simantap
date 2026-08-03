<?php

use App\Enums\InventoryReceiptStatus;
use App\Enums\StockAdjustmentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventory_receipts')) {
            Schema::create('inventory_receipts', function (Blueprint $table) {
                $table->id();
                $table->string('receipt_number', 80)->unique();
                $table->dateTime('receipt_date')->index();
                $table->string('source');
                $table->string('reference_number')->nullable();
                $table->text('notes')->nullable();
                $table->string('status', 40)
                    ->default(InventoryReceiptStatus::Draft->value)
                    ->index();
                $table->foreignId('created_by')
                    ->constrained('users')
                    ->restrictOnDelete();
                $table->foreignId('posted_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
                $table->timestamp('posted_at')->nullable();
                $table->foreignId('cancelled_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
                $table->timestamp('cancelled_at')->nullable();
                $table->text('cancellation_reason')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['status', 'receipt_date']);
            });
        }

        if (! Schema::hasTable('inventory_receipt_items')) {
            Schema::create('inventory_receipt_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('inventory_receipt_id')
                    ->constrained()
                    ->cascadeOnDelete();
                $table->foreignId('item_id')
                    ->constrained()
                    ->restrictOnDelete();
                $table->string('item_code_snapshot', 80);
                $table->string('item_name_snapshot');
                $table->string('unit_snapshot', 100);
                $table->decimal('quantity', 15, 2);
                $table->decimal('unit_cost', 19, 2)->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(
                    ['inventory_receipt_id', 'item_id'],
                    'receipt_item_unique',
                );
            });
        }

        if (! Schema::hasTable('stock_adjustments')) {
            Schema::create('stock_adjustments', function (Blueprint $table) {
                $table->id();
                $table->string('adjustment_number', 80)->unique();
                $table->dateTime('adjustment_date')->index();
                $table->text('reason');
                $table->text('notes')->nullable();
                $table->string('status', 40)
                    ->default(StockAdjustmentStatus::Draft->value)
                    ->index();
                $table->foreignId('created_by')
                    ->constrained('users')
                    ->restrictOnDelete();
                $table->foreignId('posted_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
                $table->timestamp('posted_at')->nullable();
                $table->foreignId('cancelled_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
                $table->timestamp('cancelled_at')->nullable();
                $table->text('cancellation_reason')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['status', 'adjustment_date']);
            });
        }

        if (! Schema::hasTable('stock_adjustment_items')) {
            Schema::create('stock_adjustment_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('stock_adjustment_id')
                    ->constrained()
                    ->cascadeOnDelete();
                $table->foreignId('item_id')
                    ->constrained()
                    ->restrictOnDelete();
                $table->string('item_code_snapshot', 80);
                $table->string('item_name_snapshot');
                $table->string('unit_snapshot', 100);
                $table->decimal('system_quantity', 15, 2);
                $table->decimal('physical_quantity', 15, 2);
                $table->decimal('difference_quantity', 15, 2);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(
                    ['stock_adjustment_id', 'item_id'],
                    'adjustment_item_unique',
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_adjustment_items');
        Schema::dropIfExists('stock_adjustments');
        Schema::dropIfExists('inventory_receipt_items');
        Schema::dropIfExists('inventory_receipts');
    }
};
