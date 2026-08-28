<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\AttachmentCategory;
use App\Enums\MaintenanceStatus;
use App\Enums\OperationalAssetStatus;
use App\Enums\OperationalAssetType;
use App\Enums\RoleName;
use App\Enums\VehicleStatus;
use App\Models\DocumentVerification;
use App\Models\MaintenanceRecord;
use App\Models\MaintenanceStatusHistory;
use App\Models\OperationalAsset;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class MaintenancePdfTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
        Carbon::setTestNow('2026-08-28 03:00:00 UTC');
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_admin_can_download_verified_and_audited_vehicle_maintenance_pdf(): void
    {
        $admin = $this->admin();
        $record = $this->vehicleRecord($admin);

        $response = $this->actingAs($admin)
            ->get(route('maintenance-records.pdf', $record));

        $response
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertStringContainsString(
            'attachment;',
            (string) $response->headers->get(
                'content-disposition',
            ),
        );
        $this->assertDatabaseHas('document_verifications', [
            'document_type' => 'maintenance_record',
            'document_reference' => $record->maintenance_number,
            'version' => 1,
            'issued_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'maintenance_pdf_downloaded',
            'module' => 'maintenance',
            'auditable_id' => $record->id,
            'actor_id' => $admin->id,
        ]);
    }

    public function test_pdf_is_available_for_every_maintenance_status(): void
    {
        $admin = $this->admin();
        $record = $this->vehicleRecord($admin);

        foreach (MaintenanceStatus::cases() as $status) {
            $record->forceFill([
                'status' => $status,
            ])->save();

            $this->actingAs($admin)
                ->get(route('maintenance-records.pdf', $record->fresh()))
                ->assertOk()
                ->assertHeader('content-type', 'application/pdf');
        }
    }

    public function test_operational_asset_maintenance_pdf_uses_the_exact_asset_subject(): void
    {
        $admin = $this->admin();
        $asset = OperationalAsset::query()->create([
            'asset_code' => 'AST-PC-001',
            'bmn_code' => '3100102001',
            'nup' => '35',
            'register_code' => 'REGISTER-PC-001',
            'type' => OperationalAssetType::Pc,
            'brand' => 'Dell',
            'model' => 'OptiPlex',
            'serial_number' => 'SERIAL-PC-001',
            'acquisition_year' => 2021,
            'location' => 'Ruang Staf',
            'responsible_person' => 'Pengelola Barang',
            'status' => OperationalAssetStatus::Inspection,
            'is_active' => true,
        ]);
        $record = MaintenanceRecord::query()->create([
            'maintenance_number' => 'MTC/2026/08/0002',
            'operational_asset_id' => $asset->id,
            'operational_asset_snapshot' => 'AST-PC-001 | Dell OptiPlex',
            'operational_asset_status_before' => OperationalAssetStatus::Available,
            'reported_by' => $admin->id,
            'maintenance_type' => 'Pemeliharaan perangkat',
            'complaint' => 'Perangkat tidak dapat digunakan.',
            'initial_condition' => 'Perangkat menyala tetapi fungsi utama gagal.',
            'reported_date' => '2026-08-28',
            'status' => MaintenanceStatus::Reported,
        ]);
        $this->history($record, $admin);

        $this->actingAs($admin)
            ->get(route('maintenance-records.pdf', $record))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $verification = DocumentVerification::query()
            ->where('document_type', 'maintenance_record')
            ->where(
                'verifiable_type',
                $record->getMorphClass(),
            )
            ->where('verifiable_id', $record->id)
            ->firstOrFail();

        $this->assertSame(64, strlen($verification->payload_hash));
    }

    public function test_unchanged_pdf_reuses_verification_and_changed_status_creates_a_new_version(): void
    {
        $admin = $this->admin();
        $record = $this->vehicleRecord($admin);

        $this->actingAs($admin)
            ->get(route('maintenance-records.pdf', $record))
            ->assertOk();
        $this->actingAs($admin)
            ->get(route('maintenance-records.pdf', $record))
            ->assertOk();

        $this->assertDatabaseCount('document_verifications', 1);

        $record->forceFill([
            'status' => MaintenanceStatus::Approved,
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ])->save();
        $this->history(
            $record,
            $admin,
            MaintenanceStatus::Reported,
            MaintenanceStatus::Approved,
        );

        $this->actingAs($admin)
            ->get(route('maintenance-records.pdf', $record->fresh()))
            ->assertOk();

        $this->assertDatabaseHas('document_verifications', [
            'document_type' => 'maintenance_record',
            'verifiable_id' => $record->id,
            'version' => 2,
        ]);
    }

    public function test_tampered_maintenance_evidence_blocks_pdf_download(): void
    {
        $admin = $this->admin();
        $record = $this->vehicleRecord($admin);
        $path = 'maintenance-records/'.$record->public_id.'/before.png';
        $binary = 'original-maintenance-evidence';
        Storage::disk('local')->put($path, $binary);

        $record->attachments()->create([
            'file_category' => AttachmentCategory::MaintenanceBefore,
            'disk' => 'local',
            'original_name' => 'before.png',
            'stored_name' => 'before.png',
            'file_path' => $path,
            'mime_type' => 'image/png',
            'file_size' => strlen($binary),
            'checksum' => hash('sha256', $binary),
            'metadata' => ['stage' => 'reported'],
            'uploaded_by' => $admin->id,
        ]);

        Storage::disk('local')->put(
            $path,
            'tampered-maintenance-evidence',
        );

        $this->actingAs($admin)
            ->get(route('maintenance-records.pdf', $record))
            ->assertStatus(409);

        $this->assertDatabaseCount('document_verifications', 0);
        $this->assertDatabaseMissing('audit_logs', [
            'event' => 'maintenance_pdf_downloaded',
        ]);
    }

    public function test_current_official_change_creates_a_new_verification_version(): void
    {
        $admin = $this->admin();
        User::factory()->create([
            'employee_number' => 'SIM-JBG-017',
            'name' => 'PENGELOLA BARANG AKTIF',
            'status' => AccountStatus::Active,
        ]);
        $kasubbag = User::factory()->create([
            'employee_number' => 'SIM-JBG-020',
            'name' => 'KASUBBAG AKTIF',
            'status' => AccountStatus::Active,
        ]);
        $record = $this->vehicleRecord($admin);

        $this->actingAs($admin)
            ->get(route('maintenance-records.pdf', $record))
            ->assertOk();

        $kasubbag->forceFill([
            'name' => 'KASUBBAG PENGGANTI',
        ])->save();

        $this->actingAs($admin)
            ->get(route('maintenance-records.pdf', $record->fresh()))
            ->assertOk();

        $this->assertDatabaseHas('document_verifications', [
            'document_type' => 'maintenance_record',
            'verifiable_id' => $record->id,
            'version' => 2,
        ]);
    }

    public function test_employee_cannot_download_maintenance_pdf(): void
    {
        $admin = $this->admin();
        $employee = User::factory()->create([
            'status' => AccountStatus::Active,
        ]);
        $employee->assignRole(RoleName::Employee->value);
        $record = $this->vehicleRecord($admin);

        $this->actingAs($employee)
            ->get(route('maintenance-records.pdf', $record))
            ->assertForbidden();
    }

    private function vehicleRecord(User $admin): MaintenanceRecord
    {
        $vehicle = Vehicle::query()->create([
            'vehicle_code' => 'KND-PDF-001',
            'license_plate' => 'S 1001 XX',
            'brand' => 'Honda',
            'model' => 'Vario',
            'year' => 2025,
            'current_odometer' => 1250,
            'status' => VehicleStatus::Inspection,
            'is_active' => true,
        ]);
        $record = MaintenanceRecord::query()->create([
            'public_id' => (string) Str::uuid(),
            'maintenance_number' => 'MTC/2026/08/0001',
            'vehicle_id' => $vehicle->id,
            'vehicle_snapshot' => 'KND-PDF-001 | S 1001 XX | Honda Vario',
            'vehicle_status_before' => VehicleStatus::Available,
            'reported_by' => $admin->id,
            'maintenance_type' => 'Pemeliharaan korektif',
            'complaint' => 'Kendaraan memerlukan pemeriksaan sistem pengereman.',
            'initial_condition' => 'Rem terasa kurang responsif saat digunakan.',
            'reported_date' => '2026-08-28',
            'status' => MaintenanceStatus::Reported,
        ]);
        $this->history($record, $admin);

        return $record;
    }

    private function history(
        MaintenanceRecord $record,
        User $admin,
        ?MaintenanceStatus $previous = null,
        MaintenanceStatus $next = MaintenanceStatus::Reported,
    ): void {
        MaintenanceStatusHistory::query()->create([
            'maintenance_record_id' => $record->id,
            'previous_status' => $previous,
            'new_status' => $next,
            'notes' => 'Riwayat pemeliharaan untuk pengujian PDF.',
            'changed_by' => $admin->id,
            'changed_at' => now(),
        ]);
    }

    private function admin(): User
    {
        $admin = User::factory()->create([
            'status' => AccountStatus::Active,
            'name' => 'Administrator PDF',
            'position' => 'Pengelola Barang',
        ]);
        $admin->assignRole(RoleName::Administrator->value);

        return $admin;
    }
}
