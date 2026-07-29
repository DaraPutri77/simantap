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
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->string('movement_number', 80)->unique();
            $table->string('reference_number', 80)->index();

            $table->foreignId('item_id')
                ->constrained('items')
                ->restrictOnDelete();

            $table->string('movement_type', 40)->index();
            $table->string('reference_type', 255)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->decimal('quantity_in', 15, 2)->default(0);
            $table->decimal('quantity_out', 15, 2)->default(0);
            $table->decimal('stock_before', 15, 2);
            $table->decimal('stock_after', 15, 2);
            $table->dateTime('transaction_date')->index();
            $table->text('description')->nullable();

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamp('created_at');

            $table->index(
                ['reference_type', 'reference_id'],
                'stock_movements_reference_index'
            );

            $table->index(
                ['item_id', 'transaction_date'],
                'stock_movements_item_transaction_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
