<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('inventory_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number', 80)->unique();
            $table->dateTime('receipt_date')->index();
            $table->string('source');
            $table->string('reference_number')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 40)->default('draft');

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
            $table->index(['created_by', 'status']);
        });

        Schema::create('inventory_receipt_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('inventory_receipt_id')
                ->constrained('inventory_receipts')
                ->cascadeOnDelete();

            $table->foreignId('item_id')
                ->constrained('items')
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
                'inventory_receipt_items_receipt_item_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_receipt_items');
        Schema::dropIfExists('inventory_receipts');
    }
};
