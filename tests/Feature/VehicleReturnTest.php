<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\ConditionCheckType;
use App\Enums\DigitalSignaturePurpose;
use App\Enums\RoleName;
use App\Enums\VehicleLoanStatus;
use App\Enums\VehicleOverallCondition;
use App\Enums\VehicleStatus;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleLoan;
use App\Services\VehicleLoanLifecycleService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Tests\TestCase;

class VehicleReturnTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
        Carbon::setTestNow('2026-08-04 02:00:00 UTC');
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_admin_checkout_creates_immutable_baseline_and_required_evidence(): void
    {
        $admin = $this->admin();
        $employee = $this->employee();
        $vehicle = $this->vehicle([
            'status' => VehicleStatus::Reserved,
        ]);
        $loan = $this->approvedLoan($employee, $vehicle, $admin);

        $this->actingAs($admin)
            ->post(
                route('vehicle-loan-lifecycle.admin.checkout', $loan),
                $this->conditionPayload('checkout', 1000.0),
            )
            ->assertRedirect(route('vehicle-loan-lifecycle.admin.index'));

        $loan->refresh();
        $check = $loan->conditionChecks()
            ->where('check_type', ConditionCheckType::Checkout->value)
            ->firstOrFail();

        $this->assertSame(
            VehicleLoanStatus::ReadyForPickup,
            $loan->status,
        );
        $this->assertSame(
            VehicleStatus::Reserved,
            $vehicle->refresh()->status,
        );
        $this->assertSame('1000.0', $check->odometer);
        $this->assertSame(6, $check->attachments()->count());
        $this->assertDatabaseHas('vehicle_loan_status_histories', [
            'vehicle_loan_id' => $loan->id,
            'previous_status' => VehicleLoanStatus::Approved->value,
            'new_status' => VehicleLoanStatus::ReadyForPickup->value,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'vehicle_loan_checkout_recorded',
            'auditable_id' => $loan->id,
        ]);

        $attachments = $check->attachments()->get();
        foreach ($attachments as $attachment) {
            Storage::disk('local')->assertExists($attachment->file_path);
            $this->assertNotEmpty($attachment->checksum);
        }
    }

    public function test_checkout_rejects_lower_odometer_and_cleans_new_files(): void
    {
        $admin = $this->admin();
        $employee = $this->employee();
        $vehicle = $this->vehicle([
            'status' => VehicleStatus::Reserved,
            'current_odometer' => 1200.0,
        ]);
        $loan = $this->approvedLoan($employee, $vehicle, $admin);

        $this->actingAs($admin)
            ->from(route('vehicle-loan-lifecycle.admin.index'))
            ->post(
                route('vehicle-loan-lifecycle.admin.checkout', $loan),
                $this->conditionPayload('checkout-fail', 1199.9),
            )
            ->assertRedirect(route('vehicle-loan-lifecycle.admin.index'))
            ->assertSessionHasErrors('odometer');

        $this->assertSame(VehicleLoanStatus::Approved, $loan->refresh()->status);
        $this->assertDatabaseCount('vehicle_condition_checks', 0);
        $this->assertDatabaseCount('attachments', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_employee_confirms_pickup_with_signature_and_vehicle_becomes_borrowed(): void
    {
        $admin = $this->admin();
        $employee = $this->employee();
        $vehicle = $this->vehicle([
            'status' => VehicleStatus::Reserved,
        ]);
        $loan = $this->approvedLoan($employee, $vehicle, $admin);
        $this->checkout($admin, $loan);

        $this->actingAs($employee)
            ->post(
                route('vehicle-loan-lifecycle.employee.confirm-pickup', $loan),
                $this->signaturePayload(),
            )
            ->assertRedirect(route('vehicle-loan-lifecycle.employee.index'));

        $loan->refresh();
        $checkout = $loan->conditionChecks()
            ->where('check_type', ConditionCheckType::Checkout->value)
            ->firstOrFail();
        $signature = $loan->signatures()
            ->where('purpose', DigitalSignaturePurpose::VehicleLoanPickup->value)
            ->firstOrFail();

        $this->assertSame(VehicleLoanStatus::Borrowed, $loan->status);
        $this->assertNotNull($loan->actual_start_at);
        $this->assertSame(VehicleStatus::Borrowed, $vehicle->refresh()->status);
        $this->assertNotNull($checkout->borrower_confirmed_at);
        $this->assertSame($employee->id, $signature->signer_id);
        $this->assertSame(
            DigitalSignaturePurpose::VehicleLoanPickup,
            $signature->purpose,
        );
        $this->assertSame(1, $signature->version);
        Storage::disk('local')->assertExists($signature->image_path);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'vehicle_loan_pickup_confirmed',
            'auditable_id' => $loan->id,
        ]);
    }

    public function test_other_employee_cannot_confirm_pickup_or_request_return(): void
    {
        $admin = $this->admin();
        $owner = $this->employee();
        $other = $this->employee();
        $vehicle = $this->vehicle([
            'status' => VehicleStatus::Reserved,
        ]);
        $loan = $this->approvedLoan($owner, $vehicle, $admin);
        $this->checkout($admin, $loan);

        $this->actingAs($other)
            ->post(
                route('vehicle-loan-lifecycle.employee.confirm-pickup', $loan),
                $this->signaturePayload(),
            )
            ->assertForbidden();

        $this->actingAs($owner)
            ->post(
                route('vehicle-loan-lifecycle.employee.confirm-pickup', $loan),
                $this->signaturePayload(),
            )
            ->assertRedirect(route('vehicle-loan-lifecycle.employee.index'));

        $this->actingAs($other)
            ->post(
                route('vehicle-loan-lifecycle.employee.request-return', $loan),
                [
                    'return_confirmation' => '1',
                    'return_notes' => 'Bukan kendaraan saya.',
                ],
            )
            ->assertForbidden();

        $this->assertSame(VehicleLoanStatus::Borrowed, $loan->refresh()->status);
    }

    public function test_employee_return_request_marks_late_without_new_status_enum(): void
    {
        [$admin, $employee, $vehicle, $loan] = $this->borrowedLoan();
        Carbon::setTestNow('2026-08-04 06:00:00 UTC');

        $this->actingAs($employee)
            ->post(
                route('vehicle-loan-lifecycle.employee.request-return', $loan),
                [
                    'return_confirmation' => '1',
                    'return_notes' => 'Kegiatan lapangan selesai lebih lambat dari rencana.',
                ],
            )
            ->assertRedirect(route('vehicle-loan-lifecycle.employee.index'));

        $loan->refresh();
        $this->assertSame(
            VehicleLoanStatus::AwaitingReturnInspection,
            $loan->status,
        );
        $this->assertNotNull($loan->actual_end_at);
        $this->assertNotNull($loan->overdue_at);
        $this->assertSame(
            $loan->planned_end_at->toDateTimeString(),
            $loan->overdue_at->toDateTimeString(),
        );
        $this->assertSame(VehicleStatus::Borrowed, $vehicle->refresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'vehicle_loan_return_requested',
            'auditable_id' => $loan->id,
        ]);
    }

    public function test_good_return_completes_loan_and_updates_master_odometer(): void
    {
        [$admin, $employee, $vehicle, $loan] = $this->borrowedLoan();
        Carbon::setTestNow('2026-08-04 04:00:00 UTC');
        $this->requestReturn($employee, $loan);

        $this->actingAs($admin)
            ->post(
                route('vehicle-loan-lifecycle.admin.return-inspection', $loan),
                $this->conditionPayload('return-good', 1025.0),
            )
            ->assertRedirect(route('vehicle-loan-lifecycle.admin.index'));

        $loan->refresh();
        $vehicle->refresh();

        $this->assertSame(VehicleLoanStatus::Completed, $loan->status);
        $this->assertSame(VehicleStatus::Available, $vehicle->status);
        $this->assertSame('1025.0', $vehicle->current_odometer);
        $this->assertDatabaseHas('vehicle_condition_checks', [
            'vehicle_loan_id' => $loan->id,
            'check_type' => ConditionCheckType::Return->value,
            'overall_condition' => VehicleOverallCondition::Good->value,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'vehicle_loan_return_completed',
            'auditable_id' => $loan->id,
        ]);
    }

    public function test_good_return_keeps_vehicle_reserved_for_future_approved_schedule(): void
    {
        [$admin, $employee, $vehicle, $loan] = $this->borrowedLoan();
        $futureEmployee = $this->employee();
        $this->vehicleLoan($futureEmployee, $vehicle, [
            'status' => VehicleLoanStatus::Approved,
            'planned_start_at' => '2026-08-05 01:00:00',
            'planned_end_at' => '2026-08-05 05:00:00',
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);

        Carbon::setTestNow('2026-08-04 04:00:00 UTC');
        $this->requestReturn($employee, $loan);

        $this->actingAs($admin)
            ->post(
                route('vehicle-loan-lifecycle.admin.return-inspection', $loan),
                $this->conditionPayload('return-reserved', 1015.0),
            )
            ->assertRedirect(route('vehicle-loan-lifecycle.admin.index'));

        $this->assertSame(VehicleLoanStatus::Completed, $loan->refresh()->status);
        $this->assertSame(VehicleStatus::Reserved, $vehicle->refresh()->status);
    }

    public function test_problem_return_sets_return_issue_and_vehicle_inspection(): void
    {
        [$admin, $employee, $vehicle, $loan] = $this->borrowedLoan();
        Carbon::setTestNow('2026-08-04 04:00:00 UTC');
        $this->requestReturn($employee, $loan);

        $payload = $this->conditionPayload('return-issue', 1030.0, [
            'overall_condition' => VehicleOverallCondition::NeedsAttention->value,
            'damage_notes' => 'Terdapat goresan baru pada sisi kanan bodi.',
            'photo_damage' => UploadedFile::fake()
                ->image('damage.jpg', 120, 120)
                ->size(120),
        ]);

        $this->actingAs($admin)
            ->post(
                route('vehicle-loan-lifecycle.admin.return-inspection', $loan),
                $payload,
            )
            ->assertRedirect(route('vehicle-loan-lifecycle.admin.index'));

        $this->assertSame(
            VehicleLoanStatus::ReturnIssue,
            $loan->refresh()->status,
        );
        $this->assertSame(
            VehicleStatus::Inspection,
            $vehicle->refresh()->status,
        );
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'vehicle_loan_return_issue',
            'auditable_id' => $loan->id,
        ]);
    }

    public function test_return_rejects_regressive_odometer_and_does_not_leave_orphan_files(): void
    {
        [$admin, $employee, $vehicle, $loan] = $this->borrowedLoan();
        Carbon::setTestNow('2026-08-04 04:00:00 UTC');
        $this->requestReturn($employee, $loan);
        $filesBefore = Storage::disk('local')->allFiles();
        sort($filesBefore);

        $this->actingAs($admin)
            ->from(route('vehicle-loan-lifecycle.admin.index'))
            ->post(
                route('vehicle-loan-lifecycle.admin.return-inspection', $loan),
                $this->conditionPayload('return-fail', 999.0),
            )
            ->assertRedirect(route('vehicle-loan-lifecycle.admin.index'))
            ->assertSessionHasErrors('odometer');

        $this->assertSame(
            VehicleLoanStatus::AwaitingReturnInspection,
            $loan->refresh()->status,
        );
        $this->assertDatabaseMissing('vehicle_condition_checks', [
            'vehicle_loan_id' => $loan->id,
            'check_type' => ConditionCheckType::Return->value,
        ]);
        $filesAfter = Storage::disk('local')->allFiles();
        sort($filesAfter);
        $this->assertSame($filesBefore, $filesAfter);
    }

    public function test_condition_check_cannot_be_mutated_or_deleted(): void
    {
        $admin = $this->admin();
        $employee = $this->employee();
        $vehicle = $this->vehicle([
            'status' => VehicleStatus::Reserved,
        ]);
        $loan = $this->approvedLoan($employee, $vehicle, $admin);
        $this->checkout($admin, $loan);
        $check = $loan->conditionChecks()->firstOrFail();

        try {
            $check->forceFill(['body_condition' => 'Diubah ilegal'])->save();
            $this->fail('Perubahan condition check seharusnya ditolak.');
        } catch (LogicException $exception) {
            $this->assertSame(
                'Pemeriksaan kondisi kendaraan tidak boleh diubah.',
                $exception->getMessage(),
            );
        }

        $this->expectException(LogicException::class);
        $check->delete();
    }

    public function test_employee_workspace_only_lists_own_lifecycle_loans(): void
    {
        $admin = $this->admin();
        $owner = $this->employee(['name' => 'Pegawai Pemilik']);
        $other = $this->employee(['name' => 'Pegawai Lain']);
        $vehicleA = $this->vehicle(['status' => VehicleStatus::Reserved]);
        $vehicleB = $this->vehicle(['status' => VehicleStatus::Reserved]);
        $ownLoan = $this->approvedLoan($owner, $vehicleA, $admin);
        $otherLoan = $this->approvedLoan($other, $vehicleB, $admin);

        $this->actingAs($owner)
            ->get(route('vehicle-loan-lifecycle.employee.index'))
            ->assertOk()
            ->assertSee($ownLoan->loan_number)
            ->assertDontSee($otherLoan->loan_number);

        $this->actingAs($owner)
            ->get(route('vehicle-loan-lifecycle.admin.index'))
            ->assertForbidden();
    }

    public function test_authorized_user_can_download_lifecycle_pdf(): void
    {
        $admin = $this->admin();
        $employee = $this->employee();
        $vehicle = $this->vehicle([
            'status' => VehicleStatus::Reserved,
        ]);
        $loan = $this->approvedLoan($employee, $vehicle, $admin);
        $this->checkout($admin, $loan);

        $this->actingAs($employee)
            ->get(route('vehicle-loan-lifecycle.pdf', $loan))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_lifecycle_pdf_requires_checkout_and_denies_other_employee(): void
    {
        $admin = $this->admin();
        $owner = $this->employee();
        $otherEmployee = $this->employee();

        $vehicle = $this->vehicle([
            'status' => VehicleStatus::Reserved,
        ]);

        $loan = $this->approvedLoan(
            $owner,
            $vehicle,
            $admin,
        );

        $this->actingAs($owner)
            ->get(route('vehicle-loan-lifecycle.pdf', $loan))
            ->assertStatus(409);

        $this->actingAs($otherEmployee)
            ->get(route('vehicle-loan-lifecycle.pdf', $loan))
            ->assertForbidden();

        $this->assertDatabaseMissing('audit_logs', [
            'event' => 'vehicle_loan_lifecycle_pdf_downloaded',
            'auditable_id' => $loan->id,
        ]);
    }

    public function test_lifecycle_pdf_uses_checker_snapshot_and_wet_signature_fallback(): void
    {
        $admin = $this->admin([
            'name' => 'Petugas Checkout Historis',
            'employee_number' => 'PEG-CHECKER-OLD-001',
        ]);

        $employee = $this->employee();

        $vehicle = $this->vehicle([
            'status' => VehicleStatus::Reserved,
        ]);

        $loan = $this->approvedLoan(
            $employee,
            $vehicle,
            $admin,
        );

        $this->checkout($admin, $loan);

        $checkout = $loan->checkoutCheck();

        $this->assertNotNull($checkout);

        $this->assertSame(
            'Petugas Checkout Historis',
            $checkout->checker_name_snapshot,
        );

        $this->assertSame(
            'PEG-CHECKER-OLD-001',
            $checkout->checker_employee_number_snapshot,
        );

        $admin->forceFill([
            'name' => 'Petugas Master Setelah Berubah',
            'employee_number' => 'PEG-CHECKER-NEW-001',
        ])->save();

        $checkout->refresh();

        $this->assertSame(
            'Petugas Checkout Historis',
            $checkout->checker_name_snapshot,
        );

        $this->assertSame(
            'PEG-CHECKER-OLD-001',
            $checkout->checker_employee_number_snapshot,
        );

        $loan = $loan->fresh();

        $loan->load([
            'borrower:id,employee_number,name,phone,work_unit,position',
            'vehicle:id,public_id,vehicle_code,license_plate,brand,model,status,current_odometer,registration_expiry_date,storage_location,responsible_person',
            'conditionChecks.checker:id,name,employee_number',
            'conditionChecks.attachments',
            'statusHistories.changer:id,name',
            'signatures.signer:id,name',
        ]);

        $htmlBeforePickup = view(
            'vehicle-loans.lifecycle.pdf',
            [
                'vehicleLoan' => $loan,
                'pickupSignature' => null,
                'evidenceData' => [],
                'institutionName' => 'Badan Pusat Statistik Kabupaten Jombang',
                'institutionShortName' => 'BPS Kabupaten Jombang',
                'displayTimezone' => 'Asia/Jakarta',
            ],
        )->render();

        $this->assertStringContainsString(
            'Pertanggungjawaban Serah Terima',
            $htmlBeforePickup,
        );

        $this->assertStringContainsString(
            'Petugas Checkout Historis',
            $htmlBeforePickup,
        );

        $this->assertStringContainsString(
            'PEG-CHECKER-OLD-001',
            $htmlBeforePickup,
        );

        $this->assertSame(
            1,
            preg_match(
                '/Pertanggungjawaban Serah Terima(.*?)Riwayat Status/s',
                $htmlBeforePickup,
                $accountabilityMatches,
            ),
        );

        $accountabilityHtml = $accountabilityMatches[1];

        $this->assertStringContainsString(
            'class="signature-space"',
            $accountabilityHtml,
        );

        $this->assertStringNotContainsString(
            'Belum tersedia',
            $accountabilityHtml,
        );

        $this->assertStringNotContainsString(
            'alt="Tanda tangan peminjam"',
            $accountabilityHtml,
        );

        $this->actingAs($employee)
            ->post(
                route(
                    'vehicle-loan-lifecycle.employee.confirm-pickup',
                    $loan,
                ),
                $this->signaturePayload(),
            )
            ->assertRedirect(
                route('vehicle-loan-lifecycle.employee.index'),
            );

        $loan->refresh();

        $loan->load([
            'borrower:id,employee_number,name,phone,work_unit,position',
            'vehicle:id,public_id,vehicle_code,license_plate,brand,model,status,current_odometer,registration_expiry_date,storage_location,responsible_person',
            'conditionChecks.checker:id,name,employee_number',
            'conditionChecks.attachments',
            'statusHistories.changer:id,name',
            'signatures.signer:id,name',
        ]);

        $pickupSignature = app(
            VehicleLoanLifecycleService::class,
        )->signatureDataUri(
            $loan->pickupSignature(),
        );

        $this->assertNotNull($pickupSignature);

        $htmlAfterPickup = view(
            'vehicle-loans.lifecycle.pdf',
            [
                'vehicleLoan' => $loan,
                'pickupSignature' => $pickupSignature,
                'evidenceData' => [],
                'institutionName' => 'Badan Pusat Statistik Kabupaten Jombang',
                'institutionShortName' => 'BPS Kabupaten Jombang',
                'displayTimezone' => 'Asia/Jakarta',
            ],
        )->render();

        $this->assertStringContainsString(
            'alt="Tanda tangan peminjam"',
            $htmlAfterPickup,
        );
    }

    public function test_lifecycle_and_evidence_downloads_are_audited_and_owned(): void
    {
        $admin = $this->admin();
        $employee = $this->employee();
        $otherEmployee = $this->employee();

        $vehicle = $this->vehicle([
            'status' => VehicleStatus::Reserved,
        ]);

        $loan = $this->approvedLoan(
            $employee,
            $vehicle,
            $admin,
        );

        $this->checkout($admin, $loan);

        $this->actingAs($employee)
            ->get(route('vehicle-loan-lifecycle.pdf', $loan))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'vehicle_loan_lifecycle_pdf_downloaded',
            'module' => 'vehicle_loan',
            'auditable_id' => $loan->id,
            'actor_id' => $employee->id,
        ]);

        $checkout = $loan->checkoutCheck();

        $this->assertNotNull($checkout);

        $attachment = $checkout
            ->attachments()
            ->firstOrFail();

        $this->actingAs($employee)
            ->get(
                route(
                    'vehicle-loan-lifecycle.evidence',
                    [
                        'vehicleLoan' => $loan,
                        'attachment' => $attachment,
                    ],
                ),
            )
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'vehicle_condition_evidence_downloaded',
            'module' => 'vehicle_loan',
            'auditable_id' => $loan->id,
            'actor_id' => $employee->id,
        ]);

        $this->actingAs($otherEmployee)
            ->get(route('vehicle-loan-lifecycle.pdf', $loan))
            ->assertForbidden();

        $this->actingAs($otherEmployee)
            ->get(
                route(
                    'vehicle-loan-lifecycle.evidence',
                    [
                        'vehicleLoan' => $loan,
                        'attachment' => $attachment,
                    ],
                ),
            )
            ->assertForbidden();

        $this->assertDatabaseMissing('audit_logs', [
            'event' => 'vehicle_loan_lifecycle_pdf_downloaded',
            'auditable_id' => $loan->id,
            'actor_id' => $otherEmployee->id,
        ]);

        $this->assertDatabaseMissing('audit_logs', [
            'event' => 'vehicle_condition_evidence_downloaded',
            'auditable_id' => $loan->id,
            'actor_id' => $otherEmployee->id,
        ]);
    }

    /**
     * @return array{User, User, Vehicle, VehicleLoan}
     */
    private function borrowedLoan(): array
    {
        $admin = $this->admin();
        $employee = $this->employee();
        $vehicle = $this->vehicle([
            'status' => VehicleStatus::Reserved,
        ]);
        $loan = $this->approvedLoan($employee, $vehicle, $admin);
        $this->checkout($admin, $loan);

        $this->actingAs($employee)
            ->post(
                route('vehicle-loan-lifecycle.employee.confirm-pickup', $loan),
                $this->signaturePayload(),
            )
            ->assertRedirect(route('vehicle-loan-lifecycle.employee.index'));

        return [$admin, $employee, $vehicle, $loan->refresh()];
    }

    private function checkout(User $admin, VehicleLoan $loan): void
    {
        $this->actingAs($admin)
            ->post(
                route('vehicle-loan-lifecycle.admin.checkout', $loan),
                $this->conditionPayload('checkout-'.$loan->id, 1000.0),
            )
            ->assertRedirect(route('vehicle-loan-lifecycle.admin.index'));

        $loan->refresh();
    }

    private function requestReturn(User $employee, VehicleLoan $loan): void
    {
        $this->actingAs($employee)
            ->post(
                route('vehicle-loan-lifecycle.employee.request-return', $loan),
                [
                    'return_confirmation' => '1',
                    'return_notes' => 'Kendaraan dikembalikan ke garasi kantor.',
                ],
            )
            ->assertRedirect(route('vehicle-loan-lifecycle.employee.index'));

        $loan->refresh();
    }

    private function approvedLoan(
        User $employee,
        Vehicle $vehicle,
        User $admin,
    ): VehicleLoan {
        return $this->vehicleLoan($employee, $vehicle, [
            'status' => VehicleLoanStatus::Approved,
            'approved_by' => $admin->id,
            'approved_at' => now(),
            'submitted_at' => now()->subHour(),
            'reviewed_by' => $admin->id,
            'reviewed_at' => now()->subMinutes(30),
        ]);
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
            'planned_start_at' => '2026-08-04 01:00:00',
            'planned_end_at' => '2026-08-04 05:00:00',
            'status' => VehicleLoanStatus::Draft,
            'notes' => null,
            ...$attributes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function conditionPayload(
        string $prefix,
        float $odometer,
        array $overrides = [],
    ): array {
        return [
            'odometer' => $odometer,
            'fuel_level' => 80,
            'overall_condition' => VehicleOverallCondition::Good->value,
            'body_condition' => 'Bodi bersih, tidak terdapat kerusakan baru.',
            'engine_condition' => 'Mesin hidup normal dan tidak ada indikator peringatan.',
            'tire_condition' => 'Ban dalam kondisi baik dan tekanan memadai.',
            'equipment_condition' => 'STNK, kunci, helm, dan perlengkapan tersedia.',
            'damage_notes' => null,
            'photo_front' => UploadedFile::fake()->image($prefix.'-front.jpg', 120, 120)->size(120),
            'photo_back' => UploadedFile::fake()->image($prefix.'-back.jpg', 120, 120)->size(120),
            'photo_left' => UploadedFile::fake()->image($prefix.'-left.jpg', 120, 120)->size(120),
            'photo_right' => UploadedFile::fake()->image($prefix.'-right.jpg', 120, 120)->size(120),
            'photo_odometer' => UploadedFile::fake()->image($prefix.'-odometer.jpg', 120, 120)->size(120),
            'photo_fuel' => UploadedFile::fake()->image($prefix.'-fuel.jpg', 120, 120)->size(120),
            ...$overrides,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function signaturePayload(): array
    {
        return [
            'signature_data' => $this->signatureDataUrl(),
            'pickup_consent' => '1',
        ];
    }

    private function signatureDataUrl(): string
    {
        return 'data:image/png;base64,'
            .'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwC'
            .'AAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';
    }
}
