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
        Schema::create('inventory_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number', 80)->unique();

            $table->foreignId('requested_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('employee_number_snapshot', 50)->nullable();
            $table->string('requester_name_snapshot', 255);
            $table->string('work_unit_snapshot', 255)->nullable();
            $table->dateTime('request_date')->index();
            $table->text('purpose');
            $table->text('notes')->nullable();
            $table->string('status', 40)->default('draft')->index();
            $table->timestamp('submitted_at')->nullable();

            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('revision_note')->nullable();

            $table->foreignId('delivered_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'request_date']);
            $table->index(['requested_by', 'status']);
        });

        Schema::create('inventory_request_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('inventory_request_id')
                ->constrained('inventory_requests')
                ->cascadeOnDelete();

            $table->foreignId('item_id')
                ->constrained('items')
                ->restrictOnDelete();

            $table->string('item_code_snapshot', 80);
            $table->string('item_name_snapshot', 255);
            $table->string('unit_snapshot', 100);
            $table->decimal('requested_quantity', 15, 2);
            $table->decimal('approved_quantity', 15, 2)->nullable();
            $table->decimal('reserved_quantity', 15, 2)->default(0);
            $table->decimal('delivered_quantity', 15, 2)->nullable();
            $table->text('notes')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamps();

            $table->unique(
                ['inventory_request_id', 'item_id'],
                'inventory_request_items_request_item_unique'
            );
        });

        Schema::create(
            'inventory_request_status_histories',
            function (Blueprint $table) {
                $table->id();

                $table->foreignId('inventory_request_id')
                    ->constrained('inventory_requests')
                    ->cascadeOnDelete();

                $table->string('previous_status', 40)->nullable();
                $table->string('new_status', 40);
                $table->text('notes')->nullable();

                $table->foreignId('changed_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->timestamp('changed_at');
            }
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_request_status_histories');
        Schema::dropIfExists('inventory_request_items');
        Schema::dropIfExists('inventory_requests');
    }
};
