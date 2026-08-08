<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\MaintenanceStatus;
use App\Enums\RoleName;
use App\Enums\VehicleLoanStatus;
use App\Enums\VehicleStatus;
use App\Models\MaintenanceRecord;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleLoan;
use App\Services\MaintenanceService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

class MaintenanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
        Carbon::setTestNow('2026-08-08 03:00:00 UTC');
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_admin_can_report_manual_maintenance_with_uuid_evidence_history_and_audit(): void
    {
        $admin = $this->admin();
        $vehicle = $this->vehicle();

        $this->actingAs($admin)
            ->post(route('maintenance-records.store'), $this->reportPayload($vehicle))
            ->assertRedirect();

        $record = MaintenanceRecord::query()->firstOrFail();

        $this->assertNotNull($record->public_id);
        $this->assertStringStartsWith('MTC/2026/08/', $record->maintenance_number);
        $this->assertSame(MaintenanceStatus::Reported, $record->status);
        $this->assertSame(VehicleStatus::Available, $record->vehicle_status_before);
        $this->assertSame(VehicleStatus::Inspection, $vehicle->refresh()->status);
        $this->assertSame(1, $record->attachments()->count());
        $this->assertSame(1, $record->statusHistories()->count());
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'maintenance_reported',
            'auditable_id' => $record->id,
        ]);

        $attachment = $record->attachments()->firstOrFail();
        Storage::disk('local')->assertExists($attachment->file_path);
    }

    public function test_employee_cannot_access_or_manage_maintenance(): void
    {
        $employee = $this->employee();
        $vehicle = $this->vehicle();

        $this->actingAs($employee)
            ->get(route('maintenance-records.index'))
            ->assertForbidden();

        $this->actingAs($employee)
            ->post(route('maintenance-records.store'), $this->reportPayload($vehicle))
            ->assertForbidden();

        $this->assertDatabaseCount('maintenance_records', 0);
    }

    public function test_return_issue_can_create_linked_maintenance_and_duplicate_source_is_rejected(): void
    {
        $admin = $this->admin();
        $employee = $this->employee();
        $vehicle = $this->vehicle([
            'status' => VehicleStatus::Inspection,
        ]);
        $loan = $this->vehicleLoan($employee, $vehicle, [
            'status' => VehicleLoanStatus::ReturnIssue,
            'actual_end_at' => now()->subHour(),
        ]);

        $payload = $this->reportPayload($vehicle, [
            'source_vehicle_loan_public_id' => $loan->public_id,
            'complaint' => 'Goresan baru ditemukan pada pemeriksaan pengembalian.',
        ]);

        $this->actingAs($admin)
            ->post(route('maintenance-records.store'), $payload)
            ->assertRedirect();

        $record = MaintenanceRecord::query()->firstOrFail();
        $this->assertSame($loan->id, $record->source_vehicle_loan_id);

        $this->actingAs($admin)
            ->from(route('maintenance-records.create-from-loan', $loan))
            ->post(route('maintenance-records.store'), $this->reportPayload($vehicle, [
                'source_vehicle_loan_public_id' => $loan->public_id,
            ]))
            ->assertSessionHasErrors('vehicle_public_id');

        $this->assertDatabaseCount('maintenance_records', 1);
    }

    public function test_approval_and_start_are_atomic_and_vehicle_becomes_maintenance(): void
    {
        [$admin, $vehicle, $record] = $this->manualRecord();

        $this->actingAs($admin)
            ->post(route('maintenance-records.approve', $record), [
                'approval_notes' => 'Disetujui untuk pemeriksaan bengkel.',
            ])
            ->assertRedirect(route('maintenance-records.show', $record));

        $record->refresh();
        $this->assertSame(MaintenanceStatus::Approved, $record->status);
        $this->assertSame($admin->id, $record->approved_by);
        $this->assertNotNull($record->approved_at);

        $this->actingAs($admin)
            ->post(route('maintenance-records.start', $record), [
                'start_date' => '2026-08-08',
                'service_provider' => 'Bengkel Rekanan BPS',
            ])
            ->assertRedirect(route('maintenance-records.show', $record));

        $this->assertSame(MaintenanceStatus::InProgress, $record->refresh()->status);
        $this->assertSame(VehicleStatus::Maintenance, $vehicle->refresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'maintenance_started',
            'auditable_id' => $record->id,
        ]);
    }

    public function test_completed_maintenance_resolves_return_issue_and_restores_vehicle(): void
    {
        [$admin, $employee, $vehicle, $loan, $record] = $this->returnIssueRecord();
        $this->approveAndStart($admin, $record);

        $this->actingAs($admin)
            ->post(
                route('maintenance-records.complete', $record),
                $this->completionPayload(MaintenanceStatus::Completed),
            )
            ->assertRedirect(route('maintenance-records.show', $record));

        $this->assertSame(MaintenanceStatus::Completed, $record->refresh()->status);
        $this->assertSame(VehicleStatus::Available, $vehicle->refresh()->status);
        $this->assertSame(VehicleLoanStatus::Completed, $loan->refresh()->status);
        $this->assertDatabaseHas('vehicle_loan_status_histories', [
            'vehicle_loan_id' => $loan->id,
            'previous_status' => VehicleLoanStatus::ReturnIssue->value,
            'new_status' => VehicleLoanStatus::Completed->value,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'vehicle_loan_return_issue_resolved',
            'auditable_id' => $loan->id,
        ]);
        $this->assertSame(2, $record->attachments()->count());
    }

    public function test_completed_maintenance_keeps_vehicle_reserved_for_future_approved_loan(): void
    {
        [$admin, $employee, $vehicle, $loan, $record] = $this->returnIssueRecord();
        $futureEmployee = $this->employee();
        $this->vehicleLoan($futureEmployee, $vehicle, [
            'status' => VehicleLoanStatus::Approved,
            'planned_start_at' => '2026-08-09 01:00:00',
            'planned_end_at' => '2026-08-09 05:00:00',
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);
        $this->approveAndStart($admin, $record);

        $this->actingAs($admin)
            ->post(
                route('maintenance-records.complete', $record),
                $this->completionPayload(MaintenanceStatus::CompletedWithNotes),
            )
            ->assertRedirect();

        $this->assertSame(VehicleStatus::Reserved, $vehicle->refresh()->status);
        $this->assertSame(VehicleLoanStatus::Completed, $loan->refresh()->status);
    }

    public function test_severely_damaged_outcome_keeps_return_issue_and_marks_vehicle_damaged(): void
    {
        [$admin, $employee, $vehicle, $loan, $record] = $this->returnIssueRecord();
        $this->approveAndStart($admin, $record);

        $this->actingAs($admin)
            ->post(
                route('maintenance-records.complete', $record),
                $this->completionPayload(MaintenanceStatus::SeverelyDamaged),
            )
            ->assertRedirect();

        $this->assertSame(MaintenanceStatus::SeverelyDamaged, $record->refresh()->status);
        $this->assertSame(VehicleStatus::Damaged, $vehicle->refresh()->status);
        $this->assertSame(VehicleLoanStatus::ReturnIssue, $loan->refresh()->status);
    }

    public function test_unserviceable_outcome_deactivates_vehicle(): void
    {
        [$admin, $vehicle, $record] = $this->manualRecord();
        $this->approveAndStart($admin, $record);

        $this->actingAs($admin)
            ->post(
                route('maintenance-records.complete', $record),
                $this->completionPayload(MaintenanceStatus::Unserviceable),
            )
            ->assertRedirect();

        $vehicle->refresh();
        $this->assertSame(VehicleStatus::Inactive, $vehicle->status);
        $this->assertFalse($vehicle->is_active);
    }

    public function test_further_action_keeps_vehicle_in_maintenance_and_can_be_started_again(): void
    {
        [$admin, $vehicle, $record] = $this->manualRecord();
        $this->approveAndStart($admin, $record);

        $this->actingAs($admin)
            ->post(
                route('maintenance-records.complete', $record),
                $this->completionPayload(MaintenanceStatus::FurtherActionRequired),
            )
            ->assertRedirect();

        $this->assertSame(
            MaintenanceStatus::FurtherActionRequired,
            $record->refresh()->status,
        );
        $this->assertSame(VehicleStatus::Maintenance, $vehicle->refresh()->status);

        Carbon::setTestNow('2026-08-09 02:00:00 UTC');
        $this->actingAs($admin)
            ->post(route('maintenance-records.start', $record), [
                'start_date' => '2026-08-09',
                'service_provider' => 'Bengkel Lanjutan',
            ])
            ->assertRedirect();

        $this->assertSame(MaintenanceStatus::InProgress, $record->refresh()->status);
    }

    public function test_cancellation_restores_manual_available_vehicle_and_records_reason(): void
    {
        [$admin, $vehicle, $record] = $this->manualRecord();

        $this->actingAs($admin)
            ->post(route('maintenance-records.cancel', $record), [
                'cancellation_reason' => 'Pemeriksaan ulang menunjukkan laporan tidak memerlukan pemeliharaan.',
            ])
            ->assertRedirect();

        $this->assertSame(MaintenanceStatus::Cancelled, $record->refresh()->status);
        $this->assertSame(VehicleStatus::Available, $vehicle->refresh()->status);
        $this->assertNotNull($record->cancelled_at);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'maintenance_cancelled',
            'auditable_id' => $record->id,
        ]);
    }

    public function test_failed_completion_cleans_new_files(): void
    {
        [$admin, $vehicle, $record] = $this->manualRecord();
        $this->actingAs($admin)
            ->post(route('maintenance-records.approve', $record), [])
            ->assertRedirect();

        $filesBefore = Storage::disk('local')->allFiles();
        sort($filesBefore);

        try {
            app(MaintenanceService::class)->complete(
                $record->refresh(),
                $this->completionPayload(MaintenanceStatus::Completed),
                $admin,
            );
            $this->fail('Completion sebelum InProgress seharusnya ditolak.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $filesAfter = Storage::disk('local')->allFiles();
        sort($filesAfter);
        $this->assertSame($filesBefore, $filesAfter);
    }

    public function test_maintenance_status_history_is_immutable(): void
    {
        [$admin, $vehicle, $record] = $this->manualRecord();
        $history = $record->statusHistories()->firstOrFail();

        try {
            $history->forceFill(['notes' => 'Diubah secara ilegal'])->save();
            $this->fail('Riwayat status pemeliharaan seharusnya immutable.');
        } catch (LogicException $exception) {
            $this->assertSame(
                'Riwayat status pemeliharaan tidak boleh diubah.',
                $exception->getMessage(),
            );
        }

        $this->expectException(LogicException::class);
        $history->delete();
    }

    /**
     * @return array{User, Vehicle, MaintenanceRecord}
     */
    private function manualRecord(): array
    {
        $admin = $this->admin();
        $vehicle = $this->vehicle();

        $this->actingAs($admin)
            ->post(route('maintenance-records.store'), $this->reportPayload($vehicle))
            ->assertRedirect();

        return [
            $admin,
            $vehicle,
            MaintenanceRecord::query()->firstOrFail(),
        ];
    }

    /**
     * @return array{User, User, Vehicle, VehicleLoan, MaintenanceRecord}
     */
    private function returnIssueRecord(): array
    {
        $admin = $this->admin();
        $employee = $this->employee();
        $vehicle = $this->vehicle([
            'status' => VehicleStatus::Inspection,
        ]);
        $loan = $this->vehicleLoan($employee, $vehicle, [
            'status' => VehicleLoanStatus::ReturnIssue,
            'actual_start_at' => now()->subHours(5),
            'actual_end_at' => now()->subHour(),
        ]);

        $this->actingAs($admin)
            ->post(route('maintenance-records.store'), $this->reportPayload($vehicle, [
                'source_vehicle_loan_public_id' => $loan->public_id,
            ]))
            ->assertRedirect();

        return [
            $admin,
            $employee,
            $vehicle,
            $loan,
            MaintenanceRecord::query()->firstOrFail(),
        ];
    }

    private function approveAndStart(User $admin, MaintenanceRecord $record): void
    {
        $this->actingAs($admin)
            ->post(route('maintenance-records.approve', $record), [])
            ->assertRedirect();

        $this->actingAs($admin)
            ->post(route('maintenance-records.start', $record), [
                'start_date' => '2026-08-08',
                'service_provider' => 'Bengkel Rekanan BPS',
            ])
            ->assertRedirect();

        $record->refresh();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function reportPayload(Vehicle $vehicle, array $overrides = []): array
    {
        return [
            'vehicle_public_id' => $vehicle->public_id,
            'source_vehicle_loan_public_id' => null,
            'maintenance_type' => 'Pemeliharaan korektif',
            'complaint' => 'Terdapat bunyi tidak normal saat kendaraan dijalankan.',
            'initial_condition' => 'Kendaraan dapat dinyalakan tetapi perlu pemeriksaan lebih lanjut.',
            'reported_date' => '2026-08-08',
            'photo_before' => UploadedFile::fake()
                ->image('maintenance-before.jpg', 160, 120)
                ->size(180),
            ...$overrides,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function completionPayload(MaintenanceStatus $status): array
    {
        return [
            'outcome_status' => $status->value,
            'completion_date' => '2026-08-08',
            'cost' => 275000,
            'result' => 'Komponen diperiksa dan tindakan pemeliharaan telah dilakukan.',
            'final_condition' => 'Kondisi akhir telah diverifikasi oleh pengelola kendaraan.',
            'photo_after' => UploadedFile::fake()
                ->image('maintenance-after.jpg', 160, 120)
                ->size(180),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function admin(array $attributes = []): User
    {
        $user = User::factory()->create([
            'status' => AccountStatus::Active,
            'phone' => '081234567800',
            ...$attributes,
        ]);
        $user->assignRole(RoleName::Administrator->value);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function employee(array $attributes = []): User
    {
        $user = User::factory()->create([
            'status' => AccountStatus::Active,
            'phone' => fake()->unique()->numerify('0812########'),
            'work_unit' => 'Seksi Statistik Produksi',
            'position' => 'Statistisi Ahli Pertama',
            ...$attributes,
        ]);
        $user->assignRole(RoleName::Employee->value);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function vehicle(array $attributes = []): Vehicle
    {
        return Vehicle::query()->create([
            'vehicle_code' => fake()->unique()->bothify('KND-###??'),
            'license_plate' => fake()->unique()->bothify('S #### ??'),
            'brand' => 'Honda',
            'model' => 'Vario 160 CBS',
            'year' => 2025,
            'color' => 'Hitam',
            'chassis_number' => fake()->unique()->bothify('MH1###############'),
            'engine_number' => fake()->unique()->bothify('ENG############'),
            'current_odometer' => 1000.0,
            'status' => VehicleStatus::Available,
            'registration_expiry_date' => '2027-08-03',
            'storage_location' => 'Garasi Kantor, Slot A-01',
            'responsible_person' => 'Pengelola Barang',
            'is_active' => true,
            ...$attributes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function vehicleLoan(
        User $employee,
        Vehicle $vehicle,
        array $attributes = [],
    ): VehicleLoan {
        return VehicleLoan::query()->create([
            'loan_number' => fake()->unique()->numerify('LOAN/2026/08/####'),
            'borrower_id' => $employee->id,
            'borrower_name_snapshot' => $employee->name,
            'employee_number_snapshot' => $employee->employee_number,
            'work_unit_snapshot' => $employee->work_unit,
            'vehicle_id' => $vehicle->id,
            'vehicle_code_snapshot' => $vehicle->vehicle_code,
            'license_plate_snapshot' => $vehicle->license_plate,
            'vehicle_name_snapshot' => $vehicle->displayName(),
            'purpose' => 'Kunjungan lapangan untuk kegiatan statistik sektoral.',
            'destination' => 'Kantor Kecamatan Sukolilo',
            'reason' => null,
            'phone_snapshot' => $employee->phone,
            'planned_start_at' => '2026-08-08 01:00:00',
            'planned_end_at' => '2026-08-08 05:00:00',
            'status' => VehicleLoanStatus::Draft,
            'notes' => null,
            ...$attributes,
        ]);
    }
}
