<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\MaintenanceStatus;
use App\Enums\MaintenanceSubjectType;
use App\Enums\OperationalAssetStatus;
use App\Enums\OperationalAssetType;
use App\Enums\RoleName;
use App\Enums\VehicleStatus;
use App\Models\MaintenanceRecord;
use App\Models\OperationalAsset;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Tests\TestCase;

class OperationalAssetMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
        Carbon::setTestNow('2026-08-25 03:00:00 UTC');
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_admin_can_create_update_and_view_bmn_operational_asset(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('operational-assets.store'), $this->assetPayload())
            ->assertRedirect();

        $asset = OperationalAsset::query()->firstOrFail();
        $this->assertSame(OperationalAssetType::Pc, $asset->type);
        $this->assertSame(OperationalAssetStatus::Available, $asset->status);
        $this->assertSame('3100102001', $asset->bmn_code);
        $this->assertSame('35', $asset->nup);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'operational_asset_created',
            'auditable_id' => $asset->id,
        ]);

        $this->actingAs($admin)
            ->put(route('operational-assets.update', $asset), $this->assetPayload([
                'location' => '00001 - Ruang PST',
                'responsible_person' => 'Pengelola BMN',
            ]))
            ->assertRedirect(route('operational-assets.show', $asset));

        $this->assertSame('00001 - Ruang PST', $asset->refresh()->location);
        $this->actingAs($admin)
            ->get(route('operational-assets.show', $asset))
            ->assertOk()
            ->assertSee('3100102001')
            ->assertSee('NUP')
            ->assertSee('Pengelola BMN');
    }

    public function test_pc_laptop_and_printer_are_supported_as_individual_assets(): void
    {
        $admin = $this->admin();

        foreach (OperationalAssetType::cases() as $index => $type) {
            $this->actingAs($admin)
                ->post(route('operational-assets.store'), $this->assetPayload([
                    'asset_code' => 'AST-'.($index + 1),
                    'bmn_code' => '310010200'.($index + 1),
                    'nup' => (string) ($index + 1),
                    'register_code' => 'REGISTER-'.($index + 1),
                    'serial_number' => 'SERIAL-'.($index + 1),
                    'type' => $type->value,
                ]))
                ->assertRedirect();
        }

        $this->assertDatabaseCount('operational_assets', 3);
        foreach (OperationalAssetType::cases() as $type) {
            $this->assertDatabaseHas('operational_assets', [
                'type' => $type->value,
            ]);
        }
    }

    public function test_bmn_code_and_nup_pair_must_remain_unique(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('operational-assets.store'), $this->assetPayload())
            ->assertRedirect();

        $this->actingAs($admin)
            ->from(route('operational-assets.create'))
            ->post(route('operational-assets.store'), $this->assetPayload([
                'asset_code' => 'AST-PC-002',
                'register_code' => 'REGISTER-PC-002',
                'serial_number' => 'SERIAL-PC-002',
            ]))
            ->assertRedirect(route('operational-assets.create'))
            ->assertSessionHasErrors('bmn_code');

        $this->assertDatabaseCount('operational_assets', 1);
    }

    public function test_employee_cannot_access_or_manage_operational_assets(): void
    {
        $employee = $this->employee();

        $this->actingAs($employee)
            ->get(route('operational-assets.index'))
            ->assertForbidden();
        $this->actingAs($employee)
            ->post(route('operational-assets.store'), $this->assetPayload())
            ->assertForbidden();

        $this->assertDatabaseCount('operational_assets', 0);
    }

    public function test_asset_maintenance_uses_uuid_evidence_history_audit_and_exact_subject(): void
    {
        $admin = $this->admin();
        $asset = $this->asset();

        $this->actingAs($admin)
            ->post(route('maintenance-records.store'), $this->assetReportPayload($asset))
            ->assertRedirect();

        $record = MaintenanceRecord::query()->firstOrFail();
        $this->assertNotNull($record->public_id);
        $this->assertSame($asset->id, $record->operational_asset_id);
        $this->assertNull($record->vehicle_id);
        $this->assertNull($record->source_vehicle_loan_id);
        $this->assertSame(
            OperationalAssetStatus::Available,
            $record->operational_asset_status_before,
        );
        $this->assertSame(
            OperationalAssetStatus::Inspection,
            $asset->refresh()->status,
        );
        $this->assertSame(1, $record->attachments()->count());
        $this->assertSame(1, $record->statusHistories()->count());
        $attachment = $record->attachments()->firstOrFail();
        $this->assertSame(64, strlen($attachment->checksum));
        Storage::disk($attachment->disk)->assertExists($attachment->file_path);
        $this->actingAs($admin)
            ->get(route('maintenance-records.evidence', [$record, $attachment]))
            ->assertOk();
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'maintenance_reported',
            'auditable_id' => $record->id,
        ]);
    }

    public function test_asset_maintenance_is_visible_on_dashboard_and_generic_report(): void
    {
        [$admin, $asset, $record] = $this->assetRecord();

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee($asset->asset_code)
            ->assertSee($record->maintenance_number);

        $this->actingAs($admin)
            ->get(route('reports.index', [
                'report' => 'maintenance',
                'q' => $asset->asset_code,
            ]))
            ->assertOk()
            ->assertSee('Pemeliharaan Aset dan Kendaraan')
            ->assertSee($asset->asset_code)
            ->assertSee($record->maintenance_number);
    }

    public function test_asset_maintenance_can_complete_and_restore_available_status(): void
    {
        [$admin, $asset, $record] = $this->assetRecord();
        $this->approveAndStart($admin, $record);

        $this->assertSame(
            OperationalAssetStatus::Maintenance,
            $asset->refresh()->status,
        );

        $this->actingAs($admin)
            ->post(
                route('maintenance-records.complete', $record),
                $this->completionPayload(MaintenanceStatus::Completed),
            )
            ->assertRedirect(route('maintenance-records.show', $record));

        $this->assertSame(MaintenanceStatus::Completed, $record->refresh()->status);
        $this->assertSame(
            OperationalAssetStatus::Available,
            $asset->refresh()->status,
        );
        $this->assertSame(2, $record->attachments()->count());
    }

    public function test_action_forms_follow_current_maintenance_status(): void
    {
        [$admin, $asset, $record] = $this->assetRecord();

        $this->actingAs($admin)
            ->get(route('maintenance-records.show', $record))
            ->assertOk()
            ->assertSee('Setujui Pemeliharaan')
            ->assertDontSee('Mulai Pengerjaan')
            ->assertDontSee('Catat Hasil Pemeliharaan')
            ->assertSee('Batalkan Pemeliharaan');

        $this->actingAs($admin)
            ->post(route('maintenance-records.approve', $record), [])
            ->assertRedirect();

        $this->actingAs($admin)
            ->get(route('maintenance-records.show', $record->refresh()))
            ->assertOk()
            ->assertDontSee('Setujui Pemeliharaan')
            ->assertSee('Mulai Pengerjaan')
            ->assertDontSee('Catat Hasil Pemeliharaan')
            ->assertSee('Batalkan Pemeliharaan');

        $this->actingAs($admin)
            ->post(route('maintenance-records.start', $record), [
                'start_date' => '2026-08-25',
                'service_provider' => 'Teknisi Internal',
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->get(route('maintenance-records.show', $record->refresh()))
            ->assertOk()
            ->assertDontSee('Setujui Pemeliharaan')
            ->assertDontSee('Mulai Pengerjaan')
            ->assertSee('Catat Hasil Pemeliharaan')
            ->assertSee('Batalkan Pemeliharaan');

        $this->actingAs($admin)
            ->post(
                route('maintenance-records.complete', $record),
                $this->completionPayload(MaintenanceStatus::Completed),
            )
            ->assertRedirect();

        $this->actingAs($admin)
            ->get(route('maintenance-records.show', $record->refresh()))
            ->assertOk()
            ->assertDontSee('Setujui Pemeliharaan')
            ->assertDontSee('Mulai Pengerjaan')
            ->assertDontSee('Catat Hasil Pemeliharaan')
            ->assertDontSee('Batalkan Pemeliharaan');
    }

    public function test_duplicate_active_ticket_and_deactivation_are_blocked(): void
    {
        [$admin, $asset, $record] = $this->assetRecord();

        $this->actingAs($admin)
            ->from(route('maintenance-records.create'))
            ->post(route('maintenance-records.store'), $this->assetReportPayload($asset))
            ->assertSessionHasErrors('operational_asset_public_id');

        $this->actingAs($admin)
            ->from(route('operational-assets.show', $asset))
            ->patch(route('operational-assets.deactivate', $asset))
            ->assertSessionHasErrors('operational_asset');

        $this->assertTrue($asset->refresh()->is_active);
        $this->assertSame(1, MaintenanceRecord::query()->count());
    }

    public function test_further_action_keeps_asset_in_maintenance_and_can_restart(): void
    {
        [$admin, $asset, $record] = $this->assetRecord();
        $this->approveAndStart($admin, $record);

        $this->actingAs($admin)
            ->post(
                route('maintenance-records.complete', $record),
                $this->completionPayload(MaintenanceStatus::FurtherActionRequired),
            )
            ->assertRedirect();

        $this->assertSame(
            OperationalAssetStatus::Maintenance,
            $asset->refresh()->status,
        );

        Carbon::setTestNow('2026-08-26 03:00:00 UTC');
        $this->actingAs($admin)
            ->post(route('maintenance-records.start', $record), [
                'start_date' => '2026-08-26',
                'service_provider' => 'Teknisi Lanjutan',
            ])
            ->assertRedirect();

        $this->assertSame(MaintenanceStatus::InProgress, $record->refresh()->status);
    }

    public function test_terminal_outcomes_mark_asset_damaged_or_inactive(): void
    {
        $admin = $this->admin();
        $damagedAsset = $this->asset(['asset_code' => 'AST-DAMAGED']);
        $damagedRecord = $this->reportAsset($admin, $damagedAsset);
        $this->approveAndStart($admin, $damagedRecord);

        $this->actingAs($admin)
            ->post(
                route('maintenance-records.complete', $damagedRecord),
                $this->completionPayload(MaintenanceStatus::SeverelyDamaged),
            )
            ->assertRedirect();
        $this->assertSame(
            OperationalAssetStatus::Damaged,
            $damagedAsset->refresh()->status,
        );

        $inactiveAsset = $this->asset([
            'asset_code' => 'AST-INACTIVE',
            'bmn_code' => '3100102002',
            'nup' => '36',
            'register_code' => 'REGISTER-INACTIVE',
            'serial_number' => 'SERIAL-INACTIVE',
        ]);
        $inactiveRecord = $this->reportAsset($admin, $inactiveAsset);
        $this->approveAndStart($admin, $inactiveRecord);
        $this->actingAs($admin)
            ->post(
                route('maintenance-records.complete', $inactiveRecord),
                $this->completionPayload(MaintenanceStatus::Unserviceable),
            )
            ->assertRedirect();

        $inactiveAsset->refresh();
        $this->assertSame(OperationalAssetStatus::Inactive, $inactiveAsset->status);
        $this->assertFalse($inactiveAsset->is_active);
    }

    public function test_cancellation_restores_previous_asset_status(): void
    {
        $admin = $this->admin();
        $asset = $this->asset([
            'status' => OperationalAssetStatus::Damaged,
        ]);
        $record = $this->reportAsset($admin, $asset);

        $this->actingAs($admin)
            ->post(route('maintenance-records.cancel', $record), [
                'cancellation_reason' => 'Pemeriksaan ulang menunjukkan tiket dibuat pada perangkat yang keliru.',
            ])
            ->assertRedirect();

        $this->assertSame(MaintenanceStatus::Cancelled, $record->refresh()->status);
        $this->assertSame(
            OperationalAssetStatus::Damaged,
            $asset->refresh()->status,
        );
    }

    public function test_vehicle_loan_source_is_prohibited_for_operational_asset(): void
    {
        $admin = $this->admin();
        $asset = $this->asset();

        $this->actingAs($admin)
            ->from(route('maintenance-records.create'))
            ->post(route('maintenance-records.store'), $this->assetReportPayload($asset, [
                'source_vehicle_loan_public_id' => fake()->uuid(),
            ]))
            ->assertSessionHasErrors('source_vehicle_loan_public_id');

        $this->assertDatabaseCount('maintenance_records', 0);
    }

    public function test_model_rejects_two_maintenance_subjects(): void
    {
        $admin = $this->admin();
        $asset = $this->asset();
        $vehicle = $this->vehicle();

        $this->expectException(LogicException::class);
        MaintenanceRecord::query()->create([
            'maintenance_number' => 'MTC/2026/08/9999',
            'vehicle_id' => $vehicle->id,
            'operational_asset_id' => $asset->id,
            'vehicle_snapshot' => 'KND-TEST',
            'operational_asset_snapshot' => 'AST-PC-001',
            'reported_by' => $admin->id,
            'maintenance_type' => 'Uji invariant',
            'complaint' => 'Dua subjek tidak boleh tersimpan.',
            'initial_condition' => 'Data invalid untuk pengujian.',
            'reported_date' => '2026-08-25',
            'status' => MaintenanceStatus::Reported,
        ]);
    }

    public function test_model_rejects_maintenance_without_a_subject(): void
    {
        $admin = $this->admin();

        $this->expectException(LogicException::class);
        MaintenanceRecord::query()->create([
            'maintenance_number' => 'MTC/2026/08/9998',
            'vehicle_id' => null,
            'operational_asset_id' => null,
            'vehicle_snapshot' => null,
            'operational_asset_snapshot' => null,
            'reported_by' => $admin->id,
            'maintenance_type' => 'Uji invariant',
            'complaint' => 'Subjek kosong tidak boleh tersimpan.',
            'initial_condition' => 'Data invalid untuk pengujian.',
            'reported_date' => '2026-08-25',
            'status' => MaintenanceStatus::Reported,
        ]);
    }

    public function test_asset_routes_do_not_collide_with_uuid_maintenance_routes(): void
    {
        $admin = $this->admin();
        $asset = $this->asset();

        $this->actingAs($admin)
            ->get('/pemeliharaan/aset-perangkat')
            ->assertOk()
            ->assertSee('PC, Laptop, dan Printer');
        $this->actingAs($admin)
            ->get(route('operational-assets.show', $asset))
            ->assertOk()
            ->assertSee($asset->asset_code);
    }

    /**
     * @return array{User, OperationalAsset, MaintenanceRecord}
     */
    private function assetRecord(): array
    {
        $admin = $this->admin();
        $asset = $this->asset();

        return [$admin, $asset, $this->reportAsset($admin, $asset)];
    }

    private function reportAsset(
        User $admin,
        OperationalAsset $asset,
    ): MaintenanceRecord {
        $this->actingAs($admin)
            ->post(route('maintenance-records.store'), $this->assetReportPayload($asset))
            ->assertRedirect();

        return MaintenanceRecord::query()
            ->where('operational_asset_id', $asset->id)
            ->firstOrFail();
    }

    private function approveAndStart(User $admin, MaintenanceRecord $record): void
    {
        $this->actingAs($admin)
            ->post(route('maintenance-records.approve', $record), [])
            ->assertRedirect();
        $this->actingAs($admin)
            ->post(route('maintenance-records.start', $record), [
                'start_date' => '2026-08-25',
                'service_provider' => 'Teknisi Rekanan BPS',
            ])
            ->assertRedirect();

        $record->refresh();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function assetPayload(array $overrides = []): array
    {
        return [
            'asset_code' => 'AST-PC-001',
            'bmn_code' => '3100102001',
            'nup' => '35',
            'register_code' => 'E2AE0203F728119AE0531261F20AB77E',
            'type' => OperationalAssetType::Pc->value,
            'brand' => 'Dell',
            'model' => 'OptiPlex',
            'serial_number' => 'SERIAL-PC-001',
            'acquisition_year' => 2021,
            'location' => '00004 - Ruang Staf',
            'responsible_person' => 'Pengelola Barang',
            'status' => OperationalAssetStatus::Available->value,
            'notes' => 'Referensi master aset BMN.',
            'is_active' => true,
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function asset(array $attributes = []): OperationalAsset
    {
        return OperationalAsset::query()->create([
            ...$this->assetPayload(),
            ...$attributes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function assetReportPayload(
        OperationalAsset $asset,
        array $overrides = [],
    ): array {
        return [
            'subject_type' => MaintenanceSubjectType::OperationalAsset->value,
            'operational_asset_public_id' => $asset->public_id,
            'maintenance_type' => 'Pemeliharaan korektif perangkat',
            'complaint' => 'Perangkat tidak dapat digunakan untuk pekerjaan operasional.',
            'initial_condition' => 'Perangkat menyala tetapi fungsi utama tidak bekerja.',
            'reported_date' => '2026-08-25',
            'photo_before' => UploadedFile::fake()
                ->image('asset-before.jpg', 160, 120)
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
            'completion_date' => '2026-08-25',
            'cost' => 350000,
            'result' => 'Komponen diperiksa dan perbaikan perangkat selesai dilakukan.',
            'final_condition' => 'Fungsi utama perangkat telah diverifikasi oleh pengelola.',
            'photo_after' => UploadedFile::fake()
                ->image('asset-after.jpg', 160, 120)
                ->size(180),
        ];
    }

    private function admin(): User
    {
        $user = User::factory()->create([
            'status' => AccountStatus::Active,
            'phone' => fake()->unique()->numerify('0812########'),
        ]);
        $user->assignRole(RoleName::Administrator->value);

        return $user;
    }

    private function employee(): User
    {
        $user = User::factory()->create([
            'status' => AccountStatus::Active,
            'phone' => fake()->unique()->numerify('0813########'),
        ]);
        $user->assignRole(RoleName::Employee->value);

        return $user;
    }

    private function vehicle(): Vehicle
    {
        return Vehicle::query()->create([
            'vehicle_code' => 'KND-TEST-001',
            'license_plate' => 'S 1001 XX',
            'brand' => 'Honda',
            'model' => 'Vario',
            'year' => 2025,
            'current_odometer' => 1000,
            'status' => VehicleStatus::Available,
            'is_active' => true,
        ]);
    }
}
