<?php

namespace Tests\Feature;

use App\Enums\ConditionCheckType;
use App\Enums\MaintenanceStatus;
use App\Enums\VehicleLoanStatus;
use App\Enums\VehicleOverallCondition;
use App\Enums\VehicleStatus;
use App\Models\MaintenanceRecord;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleConditionCheck;
use App\Models\VehicleLoan;
use App\Models\VehicleLoanStatusHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

class VehicleTransactionModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_vehicle_generates_public_id_and_uses_expected_casts(): void
    {
        $vehicle = $this->createVehicle();

        $vehicle->refresh();

        $this->assertTrue(Str::isUuid($vehicle->public_id));
        $this->assertSame('public_id', $vehicle->getRouteKeyName());
        $this->assertSame(VehicleStatus::Available, $vehicle->status);
        $this->assertSame(2024, $vehicle->year);
        $this->assertSame('1250.5', $vehicle->current_odometer);
        $this->assertSame(
            '2027-07-29',
            $vehicle->registration_expiry_date->format('Y-m-d'),
        );
        $this->assertTrue($vehicle->is_active);
        $this->assertTrue($vehicle->isAvailable());
        $this->assertFalse($vehicle->isUnderInspection());
        $this->assertFalse($vehicle->isUnderMaintenance());
        $this->assertTrue($vehicle->canBeBorrowed());
    }

    public function test_vehicle_loan_and_status_history_use_expected_contract(): void
    {
        $borrower = User::factory()->create([
            'employee_number' => 'PGW-001',
            'phone' => '081234567890',
            'work_unit' => 'Bagian Umum',
        ]);

        $reviewer = User::factory()->create([
            'employee_number' => 'ADM-001',
        ]);

        $vehicle = $this->createVehicle();

        $vehicleLoan = $this->createVehicleLoan(
            borrower: $borrower,
            vehicle: $vehicle,
            reviewer: $reviewer,
            status: VehicleLoanStatus::UnderReview,
        );

        $history = VehicleLoanStatusHistory::query()->create([
            'vehicle_loan_id' => $vehicleLoan->id,
            'previous_status' => VehicleLoanStatus::Submitted,
            'new_status' => VehicleLoanStatus::UnderReview,
            'notes' => 'Pengajuan mulai diperiksa oleh admin.',
            'changed_by' => $reviewer->id,
            'changed_at' => now(),
        ]);

        $vehicleLoan->load([
            'borrower',
            'vehicle',
            'reviewer',
            'statusHistories.changer',
        ]);

        $vehicle->load('vehicleLoans');

        $this->assertSame(
            VehicleLoanStatus::UnderReview,
            $vehicleLoan->status,
        );
        $this->assertTrue($vehicleLoan->borrower->is($borrower));
        $this->assertTrue($vehicleLoan->vehicle->is($vehicle));
        $this->assertTrue($vehicleLoan->reviewer->is($reviewer));
        $this->assertCount(1, $vehicle->vehicleLoans);
        $this->assertTrue(
            $vehicle->vehicleLoans->first()->is($vehicleLoan),
        );
        $this->assertFalse($vehicleLoan->isDraft());
        $this->assertFalse($vehicleLoan->isBorrowed());
        $this->assertFalse(
            $vehicleLoan->isAwaitingReturnInspection(),
        );
        $this->assertFalse($vehicleLoan->isCompleted());
        $this->assertFalse($vehicleLoan->wasMarkedOverdue());

        $loadedHistory = $vehicleLoan->statusHistories->first();

        $this->assertTrue($loadedHistory->is($history));
        $this->assertSame(
            VehicleLoanStatus::Submitted,
            $loadedHistory->previous_status,
        );
        $this->assertSame(
            VehicleLoanStatus::UnderReview,
            $loadedHistory->new_status,
        );
        $this->assertTrue($loadedHistory->changer->is($reviewer));

        $this->assertModelCannotBeChanged(
            $history,
            ['notes' => 'Riwayat tidak boleh diubah.'],
        );
    }

    public function test_vehicle_condition_check_uses_expected_casts_and_relationships(): void
    {
        $borrower = User::factory()->create([
            'employee_number' => 'PGW-002',
            'phone' => '081298765432',
        ]);

        $reviewer = User::factory()->create([
            'employee_number' => 'ADM-002',
        ]);

        $checker = User::factory()->create([
            'employee_number' => 'ADM-003',
        ]);

        $vehicle = $this->createVehicle();

        $vehicleLoan = $this->createVehicleLoan(
            borrower: $borrower,
            vehicle: $vehicle,
            reviewer: $reviewer,
            status: VehicleLoanStatus::Borrowed,
        );

        $conditionCheck = VehicleConditionCheck::query()->create([
            'vehicle_loan_id' => $vehicleLoan->id,
            'check_type' => ConditionCheckType::Checkout,
            'odometer' => 1250.5,
            'fuel_level' => 75,
            'overall_condition' => VehicleOverallCondition::Good,
            'body_condition' => 'Bodi dalam kondisi baik.',
            'engine_condition' => 'Mesin menyala normal.',
            'tire_condition' => 'Tekanan dan kondisi ban baik.',
            'equipment_condition' => 'Peralatan kendaraan lengkap.',
            'damage_notes' => null,
            'checked_by' => $checker->id,
            'checked_at' => now(),
            'borrower_confirmed_at' => now(),
        ]);

        $conditionCheck->load([
            'vehicleLoan.vehicle',
            'checker',
        ]);

        $vehicleLoan->load('conditionChecks');

        $this->assertSame(
            ConditionCheckType::Checkout,
            $conditionCheck->check_type,
        );
        $this->assertSame(
            VehicleOverallCondition::Good,
            $conditionCheck->overall_condition,
        );
        $this->assertSame('1250.5', $conditionCheck->odometer);
        $this->assertSame(75, $conditionCheck->fuel_level);
        $this->assertTrue(
            $conditionCheck->vehicleLoan->is($vehicleLoan),
        );
        $this->assertTrue(
            $conditionCheck->vehicleLoan->vehicle->is($vehicle),
        );
        $this->assertTrue($conditionCheck->checker->is($checker));
        $this->assertTrue($conditionCheck->isCheckout());
        $this->assertFalse($conditionCheck->isReturn());
        $this->assertTrue(
            $conditionCheck->isConfirmedByBorrower(),
        );
        $this->assertCount(1, $vehicleLoan->conditionChecks);
        $this->assertTrue(
            $vehicleLoan->conditionChecks->first()->is(
                $conditionCheck,
            ),
        );
    }

    public function test_maintenance_record_uses_expected_casts_and_relationships(): void
    {
        $borrower = User::factory()->create([
            'employee_number' => 'PGW-003',
            'phone' => '081212345678',
        ]);

        $reviewer = User::factory()->create([
            'employee_number' => 'ADM-004',
        ]);

        $reporter = User::factory()->create([
            'employee_number' => 'PGW-004',
        ]);

        $handler = User::factory()->create([
            'employee_number' => 'ADM-005',
        ]);

        $vehicle = $this->createVehicle();

        $vehicleLoan = $this->createVehicleLoan(
            borrower: $borrower,
            vehicle: $vehicle,
            reviewer: $reviewer,
            status: VehicleLoanStatus::AwaitingReturnInspection,
        );

        $maintenanceRecord = MaintenanceRecord::query()->create([
            'maintenance_number' => 'MTC/2026/07/0001',
            'vehicle_id' => $vehicle->id,
            'source_vehicle_loan_id' => $vehicleLoan->id,
            'vehicle_snapshot' => sprintf(
                '%s %s - %s',
                $vehicle->brand,
                $vehicle->model,
                $vehicle->license_plate,
            ),
            'reported_by' => $reporter->id,
            'handled_by' => $handler->id,
            'maintenance_type' => 'Perbaikan',
            'complaint' => 'Terdapat suara tidak normal pada mesin.',
            'initial_condition' => 'Mesin masih menyala tetapi berisik.',
            'service_provider' => 'Bengkel Mitra SIMANTAP',
            'reported_date' => '2026-07-29',
            'start_date' => '2026-07-30',
            'completion_date' => null,
            'cost' => 350000,
            'result' => null,
            'final_condition' => null,
            'status' => MaintenanceStatus::InProgress,
        ]);

        $maintenanceRecord->load([
            'vehicle',
            'sourceVehicleLoan',
            'reporter',
            'handler',
        ]);

        $vehicle->load('maintenanceRecords');
        $vehicleLoan->load('maintenanceRecords');

        $this->assertSame(
            MaintenanceStatus::InProgress,
            $maintenanceRecord->status,
        );
        $this->assertSame(
            '2026-07-29',
            $maintenanceRecord->reported_date->format('Y-m-d'),
        );
        $this->assertSame(
            '2026-07-30',
            $maintenanceRecord->start_date->format('Y-m-d'),
        );
        $this->assertNull($maintenanceRecord->completion_date);
        $this->assertSame('350000.00', $maintenanceRecord->cost);
        $this->assertTrue(
            $maintenanceRecord->vehicle->is($vehicle),
        );
        $this->assertTrue(
            $maintenanceRecord->sourceVehicleLoan->is($vehicleLoan),
        );
        $this->assertTrue(
            $maintenanceRecord->reporter->is($reporter),
        );
        $this->assertTrue(
            $maintenanceRecord->handler->is($handler),
        );
        $this->assertFalse($maintenanceRecord->isReported());
        $this->assertTrue($maintenanceRecord->isInProgress());
        $this->assertFalse($maintenanceRecord->isCompleted());
        $this->assertFalse(
            $maintenanceRecord->requiresFurtherAction(),
        );
        $this->assertCount(1, $vehicle->maintenanceRecords);
        $this->assertCount(1, $vehicleLoan->maintenanceRecords);
        $this->assertTrue(
            $vehicle->maintenanceRecords->first()->is(
                $maintenanceRecord,
            ),
        );
        $this->assertTrue(
            $vehicleLoan->maintenanceRecords->first()->is(
                $maintenanceRecord,
            ),
        );
    }

    private function createVehicle(): Vehicle
    {
        return Vehicle::query()->create([
            'vehicle_code' => 'VEH-TEST-001',
            'license_plate' => 'N 1234 TEST',
            'brand' => 'Honda',
            'model' => 'Vario 160',
            'year' => 2024,
            'color' => 'Hitam',
            'chassis_number' => 'MH1TEST0000000001',
            'engine_number' => 'ENGTEST000000001',
            'current_odometer' => 1250.5,
            'status' => VehicleStatus::Available,
            'registration_expiry_date' => '2027-07-29',
            'storage_location' => 'Garasi Utama',
            'image_path' => null,
            'notes' => 'Kendaraan pengujian model.',
            'is_active' => true,
        ]);
    }

    private function createVehicleLoan(
        User $borrower,
        Vehicle $vehicle,
        User $reviewer,
        VehicleLoanStatus $status,
    ): VehicleLoan {
        return VehicleLoan::query()->create([
            'loan_number' => 'LOAN/2026/07/0001',
            'borrower_id' => $borrower->id,
            'employee_number_snapshot' => $borrower->employee_number,
            'borrower_name_snapshot' => $borrower->name,
            'work_unit_snapshot' => $borrower->work_unit,
            'phone_snapshot' => $borrower->phone ?? '081200000000',
            'vehicle_id' => $vehicle->id,
            'vehicle_code_snapshot' => $vehicle->vehicle_code,
            'license_plate_snapshot' => $vehicle->license_plate,
            'vehicle_name_snapshot' => sprintf(
                '%s %s',
                $vehicle->brand,
                $vehicle->model,
            ),
            'purpose' => 'Kegiatan operasional pengujian.',
            'destination' => 'Kantor Cabang',
            'reason' => 'Membawa dokumen operasional.',
            'planned_start_at' => now()->addDay(),
            'planned_end_at' => now()->addDays(2),
            'actual_start_at' => $status === VehicleLoanStatus::Borrowed
                ? now()
                : null,
            'actual_end_at' => null,
            'overdue_at' => null,
            'status' => $status,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'approved_at' => in_array($status, [
                VehicleLoanStatus::Approved,
                VehicleLoanStatus::ReadyForPickup,
                VehicleLoanStatus::Borrowed,
                VehicleLoanStatus::AwaitingReturnInspection,
                VehicleLoanStatus::Completed,
                VehicleLoanStatus::ReturnIssue,
            ], true) ? now() : null,
            'rejected_at' => null,
            'rejection_reason' => null,
            'cancelled_at' => null,
            'cancellation_reason' => null,
            'notes' => 'Peminjaman kendaraan untuk pengujian model.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function assertModelCannotBeChanged(
        Model $model,
        array $changes,
    ): void {
        try {
            $model->update($changes);

            $this->fail('Model immutable masih dapat diubah.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString(
                'tidak boleh diubah',
                $exception->getMessage(),
            );
        }

        $model->refresh();

        foreach ($changes as $attribute => $value) {
            $this->assertNotEquals(
                $value,
                $model->getAttribute($attribute),
            );
        }

        try {
            $model->delete();

            $this->fail('Model immutable masih dapat dihapus.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString(
                'tidak boleh dihapus',
                $exception->getMessage(),
            );
        }

        $this->assertDatabaseHas($model->getTable(), [
            $model->getKeyName() => $model->getKey(),
        ]);
    }
}
