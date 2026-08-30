<?php

namespace Tests\Feature;

use App\Enums\AttachmentCategory;
use App\Enums\ConditionCheckType;
use App\Enums\DigitalSignaturePurpose;
use App\Enums\MaintenanceStatus;
use App\Enums\OperationalAssetStatus;
use App\Enums\OperationalAssetType;
use App\Enums\VehicleLoanStatus;
use App\Enums\VehicleOverallCondition;
use App\Enums\VehicleStatus;
use App\Models\MaintenanceRecord;
use App\Models\OperationalAsset;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleLoan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class VehicleLoanUatCleanupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_dry_run_does_not_change_database_or_files(): void
    {
        $fixture = $this->uatFixture();

        $this->artisan('simantap:cleanup-vehicle-loans')
            ->assertSuccessful();

        $this->assertDatabaseHas('vehicle_loans', [
            'id' => $fixture['loan']->id,
        ]);
        $this->assertDatabaseHas('maintenance_records', [
            'id' => $fixture['linked_maintenance']->id,
        ]);
        Storage::disk('local')->assertExists($fixture['attachment_path']);
        Storage::disk('local')->assertExists($fixture['signature_path']);
        $this->assertSame([], Storage::disk('local')->allFiles(
            'cleanup-manifests',
        ));
    }

    public function test_execute_requires_backup_and_exact_confirmation(): void
    {
        $fixture = $this->uatFixture();

        $this->artisan('simantap:cleanup-vehicle-loans', [
            '--execute' => true,
            '--confirmation' => 'HAPUS',
        ])->assertFailed();

        $this->assertDatabaseHas('vehicle_loans', [
            'id' => $fixture['loan']->id,
        ]);

        $this->artisan('simantap:cleanup-vehicle-loans', [
            '--execute' => true,
            '--database-backup' => 'backup-before-cleanup.sql',
            '--private-files-backup' => 'private-before-cleanup.zip',
            '--confirmation' => 'HAPUS-SEMUA-PEMINJAMAN-UAT',
        ])->assertFailed();

        $backups = $this->backupFiles();

        $this->artisan('simantap:cleanup-vehicle-loans', [
            '--execute' => true,
            '--database-backup' => $backups['database'],
            '--private-files-backup' => $backups['private_files'],
            '--confirmation' => 'HAPUS',
        ])->assertFailed();

        $this->assertDatabaseHas('vehicle_loans', [
            'id' => $fixture['loan']->id,
        ]);
        Storage::disk('local')->assertExists($fixture['attachment_path']);
    }

    public function test_execute_removes_uat_graph_and_restores_vehicle_master(): void
    {
        $fixture = $this->uatFixture();
        $backups = $this->backupFiles();

        $this->artisan('simantap:cleanup-vehicle-loans', [
            '--execute' => true,
            '--database-backup' => $backups['database'],
            '--private-files-backup' => $backups['private_files'],
            '--confirmation' => 'HAPUS-SEMUA-PEMINJAMAN-UAT',
        ])->assertSuccessful();

        $this->assertDatabaseCount('vehicle_loans', 0);
        $this->assertDatabaseCount('vehicle_loan_status_histories', 0);
        $this->assertDatabaseCount('vehicle_condition_checks', 0);
        $this->assertDatabaseCount('digital_signatures', 0);
        $this->assertDatabaseCount('document_verifications', 0);

        $this->assertDatabaseMissing('maintenance_records', [
            'id' => $fixture['linked_maintenance']->id,
        ]);
        $this->assertDatabaseHas('maintenance_records', [
            'id' => $fixture['manual_maintenance']->id,
        ]);
        $this->assertDatabaseCount('maintenance_status_histories', 1);

        $this->assertDatabaseCount('attachments', 1);
        $this->assertDatabaseHas('attachments', [
            'id' => $fixture['unrelated_attachment_id'],
        ]);
        Storage::disk('local')->assertMissing($fixture['attachment_path']);
        Storage::disk('local')->assertMissing(
            $fixture['maintenance_attachment_path'],
        );
        Storage::disk('local')->assertMissing($fixture['signature_path']);
        Storage::disk('local')->assertExists(
            $fixture['unrelated_attachment_path'],
        );

        $vehicle = $fixture['vehicle']->refresh();
        $this->assertSame(VehicleStatus::Available, $vehicle->status);
        $this->assertTrue($vehicle->is_active);
        $this->assertSame('1000.0', $vehicle->current_odometer);

        $this->assertDatabaseMissing('document_sequences', [
            'document_type' => 'LOAN',
        ]);
        $this->assertDatabaseHas('document_sequences', [
            'document_type' => 'MTC',
            'last_number' => 7,
        ]);
        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseHas('notifications', [
            'id' => $fixture['unrelated_notification_id'],
        ]);
        $this->assertDatabaseMissing('audit_logs', [
            'event' => 'vehicle_loan_return_issue',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'unrelated_inventory_event',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'uat_vehicle_loan_cleanup_completed',
            'module' => 'system',
        ]);
        $this->assertCount(
            1,
            Storage::disk('local')->allFiles('cleanup-manifests'),
        );

        $manifestPath = Storage::disk('local')->allFiles(
            'cleanup-manifests',
        )[0];
        $manifest = json_decode(
            (string) Storage::disk('local')->get($manifestPath),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertSame(
            hash_file('sha256', $backups['database']),
            $manifest['execution']['backup_reference']['database']['sha256'],
        );
        $this->assertSame(
            hash_file('sha256', $backups['private_files']),
            $manifest['execution']['backup_reference']['private_files']['sha256'],
        );
    }

    public function test_keep_odometer_option_preserves_current_master_value(): void
    {
        $fixture = $this->uatFixture();
        $backups = $this->backupFiles();

        $this->artisan('simantap:cleanup-vehicle-loans', [
            '--execute' => true,
            '--database-backup' => $backups['database'],
            '--private-files-backup' => $backups['private_files'],
            '--confirmation' => 'HAPUS-SEMUA-PEMINJAMAN-UAT',
            '--keep-odometers' => true,
        ])->assertSuccessful();

        $vehicle = $fixture['vehicle']->refresh();

        $this->assertSame(VehicleStatus::Available, $vehicle->status);
        $this->assertSame('1400.0', $vehicle->current_odometer);
    }

    public function test_include_all_maintenance_removes_uat_records_and_restores_subjects(): void
    {
        $fixture = $this->uatFixture();
        $backups = $this->backupFiles();
        $asset = OperationalAsset::query()->create([
            'asset_code' => 'AST-UAT-001',
            'type' => OperationalAssetType::Pc,
            'brand' => 'Dell',
            'model' => 'OptiPlex UAT',
            'status' => OperationalAssetStatus::Inactive,
            'is_active' => false,
        ]);
        $assetMaintenance = MaintenanceRecord::withoutEvents(
            fn (): MaintenanceRecord => MaintenanceRecord::query()->create([
                'public_id' => (string) Str::uuid(),
                'maintenance_number' => 'MTC/2026/08/0098',
                'operational_asset_id' => $asset->id,
                'operational_asset_snapshot' => $asset->asset_code,
                'operational_asset_status_before' => OperationalAssetStatus::Available,
                'reported_by' => $fixture['user']->id,
                'maintenance_type' => 'UAT pemeliharaan aset perangkat',
                'complaint' => 'Data uji harus dibersihkan.',
                'initial_condition' => 'UAT tidak layak.',
                'reported_date' => now()->toDateString(),
                'status' => MaintenanceStatus::Unserviceable,
            ]),
        );

        DB::table('maintenance_status_histories')->insert([
            'maintenance_record_id' => $assetMaintenance->id,
            'previous_status' => MaintenanceStatus::InProgress->value,
            'new_status' => MaintenanceStatus::Unserviceable->value,
            'notes' => 'UAT aset perangkat.',
            'changed_by' => $fixture['user']->id,
            'changed_at' => now(),
        ]);

        $assetAttachmentPath = 'maintenance/'.$assetMaintenance->public_id
            .'/after.jpg';
        Storage::disk('local')->put($assetAttachmentPath, 'uat asset evidence');
        DB::table('attachments')->insert([
            'attachable_type' => 'maintenance_record',
            'attachable_id' => $assetMaintenance->id,
            'file_category' => AttachmentCategory::MaintenanceAfter->value,
            'disk' => 'local',
            'original_name' => 'after.jpg',
            'stored_name' => 'after.jpg',
            'file_path' => $assetAttachmentPath,
            'mime_type' => 'image/jpeg',
            'file_size' => 18,
            'checksum' => hash('sha256', 'uat asset evidence'),
            'metadata' => null,
            'uploaded_by' => $fixture['user']->id,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ]);
        DB::table('document_verifications')->insert([
            'public_token' => Str::random(64),
            'document_type' => 'maintenance_record',
            'verifiable_type' => 'maintenance_record',
            'verifiable_id' => $assetMaintenance->id,
            'document_reference' => $assetMaintenance->maintenance_number,
            'version' => 1,
            'payload_schema_version' => 1,
            'hash_algorithm' => 'sha256',
            'payload_hash' => hash('sha256', 'asset-maintenance-payload'),
            'public_metadata' => null,
            'issued_by' => $fixture['user']->id,
            'issued_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->insertAudit(
            'maintenance_pdf_downloaded',
            'maintenance',
            'maintenance_record',
            $assetMaintenance->id,
            $fixture['user']->id,
        );
        $this->insertNotification(
            $fixture['user'],
            MaintenanceRecord::class,
            $assetMaintenance->id,
        );

        $this->artisan('simantap:cleanup-vehicle-loans', [
            '--execute' => true,
            '--database-backup' => $backups['database'],
            '--private-files-backup' => $backups['private_files'],
            '--confirmation' => 'HAPUS-SEMUA-PEMINJAMAN-UAT',
            '--include-all-maintenance' => true,
        ])->assertSuccessful();

        $this->assertDatabaseCount('vehicle_loans', 0);
        $this->assertDatabaseCount('maintenance_records', 0);
        $this->assertDatabaseCount('maintenance_status_histories', 0);
        $this->assertDatabaseCount('attachments', 0);
        $this->assertDatabaseCount('document_verifications', 0);
        $this->assertDatabaseMissing('audit_logs', [
            'event' => 'maintenance_pdf_downloaded',
        ]);
        $this->assertDatabaseMissing('document_sequences', [
            'document_type' => 'LOAN',
        ]);
        $this->assertDatabaseMissing('document_sequences', [
            'document_type' => 'MTC',
        ]);
        Storage::disk('local')->assertMissing($assetAttachmentPath);
        Storage::disk('local')->assertMissing(
            $fixture['unrelated_attachment_path'],
        );

        $vehicle = $fixture['vehicle']->refresh();
        $this->assertSame(VehicleStatus::Available, $vehicle->status);
        $this->assertTrue($vehicle->is_active);
        $this->assertSame('1000.0', $vehicle->current_odometer);

        $asset->refresh();
        $this->assertSame(OperationalAssetStatus::Available, $asset->status);
        $this->assertTrue($asset->is_active);
    }

    public function test_manual_active_maintenance_preserves_vehicle_state(): void
    {
        $fixture = $this->uatFixture();
        $backups = $this->backupFiles();
        $vehicle = $fixture['vehicle'];

        $vehicle->forceFill([
            'status' => VehicleStatus::Maintenance,
            'is_active' => true,
            'current_odometer' => 1500.0,
        ])->save();

        $manualMaintenance = MaintenanceRecord::withoutEvents(
            fn (): MaintenanceRecord => MaintenanceRecord::query()->create([
                'public_id' => (string) Str::uuid(),
                'maintenance_number' => 'MTC/2026/08/0099',
                'vehicle_id' => $vehicle->id,
                'source_vehicle_loan_id' => null,
                'vehicle_snapshot' => $vehicle->vehicle_code,
                'vehicle_status_before' => VehicleStatus::Available,
                'reported_by' => $fixture['user']->id,
                'maintenance_type' => 'Pemeliharaan manual aktif',
                'complaint' => 'Harus dipertahankan saat cleanup.',
                'initial_condition' => 'Perlu pemeriksaan.',
                'reported_date' => now()->toDateString(),
                'status' => MaintenanceStatus::InProgress,
            ]),
        );

        $this->artisan('simantap:cleanup-vehicle-loans', [
            '--execute' => true,
            '--database-backup' => $backups['database'],
            '--private-files-backup' => $backups['private_files'],
            '--confirmation' => 'HAPUS-SEMUA-PEMINJAMAN-UAT',
        ])->assertSuccessful();

        $this->assertDatabaseHas('maintenance_records', [
            'id' => $manualMaintenance->id,
            'source_vehicle_loan_id' => null,
        ]);

        $vehicle->refresh();

        $this->assertSame(VehicleStatus::Maintenance, $vehicle->status);
        $this->assertTrue($vehicle->is_active);
        $this->assertSame('1500.0', $vehicle->current_odometer);
    }

    public function test_cleanup_cannot_run_again_against_operational_loans(): void
    {
        $fixture = $this->uatFixture();
        $backups = $this->backupFiles();
        $arguments = [
            '--execute' => true,
            '--database-backup' => $backups['database'],
            '--private-files-backup' => $backups['private_files'],
            '--confirmation' => 'HAPUS-SEMUA-PEMINJAMAN-UAT',
        ];

        $this->artisan('simantap:cleanup-vehicle-loans', $arguments)
            ->assertSuccessful();

        $operationalLoan = VehicleLoan::withoutEvents(
            fn (): VehicleLoan => VehicleLoan::query()->forceCreate([
                'public_id' => (string) Str::uuid(),
                'loan_number' => 'LOAN/2026/08/0001',
                'borrower_id' => $fixture['user']->id,
                'borrower_name_snapshot' => $fixture['user']->name,
                'employee_number_snapshot' => $fixture['user']->employee_number,
                'work_unit_snapshot' => $fixture['user']->work_unit,
                'phone_snapshot' => $fixture['user']->phone,
                'vehicle_id' => $fixture['vehicle']->id,
                'vehicle_code_snapshot' => $fixture['vehicle']->vehicle_code,
                'license_plate_snapshot' => $fixture['vehicle']->license_plate,
                'vehicle_name_snapshot' => $fixture['vehicle']->displayName(),
                'purpose' => 'Transaksi operasional pertama',
                'destination' => 'Lokasi kegiatan resmi',
                'planned_start_at' => now()->addDay(),
                'planned_end_at' => now()->addDays(2),
                'status' => VehicleLoanStatus::Draft,
            ]),
        );

        $this->artisan('simantap:cleanup-vehicle-loans', $arguments)
            ->assertFailed();

        $this->assertDatabaseHas('vehicle_loans', [
            'id' => $operationalLoan->id,
            'purpose' => 'Transaksi operasional pertama',
        ]);
        $this->assertDatabaseCount('audit_logs', 2);
    }

    /**
     * @return array{database: string, private_files: string}
     */
    private function backupFiles(): array
    {
        Storage::disk('local')->put(
            'backups/simantap-before-cleanup.sql',
            'mysql backup fixture',
        );
        Storage::disk('local')->put(
            'backups/private-before-cleanup.zip',
            'private files backup fixture',
        );

        return [
            'database' => Storage::disk('local')->path(
                'backups/simantap-before-cleanup.sql',
            ),
            'private_files' => Storage::disk('local')->path(
                'backups/private-before-cleanup.zip',
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function uatFixture(): array
    {
        $user = User::factory()->create();
        $vehicle = $this->vehicle([
            'current_odometer' => 1400.0,
            'status' => VehicleStatus::Inactive,
            'is_active' => false,
        ]);
        $unrelatedVehicle = $this->vehicle();
        $loan = VehicleLoan::withoutEvents(fn (): VehicleLoan => VehicleLoan::query()->forceCreate([
            'public_id' => (string) Str::uuid(),
            'loan_number' => 'LOAN/2026/08/0001',
            'borrower_id' => $user->id,
            'borrower_name_snapshot' => $user->name,
            'employee_number_snapshot' => $user->employee_number,
            'work_unit_snapshot' => $user->work_unit,
            'phone_snapshot' => $user->phone,
            'vehicle_id' => $vehicle->id,
            'vehicle_code_snapshot' => $vehicle->vehicle_code,
            'license_plate_snapshot' => $vehicle->license_plate,
            'vehicle_name_snapshot' => $vehicle->displayName(),
            'purpose' => 'Uji lifecycle pengembalian',
            'destination' => 'Lokasi UAT',
            'planned_start_at' => now()->subDays(2),
            'planned_end_at' => now()->subDay(),
            'actual_start_at' => now()->subDays(2),
            'actual_end_at' => now()->subDay(),
            'status' => VehicleLoanStatus::Completed,
        ]));

        DB::table('vehicle_loan_status_histories')->insert([
            'vehicle_loan_id' => $loan->id,
            'previous_status' => VehicleLoanStatus::ReturnIssue->value,
            'new_status' => VehicleLoanStatus::Completed->value,
            'notes' => 'UAT selesai melalui pemeliharaan.',
            'changed_by' => $user->id,
            'changed_at' => now(),
        ]);
        $conditionCheckId = DB::table('vehicle_condition_checks')->insertGetId([
            'vehicle_loan_id' => $loan->id,
            'check_type' => ConditionCheckType::Checkout->value,
            'odometer' => 1000.0,
            'fuel_level' => 80,
            'overall_condition' => VehicleOverallCondition::Good->value,
            'body_condition' => 'Baik',
            'engine_condition' => 'Baik',
            'tire_condition' => 'Baik',
            'equipment_condition' => 'Lengkap',
            'damage_notes' => null,
            'checked_by' => $user->id,
            'checker_name_snapshot' => $user->name,
            'checker_employee_number_snapshot' => $user->employee_number,
            'checked_at' => now()->subDays(2),
            'borrower_confirmed_at' => now()->subDays(2),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $attachmentPath = 'vehicle-loans/'.$loan->public_id.'/checkout/front.jpg';
        Storage::disk('local')->put($attachmentPath, 'uat evidence');
        DB::table('attachments')->insert([
            'attachable_type' => 'vehicle_condition_check',
            'attachable_id' => $conditionCheckId,
            'file_category' => AttachmentCategory::VehicleFront->value,
            'disk' => 'local',
            'original_name' => 'front.jpg',
            'stored_name' => 'front.jpg',
            'file_path' => $attachmentPath,
            'mime_type' => 'image/jpeg',
            'file_size' => 12,
            'checksum' => hash('sha256', 'uat evidence'),
            'metadata' => null,
            'uploaded_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ]);

        $signaturePath = 'signatures/vehicle-loans/'.$loan->public_id.'/return.png';
        Storage::disk('local')->put($signaturePath, 'uat signature');
        DB::table('digital_signatures')->insert([
            'signable_type' => 'vehicle_loan',
            'signable_id' => $loan->id,
            'signer_id' => $user->id,
            'signer_name_snapshot' => $user->name,
            'employee_number_snapshot' => $user->employee_number,
            'purpose' => DigitalSignaturePurpose::VehicleLoanReturnRequest->value,
            'version' => 1,
            'image_path' => $signaturePath,
            'transaction_hash' => hash('sha256', 'uat transaction'),
            'image_checksum' => hash('sha256', 'uat signature'),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'signed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $linkedMaintenance = MaintenanceRecord::withoutEvents(
            fn (): MaintenanceRecord => MaintenanceRecord::query()->create([
                'public_id' => (string) Str::uuid(),
                'maintenance_number' => 'MTC/2026/08/0001',
                'vehicle_id' => $vehicle->id,
                'source_vehicle_loan_id' => $loan->id,
                'vehicle_snapshot' => $vehicle->vehicle_code,
                'vehicle_status_before' => VehicleStatus::Inspection,
                'reported_by' => $user->id,
                'maintenance_type' => 'Perbaikan hasil UAT',
                'complaint' => 'Data uji pengembalian bermasalah',
                'initial_condition' => 'UAT',
                'reported_date' => now()->toDateString(),
                'status' => MaintenanceStatus::Unserviceable,
            ]),
        );
        DB::table('maintenance_status_histories')->insert([
            'maintenance_record_id' => $linkedMaintenance->id,
            'previous_status' => MaintenanceStatus::InProgress->value,
            'new_status' => MaintenanceStatus::Unserviceable->value,
            'notes' => 'UAT tidak layak.',
            'changed_by' => $user->id,
            'changed_at' => now(),
        ]);
        $maintenanceAttachmentPath = 'maintenance/'.$linkedMaintenance->public_id.'/after.jpg';
        Storage::disk('local')->put(
            $maintenanceAttachmentPath,
            'uat maintenance evidence',
        );
        DB::table('attachments')->insert([
            'attachable_type' => 'maintenance_record',
            'attachable_id' => $linkedMaintenance->id,
            'file_category' => AttachmentCategory::MaintenanceAfter->value,
            'disk' => 'local',
            'original_name' => 'after.jpg',
            'stored_name' => 'after.jpg',
            'file_path' => $maintenanceAttachmentPath,
            'mime_type' => 'image/jpeg',
            'file_size' => 24,
            'checksum' => hash('sha256', 'uat maintenance evidence'),
            'metadata' => null,
            'uploaded_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ]);

        foreach ([
            ['vehicle_loan', $loan->id, 'vehicle_loan', $loan->loan_number],
            [
                'maintenance_record',
                $linkedMaintenance->id,
                'maintenance_record',
                $linkedMaintenance->maintenance_number,
            ],
        ] as $index => [$type, $id, $documentType, $reference]) {
            DB::table('document_verifications')->insert([
                'public_token' => Str::random(64),
                'document_type' => $documentType,
                'verifiable_type' => $type,
                'verifiable_id' => $id,
                'document_reference' => $reference,
                'version' => 1,
                'payload_schema_version' => 1,
                'hash_algorithm' => 'sha256',
                'payload_hash' => hash('sha256', 'payload-'.$index),
                'public_metadata' => null,
                'issued_by' => $user->id,
                'issued_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->insertAudit(
            'vehicle_loan_return_issue',
            'vehicle_loan',
            'vehicle_loan',
            $loan->id,
            $user->id,
        );
        $this->insertAudit(
            'maintenance_created',
            'maintenance',
            'maintenance_record',
            $linkedMaintenance->id,
            $user->id,
        );
        $this->insertAudit(
            'unrelated_inventory_event',
            'inventory',
            null,
            null,
            $user->id,
        );

        $this->insertNotification(
            $user,
            VehicleLoan::class,
            $loan->id,
        );
        $this->insertNotification(
            $user,
            MaintenanceRecord::class,
            $linkedMaintenance->id,
        );
        $unrelatedNotificationId = $this->insertNotification(
            $user,
            Vehicle::class,
            $unrelatedVehicle->id,
        );

        DB::table('document_sequences')->insert([
            [
                'document_type' => 'LOAN',
                'year' => 2026,
                'month' => 8,
                'last_number' => 19,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'document_type' => 'MTC',
                'year' => 2026,
                'month' => 8,
                'last_number' => 7,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $manualMaintenance = MaintenanceRecord::withoutEvents(
            fn (): MaintenanceRecord => MaintenanceRecord::query()->create([
                'public_id' => (string) Str::uuid(),
                'maintenance_number' => 'MTC/2026/08/0007',
                'vehicle_id' => $unrelatedVehicle->id,
                'source_vehicle_loan_id' => null,
                'vehicle_snapshot' => $unrelatedVehicle->vehicle_code,
                'vehicle_status_before' => VehicleStatus::Available,
                'reported_by' => $user->id,
                'maintenance_type' => 'Pemeliharaan manual',
                'complaint' => 'Harus dipertahankan',
                'initial_condition' => 'Perlu pemeriksaan',
                'reported_date' => now()->toDateString(),
                'status' => MaintenanceStatus::Reported,
            ]),
        );
        DB::table('maintenance_status_histories')->insert([
            'maintenance_record_id' => $manualMaintenance->id,
            'previous_status' => null,
            'new_status' => MaintenanceStatus::Reported->value,
            'notes' => 'Pemeliharaan manual.',
            'changed_by' => $user->id,
            'changed_at' => now(),
        ]);
        $unrelatedAttachmentPath = 'maintenance/manual/before.jpg';
        Storage::disk('local')->put(
            $unrelatedAttachmentPath,
            'manual evidence',
        );
        $unrelatedAttachmentId = DB::table('attachments')->insertGetId([
            'attachable_type' => 'maintenance_record',
            'attachable_id' => $manualMaintenance->id,
            'file_category' => AttachmentCategory::MaintenanceBefore->value,
            'disk' => 'local',
            'original_name' => 'before.jpg',
            'stored_name' => 'before.jpg',
            'file_path' => $unrelatedAttachmentPath,
            'mime_type' => 'image/jpeg',
            'file_size' => 15,
            'checksum' => hash('sha256', 'manual evidence'),
            'metadata' => null,
            'uploaded_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ]);

        return [
            'user' => $user,
            'vehicle' => $vehicle,
            'loan' => $loan,
            'linked_maintenance' => $linkedMaintenance,
            'manual_maintenance' => $manualMaintenance,
            'attachment_path' => $attachmentPath,
            'maintenance_attachment_path' => $maintenanceAttachmentPath,
            'signature_path' => $signaturePath,
            'unrelated_attachment_id' => $unrelatedAttachmentId,
            'unrelated_attachment_path' => $unrelatedAttachmentPath,
            'unrelated_notification_id' => $unrelatedNotificationId,
        ];
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
            'model' => 'Vario',
            'year' => 2025,
            'current_odometer' => 1000.0,
            'status' => VehicleStatus::Available,
            'registration_expiry_date' => '2027-08-03',
            'storage_location' => 'Garasi',
            'is_active' => true,
            ...$attributes,
        ]);
    }

    private function insertAudit(
        string $event,
        string $module,
        ?string $type,
        ?int $id,
        int $actorId,
    ): void {
        DB::table('audit_logs')->insert([
            'request_id' => (string) Str::uuid(),
            'actor_id' => $actorId,
            'event' => $event,
            'module' => $module,
            'auditable_type' => $type,
            'auditable_id' => $id,
            'old_values' => null,
            'new_values' => null,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'url' => null,
            'http_method' => 'POST',
            'created_at' => now(),
        ]);
    }

    private function insertNotification(
        User $user,
        string $resourceType,
        int $resourceId,
    ): string {
        $id = (string) Str::uuid();
        DB::table('notifications')->insert([
            'id' => $id,
            'type' => 'App\\Notifications\\SystemNotification',
            'notifiable_type' => 'user',
            'notifiable_id' => $user->id,
            'data' => json_encode([
                'event' => 'uat_event',
                'resource_type' => $resourceType,
                'resource_id' => (string) $resourceId,
            ], JSON_THROW_ON_ERROR),
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }
}
