<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\MaintenanceStatus;
use App\Enums\RoleName;
use App\Enums\VehicleStatus;
use App\Models\MaintenanceRecord;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\VehicleControlCardService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleControlCardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_admin_can_download_vehicle_control_card_with_two_identical_cards_and_audit(): void
    {
        $admin = $this->admin([
            'name' => 'Administrator Uji',
            'employee_number' => 'ADM-KK-001',
        ]);

        $vehicle = $this->vehicle([
            'vehicle_code' => 'KND-KK-001',
            'license_plate' => 'S 1234 KK',
            'brand' => 'Honda',
            'model' => 'Vario 160',
            'responsible_person' => 'Pengelola Kendaraan',
        ]);

        $this->maintenance($admin, $vehicle, [
            'maintenance_number' => 'MTC/2026/08/9001',
            'maintenance_type' => 'Pemeliharaan berkala',
            'service_provider' => 'Bengkel Rekanan BPS',
            'completion_date' => '2026-08-08',
        ]);

        $data = app(VehicleControlCardService::class)
            ->build($vehicle);

        $this->assertSame(1, $data['recordCount']);
        $this->assertCount(1, $data['pages']);
        $this->assertCount(20, $data['pages'][0]);
        $this->assertSame(
            '08/08/2026',
            $data['pages'][0][0]['date'],
        );
        $this->assertSame(
            'Pemeliharaan berkala',
            $data['pages'][0][0]['maintenance_type'],
        );
        $this->assertSame(
            'Bengkel Rekanan BPS',
            $data['pages'][0][0]['service_provider'],
        );

        $html = view(
            'vehicles.control-card-pdf',
            $data,
        )->render();

        $this->assertSame(
            2,
            substr_count(
                $html,
                'KARTU KENDALI KENDARAAN',
            ),
        );
        $this->assertSame(
            40,
            substr_count(
                $html,
                'data-control-row="1"',
            ),
        );
        $this->assertSame(
            2,
            substr_count(
                $html,
                'Pemeliharaan berkala',
            ),
        );
        $this->assertStringContainsString(
            'S 1234 KK',
            $html,
        );
        $this->assertStringContainsString(
            'Pengelola Kendaraan',
            $html,
        );
        $this->assertStringNotContainsString(
            'Mohammad Farikhin',
            $html,
        );
        $this->assertStringNotContainsString(
            'Mitha Ramadhani Pratiwi',
            $html,
        );
        $this->assertStringNotContainsString(
            'QR',
            $html,
        );

        $this->actingAs($admin)
            ->get(route('vehicles.show', $vehicle))
            ->assertOk()
            ->assertSee('Kartu Kendali');

        $response = $this->actingAs($admin)
            ->get(route(
                'vehicles.control-card',
                $vehicle,
            ));

        $response->assertOk();
        $response->assertHeader(
            'content-type',
            'application/pdf',
        );

        $this->assertStringContainsString(
            'attachment;',
            (string) $response->headers->get(
                'content-disposition',
            ),
        );

        $this->assertStringStartsWith(
            '%PDF',
            (string) $response->getContent(),
        );

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'vehicle_control_card_downloaded',
            'module' => 'vehicle',
            'auditable_type' => 'vehicle',
            'auditable_id' => $vehicle->id,
            'actor_id' => $admin->id,
        ]);
    }

    public function test_employee_cannot_download_vehicle_control_card_or_see_button(): void
    {
        $employee = $this->employee();
        $vehicle = $this->vehicle();

        $this->actingAs($employee)
            ->get(route('vehicles.show', $vehicle))
            ->assertOk()
            ->assertDontSee('Kartu Kendali');

        $this->actingAs($employee)
            ->get(route(
                'vehicles.control-card',
                $vehicle,
            ))
            ->assertForbidden();
    }

    public function test_control_card_paginates_final_history_and_excludes_nonfinal_or_cancelled_records(): void
    {
        $admin = $this->admin();
        $vehicle = $this->vehicle([
            'vehicle_code' => 'KND-KK-PAGE',
        ]);

        for ($number = 1; $number <= 21; $number++) {
            $this->maintenance($admin, $vehicle, [
                'maintenance_type' => "Servis final {$number}",
                'service_provider' => "Bengkel {$number}",
                'completion_date' => sprintf(
                    '2026-07-%02d',
                    $number,
                ),
                'status' => MaintenanceStatus::Completed,
            ]);
        }

        $this->maintenance($admin, $vehicle, [
            'maintenance_type' => 'JANGAN MASUK - TINDAK LANJUT',
            'service_provider' => 'Bengkel Tindak Lanjut',
            'completion_date' => '2026-07-22',
            'status' => MaintenanceStatus::FurtherActionRequired,
        ]);

        $this->maintenance($admin, $vehicle, [
            'maintenance_type' => 'JANGAN MASUK - DIBATALKAN',
            'service_provider' => 'Bengkel Batal',
            'completion_date' => '2026-07-23',
            'status' => MaintenanceStatus::Cancelled,
        ]);

        $data = app(VehicleControlCardService::class)
            ->build($vehicle);

        $this->assertSame(21, $data['recordCount']);
        $this->assertCount(2, $data['pages']);
        $this->assertCount(20, $data['pages'][0]);
        $this->assertCount(20, $data['pages'][1]);

        $this->assertSame(
            'Servis final 1',
            $data['pages'][0][0]['maintenance_type'],
        );

        $this->assertSame(
            'Servis final 20',
            $data['pages'][0][19]['maintenance_type'],
        );

        $this->assertSame(
            'Servis final 21',
            $data['pages'][1][0]['maintenance_type'],
        );

        $this->assertNull(
            $data['pages'][1][1],
        );

        $html = view(
            'vehicles.control-card-pdf',
            $data,
        )->render();

        $this->assertSame(
            4,
            substr_count(
                $html,
                'KARTU KENDALI KENDARAAN',
            ),
        );

        $this->assertSame(
            80,
            substr_count(
                $html,
                'data-control-row="1"',
            ),
        );

        $this->assertStringNotContainsString(
            'JANGAN MASUK - TINDAK LANJUT',
            $html,
        );

        $this->assertStringNotContainsString(
            'JANGAN MASUK - DIBATALKAN',
            $html,
        );

        $this->assertSame(
            1,
            preg_match(
                '/<td>\s*21\s*<\/td>/',
                $html,
            ),
        );

        $this->assertSame(
            0,
            preg_match(
                '/<td>\s*22\s*<\/td>/',
                $html,
            ),
        );

        $this->assertStringContainsString(
            'font-family: "Times New Roman", Times, serif;',
            $html,
        );

        $this->assertStringContainsString(
            'text-decoration: underline;',
            $html,
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function admin(array $attributes = []): User
    {
        $user = User::factory()->create([
            'status' => AccountStatus::Active,
            ...$attributes,
        ]);

        $user->assignRole(
            RoleName::Administrator->value,
        );

        return $user;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function employee(array $attributes = []): User
    {
        $user = User::factory()->create([
            'status' => AccountStatus::Active,
            ...$attributes,
        ]);

        $user->assignRole(
            RoleName::Employee->value,
        );

        return $user;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function vehicle(array $attributes = []): Vehicle
    {
        return Vehicle::query()->create([
            'vehicle_code' => fake()
                ->unique()
                ->bothify('KND-###??'),
            'license_plate' => fake()
                ->unique()
                ->bothify('S #### ??'),
            'brand' => 'Honda',
            'model' => 'Vario 160 CBS',
            'year' => 2025,
            'color' => 'Hitam',
            'chassis_number' => fake()
                ->unique()
                ->bothify('MH1###############'),
            'engine_number' => fake()
                ->unique()
                ->bothify('ENG############'),
            'current_odometer' => 1000.0,
            'status' => VehicleStatus::Available,
            'registration_expiry_date' => '2027-08-03',
            'storage_location' => 'Garasi Kantor',
            'responsible_person' => 'Pengelola Barang',
            'image_path' => null,
            'notes' => null,
            'is_active' => true,
            ...$attributes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function maintenance(
        User $actor,
        Vehicle $vehicle,
        array $attributes = [],
    ): MaintenanceRecord {
        return MaintenanceRecord::query()->create([
            'maintenance_number' => fake()
                ->unique()
                ->numerify('MTC/2026/08/####'),
            'vehicle_id' => $vehicle->id,
            'source_vehicle_loan_id' => null,
            'vehicle_snapshot' => implode(
                ' | ',
                [
                    $vehicle->vehicle_code,
                    $vehicle->license_plate,
                    $vehicle->displayName(),
                ],
            ),
            'reported_by' => $actor->id,
            'handled_by' => $actor->id,
            'maintenance_type' => 'Pemeliharaan korektif',
            'complaint' => 'Kendaraan membutuhkan pemeriksaan.',
            'initial_condition' => 'Kendaraan dapat diperiksa.',
            'service_provider' => 'Bengkel Rekanan',
            'reported_date' => '2026-08-08',
            'start_date' => '2026-08-08',
            'completion_date' => '2026-08-08',
            'cost' => 250000,
            'result' => 'Pemeliharaan selesai.',
            'final_condition' => 'Kondisi telah diperiksa.',
            'status' => MaintenanceStatus::Completed,
            ...$attributes,
        ]);
    }
}
