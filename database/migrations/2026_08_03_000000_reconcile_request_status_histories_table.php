<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('request_status_histories')) {
            return;
        }

        if (Schema::hasTable('inventory_request_status_histories')) {
            Schema::rename(
                'inventory_request_status_histories',
                'request_status_histories',
            );

            return;
        }

        Schema::create('request_status_histories', function (Blueprint $table): void {
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
            $table->timestamp('changed_at')->useCurrent();

            $table->index([
                'inventory_request_id',
                'changed_at',
            ]);
        });
    }

    public function down(): void
    {
        // Compatibility migration: keep status history data intact on rollback.
    }
};
