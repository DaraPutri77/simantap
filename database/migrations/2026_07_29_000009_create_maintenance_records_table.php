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
        Schema::create('maintenance_records', function (Blueprint $table) {
            $table->id();
            $table->string('maintenance_number', 80)->unique();

            $table->foreignId('vehicle_id')
                ->constrained('vehicles')
                ->restrictOnDelete();

            $table->foreignId('source_vehicle_loan_id')
                ->nullable()
                ->constrained('vehicle_loans')
                ->nullOnDelete();

            $table->string('vehicle_snapshot');

            $table->foreignId('reported_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('handled_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('maintenance_type', 100);
            $table->text('complaint');
            $table->text('initial_condition');
            $table->string('service_provider')->nullable();
            $table->date('reported_date')->index();
            $table->date('start_date')->nullable();
            $table->date('completion_date')->nullable();
            $table->decimal('cost', 19, 2)->nullable();
            $table->text('result')->nullable();
            $table->text('final_condition')->nullable();
            $table->string('status', 40)
                ->default('reported')
                ->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(
                ['status', 'reported_date'],
                'maintenance_records_status_reported_date_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maintenance_records');
    }
};
