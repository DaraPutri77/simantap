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
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('vehicle_code', 80)->unique();
            $table->string('license_plate', 30)->unique();
            $table->string('brand');
            $table->string('model');
            $table->year('year')->nullable();
            $table->string('color', 80)->nullable();
            $table->string('chassis_number', 120)->nullable()->unique();
            $table->string('engine_number', 120)->nullable()->unique();
            $table->decimal('current_odometer', 12, 1)->default(0);
            $table->string('status', 40)
                ->default('available')
                ->index();
            $table->date('registration_expiry_date')->nullable()->index();
            $table->string('storage_location')->nullable();
            $table->string('image_path')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('vehicle_loans', function (Blueprint $table) {
            $table->id();
            $table->string('loan_number', 80)->unique();

            $table->foreignId('borrower_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('employee_number_snapshot', 50)->nullable();
            $table->string('borrower_name_snapshot');
            $table->string('work_unit_snapshot')->nullable();
            $table->string('phone_snapshot', 30);

            $table->foreignId('vehicle_id')
                ->constrained('vehicles')
                ->restrictOnDelete();

            $table->string('vehicle_code_snapshot', 80);
            $table->string('license_plate_snapshot', 30);
            $table->string('vehicle_name_snapshot');
            $table->text('purpose');
            $table->string('destination');
            $table->text('reason')->nullable();
            $table->dateTime('planned_start_at')->index();
            $table->dateTime('planned_end_at')->index();
            $table->dateTime('actual_start_at')->nullable();
            $table->dateTime('actual_end_at')->nullable();
            $table->timestamp('overdue_at')->nullable();
            $table->string('status', 40)
                ->default('draft')
                ->index();

            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(
                ['vehicle_id', 'planned_start_at', 'planned_end_at'],
                'vehicle_loan_schedule_index'
            );

            $table->index(['borrower_id', 'status']);
        });

        Schema::create(
            'vehicle_loan_status_histories',
            function (Blueprint $table) {
                $table->id();

                $table->foreignId('vehicle_loan_id')
                    ->constrained('vehicle_loans')
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

        Schema::create(
            'vehicle_condition_checks',
            function (Blueprint $table) {
                $table->id();

                $table->foreignId('vehicle_loan_id')
                    ->constrained('vehicle_loans')
                    ->cascadeOnDelete();

                $table->string('check_type', 30);
                $table->decimal('odometer', 12, 1);
                $table->unsignedTinyInteger('fuel_level');
                $table->string('overall_condition', 40);
                $table->text('body_condition');
                $table->text('engine_condition');
                $table->text('tire_condition');
                $table->text('equipment_condition');
                $table->text('damage_notes')->nullable();

                $table->foreignId('checked_by')
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->timestamp('checked_at');
                $table->timestamp('borrower_confirmed_at')->nullable();
                $table->timestamps();

                $table->unique(
                    ['vehicle_loan_id', 'check_type'],
                    'vehicle_condition_checks_loan_type_unique'
                );
            }
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_condition_checks');
        Schema::dropIfExists('vehicle_loan_status_histories');
        Schema::dropIfExists('vehicle_loans');
        Schema::dropIfExists('vehicles');
    }
};
