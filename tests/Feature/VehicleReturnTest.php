<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\AttachmentCategory;
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
use Illuminate\Support\Facades\DB;
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
        $checkoutSignature = $loan->signatures()
            ->where(
                'purpose',
                DigitalSignaturePurpose::VehicleCheckoutConfirmation->value,
            )
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
        $this->assertSame($admin->id, $checkoutSignature->signer_id);
        $this->assertSame(
            $checkoutSignature->signed_at->toDateTimeString(),
            $check->checked_at->toDateTimeString(),
        );
        $this->assertSame(
            DigitalSignaturePurpose::VehicleCheckoutConfirmation,
            $checkoutSignature->purpose,
        );
        $this->assertSame(1, $checkoutSignature->version);
        $this->assertNotEmpty($checkoutSignature->transaction_hash);
        Storage::disk('local')->assertExists(
            $checkoutSignature->image_path,
        );
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

        $this->actingAs($admin)
            ->get(route('vehicle-loan-lifecycle.admin.index'))
            ->assertOk()
            ->assertSee('Menunggu peminjam mengunggah foto memegang kunci');
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
        $this->assertDatabaseCount('digital_signatures', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_checkout_rejects_same_photo_for_different_evidence_categories(): void
    {
        $admin = $this->admin();
        $employee = $this->employee();
        $vehicle = $this->vehicle([
            'status' => VehicleStatus::Reserved,
        ]);
        $loan = $this->approvedLoan($employee, $vehicle, $admin);
        $payload = $this->conditionPayload('checkout-duplicate', 1000.0);
        $payload['photo_back'] = $payload['photo_front'];

        $this->actingAs($admin)
            ->from(route('vehicle-loan-lifecycle.admin.index'))
            ->post(
                route('vehicle-loan-lifecycle.admin.checkout', $loan),
                $payload,
            )
            ->assertRedirect(route('vehicle-loan-lifecycle.admin.index'))
            ->assertSessionHasErrors('photo_back');

        $this->assertSame(VehicleLoanStatus::Approved, $loan->refresh()->status);
        $this->assertDatabaseCount('vehicle_condition_checks', 0);
        $this->assertDatabaseCount('attachments', 0);
        $this->assertDatabaseCount('digital_signatures', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_checkout_requires_officer_signature_and_consent(): void
    {
        $admin = $this->admin();
        $employee = $this->employee();
        $vehicle = $this->vehicle([
            'status' => VehicleStatus::Reserved,
        ]);
        $loan = $this->approvedLoan($employee, $vehicle, $admin);
        $payload = $this->conditionPayload('checkout-unsigned', 1000.0);
        unset($payload['signature_data'], $payload['condition_consent']);

        $this->actingAs($admin)
            ->get(route('vehicle-loan-lifecycle.admin.index'))
            ->assertOk()
            ->assertSee('data-signature-form', false)
            ->assertSee('capture="environment"', false)
            ->assertSee('data-evidence-preview-input', false)
            ->assertSee('vehicle_checkout_officer_'.$loan->id.'_canvas')
            ->assertSee('Tanda Tangani Checkout dan Siapkan Kendaraan');

        $this->actingAs($admin)
            ->from(route('vehicle-loan-lifecycle.admin.index'))
            ->post(
                route('vehicle-loan-lifecycle.admin.checkout', $loan),
                $payload,
            )
            ->assertRedirect(route('vehicle-loan-lifecycle.admin.index'))
            ->assertSessionHasErrors([
                'signature_data',
                'condition_consent',
            ]);

        $this->assertSame(
            VehicleLoanStatus::Approved,
            $loan->refresh()->status,
        );
        $this->assertDatabaseCount('vehicle_condition_checks', 0);
        $this->assertDatabaseCount('digital_signatures', 0);
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
        $pickupEvidence = $checkout->attachments()
            ->where(
                'file_category',
                AttachmentCategory::BorrowerWithKey->value,
            )
            ->firstOrFail();
        $this->assertSame($employee->id, $pickupEvidence->uploaded_by);
        $this->assertSame(
            ConditionCheckType::Checkout->value,
            $pickupEvidence->metadata['check_type'],
        );
        $this->assertSame(7, $checkout->attachments()->count());
        Storage::disk('local')->assertExists($pickupEvidence->file_path);
        $this->actingAs($employee)
            ->get(route('vehicle-loan-lifecycle.employee.index'))
            ->assertOk()
            ->assertSee('Foto Peminjam Memegang Kunci');
        $this->actingAs($employee)
            ->get(route('vehicle-loan-lifecycle.evidence', [
                'vehicleLoan' => $loan,
                'attachment' => $pickupEvidence,
            ]))
            ->assertOk();
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

    public function test_employee_cannot_confirm_pickup_without_borrower_with_key_photo(): void
    {
        $admin = $this->admin();
        $employee = $this->employee();
        $vehicle = $this->vehicle([
            'status' => VehicleStatus::Reserved,
        ]);
        $loan = $this->approvedLoan($employee, $vehicle, $admin);
        $this->checkout($admin, $loan);
        $payload = $this->signaturePayload();
        unset($payload['photo_borrower_with_key']);

        $this->actingAs($employee)
            ->get(route('vehicle-loan-lifecycle.employee.index'))
            ->assertOk()
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('name="photo_borrower_with_key"', false)
            ->assertSee('capture="environment"', false)
            ->assertSee('data-evidence-preview-input', false)
            ->assertSee('data-signature-form', false)
            ->assertSee(
                'href="'.route('vehicle-loan-lifecycle.pdf', $loan).'"',
                false,
            );

        $this->actingAs($employee)
            ->from(route('vehicle-loan-lifecycle.employee.index'))
            ->post(
                route('vehicle-loan-lifecycle.employee.confirm-pickup', $loan),
                $payload,
            )
            ->assertRedirect(route('vehicle-loan-lifecycle.employee.index'))
            ->assertSessionHasErrors('photo_borrower_with_key');

        $loan->refresh();
        $checkout = $loan->checkoutCheck();

        $this->assertSame(VehicleLoanStatus::ReadyForPickup, $loan->status);
        $this->assertNotNull($checkout);
        $this->assertNull($checkout->borrower_confirmed_at);
        $this->assertSame(6, $checkout->attachments()->count());
        $this->assertSame(
            0,
            $loan->signatures()
                ->where(
                    'purpose',
                    DigitalSignaturePurpose::VehicleLoanPickup->value,
                )
                ->count(),
        );
    }

    public function test_employee_cannot_reuse_checkout_photo_as_pickup_evidence(): void
    {
        $admin = $this->admin();
        $employee = $this->employee();
        $vehicle = $this->vehicle([
            'status' => VehicleStatus::Reserved,
        ]);
        $loan = $this->approvedLoan($employee, $vehicle, $admin);
        $this->checkout($admin, $loan);
        $checkout = $loan->checkoutCheck();
        $this->assertNotNull($checkout);
        $frontEvidence = $checkout->attachments()
            ->where(
                'file_category',
                AttachmentCategory::VehicleFront->value,
            )
            ->firstOrFail();
        $payload = $this->signaturePayload();
        $payload['photo_borrower_with_key'] = UploadedFile::fake()
            ->createWithContent(
                'foto-checkout-dipakai-ulang.jpg',
                Storage::disk('local')->get($frontEvidence->file_path),
            );
        $filesBefore = Storage::disk('local')->allFiles();
        sort($filesBefore);

        $this->actingAs($employee)
            ->from(route('vehicle-loan-lifecycle.employee.index'))
            ->post(
                route('vehicle-loan-lifecycle.employee.confirm-pickup', $loan),
                $payload,
            )
            ->assertRedirect(route('vehicle-loan-lifecycle.employee.index'))
            ->assertSessionHasErrors('photo_borrower_with_key');

        $filesAfter = Storage::disk('local')->allFiles();
        sort($filesAfter);

        $this->assertSame(
            VehicleLoanStatus::ReadyForPickup,
            $loan->refresh()->status,
        );
        $this->assertSame(6, $checkout->attachments()->count());
        $this->assertSame($filesBefore, $filesAfter);
    }

    public function test_employee_cannot_confirm_pickup_before_planned_start(): void
    {
        $admin = $this->admin();
        $employee = $this->employee();
        $vehicle = $this->vehicle([
            'status' => VehicleStatus::Reserved,
        ]);
        $loan = $this->approvedLoan($employee, $vehicle, $admin);
        $loan->forceFill([
            'planned_start_at' => now()->addDay(),
            'planned_end_at' => now()->addDay()->addHours(4),
        ])->save();
        $this->checkout($admin, $loan);
        $filesBefore = Storage::disk('local')->allFiles();
        sort($filesBefore);

        $this->actingAs($employee)
            ->get(route('vehicle-loan-lifecycle.employee.index'))
            ->assertOk()
            ->assertSee('Kendaraan baru dapat diambil mulai')
            ->assertSee('05 Agt 2026, 09:00 WIB')
            ->assertDontSee('Tanda Tangani dan Ambil Kendaraan');

        $this->actingAs($employee)
            ->from(route('vehicle-loan-lifecycle.employee.index'))
            ->post(
                route('vehicle-loan-lifecycle.employee.confirm-pickup', $loan),
                $this->signaturePayload(),
            )
            ->assertRedirect(route('vehicle-loan-lifecycle.employee.index'))
            ->assertSessionHasErrors('pickup_time');

        $loan->refresh();
        $checkout = $loan->checkoutCheck();
        $filesAfter = Storage::disk('local')->allFiles();
        sort($filesAfter);

        $this->assertSame(
            VehicleLoanStatus::ReadyForPickup,
            $loan->status,
        );
        $this->assertSame(
            VehicleStatus::Reserved,
            $vehicle->refresh()->status,
        );
        $this->assertNotNull($checkout);
        $this->assertNull($checkout->borrower_confirmed_at);
        $this->assertSame(6, $checkout->attachments()->count());
        $this->assertSame(
            0,
            $loan->signatures()
                ->where(
                    'purpose',
                    DigitalSignaturePurpose::VehicleLoanPickup->value,
                )
                ->count(),
        );
        $this->assertSame($filesBefore, $filesAfter);
    }

    public function test_workspace_flags_legacy_pickup_before_schedule_without_mutating_history(): void
    {
        $admin = $this->admin();
        $employee = $this->employee();
        $vehicle = $this->vehicle([
            'status' => VehicleStatus::Reserved,
        ]);
        $loan = $this->approvedLoan($employee, $vehicle, $admin);
        $this->checkout($admin, $loan);
        $actualStartAt = now();
        $plannedStartAt = now()->addDay();
        $loan->forceFill([
            'status' => VehicleLoanStatus::Borrowed,
            'actual_start_at' => $actualStartAt,
            'planned_start_at' => $plannedStartAt,
            'planned_end_at' => $plannedStartAt->copy()->addHours(4),
        ])->save();
        $vehicle->forceFill([
            'status' => VehicleStatus::Borrowed,
        ])->save();

        $this->actingAs($employee)
            ->get(route('vehicle-loan-lifecycle.employee.index'))
            ->assertOk()
            ->assertSee('Catatan data historis: pengambilan tercatat sebelum jadwal mulai.')
            ->assertSee(
                $actualStartAt
                    ->copy()
                    ->timezone('Asia/Jakarta')
                    ->translatedFormat('d M Y, H:i'),
            )
            ->assertSee(
                $plannedStartAt
                    ->copy()
                    ->timezone('Asia/Jakarta')
                    ->translatedFormat('d M Y, H:i'),
            );

        $loan->refresh();

        $this->assertSame(
            $actualStartAt->toDateTimeString(),
            $loan->actual_start_at->toDateTimeString(),
        );
        $this->assertSame(
            $plannedStartAt->toDateTimeString(),
            $loan->planned_start_at->toDateTimeString(),
        );
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
                    'signature_data' => $this->signatureDataUrl(),
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
            ->get(route('vehicle-loan-lifecycle.employee.index'))
            ->assertOk()
            ->assertSee('data-signature-form', false)
            ->assertSee('vehicle_return_borrower_'.$loan->id.'_canvas')
            ->assertSee('Tanda Tangani dan Ajukan Pengembalian');

        $this->actingAs($employee)
            ->post(
                route('vehicle-loan-lifecycle.employee.request-return', $loan),
                [
                    'return_confirmation' => '1',
                    'return_notes' => 'Kegiatan lapangan selesai lebih lambat dari rencana.',
                    'signature_data' => $this->signatureDataUrl(),
                ],
            )
            ->assertRedirect(route('vehicle-loan-lifecycle.employee.index'));

        $loan->refresh();
        $returnRequestSignature = $loan->signatures()
            ->where(
                'purpose',
                DigitalSignaturePurpose::VehicleLoanReturnRequest->value,
            )
            ->firstOrFail();
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
        $this->assertSame($employee->id, $returnRequestSignature->signer_id);
        $this->assertSame(
            DigitalSignaturePurpose::VehicleLoanReturnRequest,
            $returnRequestSignature->purpose,
        );
        $this->assertSame(
            $loan->actual_end_at->toDateTimeString(),
            $returnRequestSignature->signed_at->toDateTimeString(),
        );
        Storage::disk('local')->assertExists(
            $returnRequestSignature->image_path,
        );
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'vehicle_loan_return_requested',
            'auditable_id' => $loan->id,
        ]);
    }

    public function test_return_request_requires_borrower_signature_and_consent(): void
    {
        [$admin, $employee, $vehicle, $loan] = $this->borrowedLoan();

        $this->actingAs($employee)
            ->from(route('vehicle-loan-lifecycle.employee.index'))
            ->post(
                route('vehicle-loan-lifecycle.employee.request-return', $loan),
                ['return_notes' => 'Belum saya tandatangani.'],
            )
            ->assertRedirect(route('vehicle-loan-lifecycle.employee.index'))
            ->assertSessionHasErrors([
                'signature_data',
                'return_confirmation',
            ]);

        $loan->refresh();
        $this->assertSame(VehicleLoanStatus::Borrowed, $loan->status);
        $this->assertNull($loan->actual_end_at);
        $this->assertSame(VehicleStatus::Borrowed, $vehicle->refresh()->status);
        $this->assertDatabaseMissing('digital_signatures', [
            'signable_type' => $loan->getMorphClass(),
            'signable_id' => $loan->id,
            'purpose' => DigitalSignaturePurpose::VehicleLoanReturnRequest->value,
        ]);
    }

    public function test_duplicate_return_submission_is_idempotent(): void
    {
        [$admin, $employee, $vehicle, $loan] = $this->borrowedLoan();
        $payload = [
            'return_confirmation' => '1',
            'return_notes' => 'Kendaraan sudah berada di garasi kantor.',
            'signature_data' => $this->signatureDataUrl(),
        ];

        $this->actingAs($employee)
            ->post(
                route('vehicle-loan-lifecycle.employee.request-return', $loan),
                $payload,
            )
            ->assertRedirect(route('vehicle-loan-lifecycle.employee.index'));

        $this->actingAs($employee)
            ->post(
                route('vehicle-loan-lifecycle.employee.request-return', $loan),
                $payload,
            )
            ->assertRedirect(route('vehicle-loan-lifecycle.employee.index'))
            ->assertSessionHas(
                'status',
                'Pengembalian sudah tercatat dan tidak dikirim ulang. Kendaraan menunggu atau telah menyelesaikan pemeriksaan akhir Administrator.',
            );

        $this->assertSame(
            VehicleLoanStatus::AwaitingReturnInspection,
            $loan->refresh()->status,
        );
        $this->assertSame(VehicleStatus::Borrowed, $vehicle->refresh()->status);
        $this->assertSame(
            1,
            $loan->signatures()
                ->where(
                    'purpose',
                    DigitalSignaturePurpose::VehicleLoanReturnRequest->value,
                )
                ->count(),
        );
        $this->assertSame(
            1,
            $loan->statusHistories()
                ->where(
                    'new_status',
                    VehicleLoanStatus::AwaitingReturnInspection->value,
                )
                ->count(),
        );
        $this->assertSame(
            1,
            \App\Models\AuditLog::query()
                ->where('event', 'vehicle_loan_return_requested')
                ->where('auditable_type', $loan->getMorphClass())
                ->where('auditable_id', $loan->id)
                ->count(),
        );
    }

    public function test_good_return_completes_loan_and_updates_master_odometer(): void
    {
        [$admin, $employee, $vehicle, $loan] = $this->borrowedLoan();
        Carbon::setTestNow('2026-08-04 04:00:00 UTC');
        $this->requestReturn($employee, $loan);

        $this->actingAs($admin)
            ->get(route('vehicle-loan-lifecycle.admin.index'))
            ->assertOk()
            ->assertSee('vehicle_return_officer_'.$loan->id.'_canvas')
            ->assertSee('Tanda Tangani dan Selesaikan Pemeriksaan');

        $this->actingAs($admin)
            ->post(
                route('vehicle-loan-lifecycle.admin.return-inspection', $loan),
                $this->conditionPayload('return-good', 1025.0),
            )
            ->assertRedirect(route('vehicle-loan-lifecycle.admin.index'));

        $loan->refresh();
        $vehicle->refresh();
        $returnConfirmationSignature = $loan->signatures()
            ->where(
                'purpose',
                DigitalSignaturePurpose::VehicleReturnConfirmation->value,
            )
            ->firstOrFail();
        $returnCheck = $loan->conditionChecks()
            ->where('check_type', ConditionCheckType::Return->value)
            ->firstOrFail();

        $this->assertSame(VehicleLoanStatus::Completed, $loan->status);
        $this->assertSame(VehicleStatus::Available, $vehicle->status);
        $this->assertSame('1025.0', $vehicle->current_odometer);
        $this->assertSame($admin->id, $returnConfirmationSignature->signer_id);
        $this->assertSame(
            $returnConfirmationSignature->signed_at->toDateTimeString(),
            $returnCheck->checked_at->toDateTimeString(),
        );
        $this->assertSame(
            DigitalSignaturePurpose::VehicleReturnConfirmation,
            $returnConfirmationSignature->purpose,
        );
        Storage::disk('local')->assertExists(
            $returnConfirmationSignature->image_path,
        );
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
            'photo_damage' => $this->evidenceImage(
                'damage.jpg',
                'return-issue|damage',
            ),
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

    public function test_return_rejects_photo_reused_from_checkout(): void
    {
        [$admin, $employee, $vehicle, $loan] = $this->borrowedLoan();
        $this->requestReturn($employee, $loan);
        $checkout = $loan->checkoutCheck();
        $this->assertNotNull($checkout);
        $frontEvidence = $checkout->attachments()
            ->where(
                'file_category',
                AttachmentCategory::VehicleFront->value,
            )
            ->firstOrFail();
        $payload = $this->conditionPayload('return-reused', 1025.0);
        $payload['photo_front'] = UploadedFile::fake()
            ->createWithContent(
                'foto-checkout-untuk-return.jpg',
                Storage::disk('local')->get($frontEvidence->file_path),
            );
        $filesBefore = Storage::disk('local')->allFiles();
        sort($filesBefore);

        $this->actingAs($admin)
            ->from(route('vehicle-loan-lifecycle.admin.index'))
            ->post(
                route('vehicle-loan-lifecycle.admin.return-inspection', $loan),
                $payload,
            )
            ->assertRedirect(route('vehicle-loan-lifecycle.admin.index'))
            ->assertSessionHasErrors('photo_front');

        $filesAfter = Storage::disk('local')->allFiles();
        sort($filesAfter);

        $this->assertSame(
            VehicleLoanStatus::AwaitingReturnInspection,
            $loan->refresh()->status,
        );
        $this->assertDatabaseMissing('vehicle_condition_checks', [
            'vehicle_loan_id' => $loan->id,
            'check_type' => ConditionCheckType::Return->value,
        ]);
        $this->assertDatabaseMissing('digital_signatures', [
            'signable_type' => $loan->getMorphClass(),
            'signable_id' => $loan->id,
            'purpose' => DigitalSignaturePurpose::VehicleReturnConfirmation->value,
        ]);
        $this->assertSame($filesBefore, $filesAfter);
    }

    public function test_return_inspection_requires_officer_signature_and_consent(): void
    {
        [$admin, $employee, $vehicle, $loan] = $this->borrowedLoan();
        $this->requestReturn($employee, $loan);
        $payload = $this->conditionPayload('return-unsigned', 1020.0);
        unset($payload['signature_data'], $payload['condition_consent']);
        $filesBefore = Storage::disk('local')->allFiles();
        sort($filesBefore);

        $this->actingAs($admin)
            ->from(route('vehicle-loan-lifecycle.admin.index'))
            ->post(
                route('vehicle-loan-lifecycle.admin.return-inspection', $loan),
                $payload,
            )
            ->assertRedirect(route('vehicle-loan-lifecycle.admin.index'))
            ->assertSessionHasErrors([
                'signature_data',
                'condition_consent',
            ]);

        $this->assertSame(
            VehicleLoanStatus::AwaitingReturnInspection,
            $loan->refresh()->status,
        );
        $this->assertDatabaseMissing('vehicle_condition_checks', [
            'vehicle_loan_id' => $loan->id,
            'check_type' => ConditionCheckType::Return->value,
        ]);
        $this->assertDatabaseMissing('digital_signatures', [
            'signable_type' => $loan->getMorphClass(),
            'signable_id' => $loan->id,
            'purpose' => DigitalSignaturePurpose::VehicleReturnConfirmation->value,
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
            ->assertDontSee($otherLoan->loan_number)
            ->assertSee('aria-disabled="true"', false)
            ->assertSee('PDF setelah Checkout')
            ->assertDontSee(
                'href="'.route('vehicle-loan-lifecycle.pdf', $ownLoan).'"',
                false,
            );

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

    public function test_lifecycle_pdf_rejects_tampered_event_signature(): void
    {
        $admin = $this->admin();
        $employee = $this->employee();
        $vehicle = $this->vehicle([
            'status' => VehicleStatus::Reserved,
        ]);
        $loan = $this->approvedLoan($employee, $vehicle, $admin);
        $this->checkout($admin, $loan);
        $signature = $loan->checkoutConfirmationSignature();

        $this->assertNotNull($signature);
        Storage::disk('local')->put(
            $signature->image_path,
            'isi-tanda-tangan-telah-diubah',
        );

        $this->actingAs($employee)
            ->get(route('vehicle-loan-lifecycle.pdf', $loan))
            ->assertStatus(409);

        $this->assertDatabaseMissing('audit_logs', [
            'event' => 'vehicle_loan_lifecycle_pdf_downloaded',
            'auditable_id' => $loan->id,
        ]);
    }

    public function test_lifecycle_pdf_renders_four_event_specific_signatures_and_snapshots(): void
    {
        $admin = $this->admin([
            'name' => 'Petugas Checkout Historis',
            'employee_number' => 'PEG-CHECKER-OLD-001',
        ]);
        $employee = $this->employee();
        $vehicle = $this->vehicle([
            'status' => VehicleStatus::Reserved,
        ]);
        $loan = $this->approvedLoan($employee, $vehicle, $admin);
        $service = app(VehicleLoanLifecycleService::class);

        $this->checkout($admin, $loan);
        $loan->load([
            'borrower:id,employee_number,name,phone,work_unit,position',
            'vehicle:id,public_id,vehicle_code,license_plate,brand,model,status,current_odometer,registration_expiry_date,storage_location,responsible_person',
            'conditionChecks.checker:id,name,employee_number',
            'conditionChecks.attachments',
            'statusHistories.changer:id,name',
            'signatures.signer:id,name',
        ]);
        $checkout = $loan->checkoutCheck();
        $checkoutSignatureRecord = $loan->checkoutConfirmationSignature();

        $this->assertNotNull($checkout);
        $this->assertNotNull($checkoutSignatureRecord);
        $this->assertSame(
            'Petugas Checkout Historis',
            $checkout->checker_name_snapshot,
        );
        $this->assertSame(
            'PEG-CHECKER-OLD-001',
            $checkout->checker_employee_number_snapshot,
        );
        $this->assertSame(
            'Petugas Checkout Historis',
            $checkoutSignatureRecord->signer_name_snapshot,
        );

        $checkoutOfficerSignature = $service->signatureDataUri(
            $checkoutSignatureRecord,
        );
        $this->assertNotNull($checkoutOfficerSignature);

        DB::table('vehicle_condition_checks')
            ->where('id', $checkout->id)
            ->update([
                'checked_at' => $checkoutSignatureRecord->signed_at
                    ->addHours(7)
                    ->format('Y-m-d H:i:s'),
            ]);
        $loan->unsetRelation('conditionChecks')->load([
            'conditionChecks.checker:id,name,employee_number',
            'conditionChecks.attachments',
        ]);

        $this->actingAs($employee)
            ->get(route('vehicle-loan-lifecycle.employee.index'))
            ->assertOk()
            ->assertSee('04 Agt 2026, 09:00 WIB')
            ->assertDontSee('04 Agt 2026, 16:00 WIB');

        $htmlBeforePickup = $this->renderLifecyclePdfHtml(
            $loan,
            $checkoutOfficerSignature,
        );

        $this->assertStringContainsString(
            'class="header-table"',
            $htmlBeforePickup,
        );
        $this->assertStringContainsString(
            'alt="Tanda tangan petugas pemeriksaan kondisi awal"',
            $htmlBeforePickup,
        );
        $this->assertStringNotContainsString(
            'alt="Tanda tangan peminjam saat pengambilan"',
            $htmlBeforePickup,
        );
        $this->assertStringContainsString(
            $checkoutSignatureRecord->signed_at
                ->timezone('Asia/Jakarta')
                ->translatedFormat('d M Y, H:i').' WIB',
            $htmlBeforePickup,
        );
        $this->assertStringNotContainsString(
            $checkoutSignatureRecord->signed_at
                ->addHours(7)
                ->timezone('Asia/Jakarta')
                ->translatedFormat('d M Y, H:i').' WIB',
            $htmlBeforePickup,
        );

        $admin->forceFill([
            'name' => 'Petugas Return Setelah Berubah',
            'employee_number' => 'PEG-CHECKER-NEW-001',
        ])->save();

        $this->actingAs($employee)
            ->post(
                route('vehicle-loan-lifecycle.employee.confirm-pickup', $loan),
                $this->signaturePayload(),
            )
            ->assertRedirect(route('vehicle-loan-lifecycle.employee.index'));

        $loan->refresh()->load([
            'borrower:id,employee_number,name,phone,work_unit,position',
            'vehicle:id,public_id,vehicle_code,license_plate,brand,model,status,current_odometer,registration_expiry_date,storage_location,responsible_person',
            'conditionChecks.checker:id,name,employee_number',
            'conditionChecks.attachments',
            'statusHistories.changer:id,name',
            'signatures.signer:id,name',
        ]);
        $pickupSignature = $service->signatureDataUri(
            $loan->pickupSignature(),
        );
        $this->assertNotNull($pickupSignature);

        $htmlAfterPickup = $this->renderLifecyclePdfHtml(
            $loan,
            $checkoutOfficerSignature,
            $pickupSignature,
        );
        $this->assertStringContainsString(
            'alt="Tanda tangan peminjam saat pengambilan"',
            $htmlAfterPickup,
        );
        $this->assertStringContainsString(
            'Foto Peminjam Memegang Kunci',
            $htmlAfterPickup,
        );

        $this->requestReturn($employee, $loan);
        $loan->refresh()->load([
            'borrower:id,employee_number,name,phone,work_unit,position',
            'vehicle:id,public_id,vehicle_code,license_plate,brand,model,status,current_odometer,registration_expiry_date,storage_location,responsible_person',
            'conditionChecks.checker:id,name,employee_number',
            'conditionChecks.attachments',
            'statusHistories.changer:id,name',
            'signatures.signer:id,name',
        ]);
        $returnBorrowerSignature = $service->signatureDataUri(
            $loan->returnRequestSignature(),
        );
        $this->assertNotNull($returnBorrowerSignature);

        $htmlAwaitingInspection = $this->renderLifecyclePdfHtml(
            $loan,
            $checkoutOfficerSignature,
            $pickupSignature,
            $returnBorrowerSignature,
        );
        $this->assertStringContainsString(
            'Pertanggungjawaban Pengembalian',
            $htmlAwaitingInspection,
        );
        $this->assertStringContainsString(
            'alt="Tanda tangan peminjam saat pengembalian"',
            $htmlAwaitingInspection,
        );
        $this->assertStringNotContainsString(
            'alt="Tanda tangan petugas pemeriksaan kondisi akhir"',
            $htmlAwaitingInspection,
        );

        $this->actingAs($admin)
            ->post(
                route('vehicle-loan-lifecycle.admin.return-inspection', $loan),
                $this->conditionPayload('return-pdf', 1020.0),
            )
            ->assertRedirect(route('vehicle-loan-lifecycle.admin.index'));

        $loan->refresh()->load([
            'borrower:id,employee_number,name,phone,work_unit,position',
            'vehicle:id,public_id,vehicle_code,license_plate,brand,model,status,current_odometer,registration_expiry_date,storage_location,responsible_person',
            'conditionChecks.checker:id,name,employee_number',
            'conditionChecks.attachments',
            'statusHistories.changer:id,name',
            'signatures.signer:id,name',
        ]);
        $returnCheck = $loan->returnCheck();
        $returnOfficerRecord = $loan->returnConfirmationSignature();
        $returnOfficerSignature = $service->signatureDataUri(
            $returnOfficerRecord,
        );

        $this->assertNotNull($returnCheck);
        $this->assertNotNull($returnOfficerRecord);
        $this->assertNotNull($returnOfficerSignature);
        $this->assertSame(
            'Petugas Return Setelah Berubah',
            $returnCheck->checker_name_snapshot,
        );
        $this->assertSame(
            'Petugas Return Setelah Berubah',
            $returnOfficerRecord->signer_name_snapshot,
        );
        $this->assertSame(
            'Petugas Checkout Historis',
            $loan->checkoutCheck()->checker_name_snapshot,
        );

        $finalHtml = $this->renderLifecyclePdfHtml(
            $loan,
            $checkoutOfficerSignature,
            $pickupSignature,
            $returnBorrowerSignature,
            $returnOfficerSignature,
        );

        foreach ([
            'Tanda tangan petugas pemeriksaan kondisi awal',
            'Tanda tangan peminjam saat pengambilan',
            'Tanda tangan peminjam saat pengembalian',
            'Tanda tangan petugas pemeriksaan kondisi akhir',
        ] as $signatureAlt) {
            $this->assertStringContainsString(
                'alt="'.$signatureAlt.'"',
                $finalHtml,
            );
        }
        $this->assertSame(
            4,
            substr_count(
                $finalHtml,
                'Ditandatangani melalui sistem:',
            ),
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

    private function renderLifecyclePdfHtml(
        VehicleLoan $loan,
        ?string $checkoutOfficerSignature = null,
        ?string $pickupSignature = null,
        ?string $returnBorrowerSignature = null,
        ?string $returnOfficerSignature = null,
    ): string {
        return view('vehicle-loans.lifecycle.pdf', [
            'vehicleLoan' => $loan,
            'documentVerification' => (object) [
                'version' => 1,
                'payload_hash' => str_repeat('a', 64),
            ],
            'verificationQrDataUri' => 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciPjwvc3ZnPg==',
            'checkoutOfficerSignature' => $checkoutOfficerSignature,
            'pickupSignature' => $pickupSignature,
            'returnBorrowerSignature' => $returnBorrowerSignature,
            'returnOfficerSignature' => $returnOfficerSignature,
            'evidenceData' => [],
            'institutionName' => 'Badan Pusat Statistik Kabupaten Jombang',
            'institutionShortName' => 'BPS Kabupaten Jombang',
            'displayTimezone' => 'Asia/Jakarta',
        ])->render();
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
                    'signature_data' => $this->signatureDataUrl(),
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
            'photo_front' => $this->evidenceImage($prefix.'-front.jpg', $prefix.'|front'),
            'photo_back' => $this->evidenceImage($prefix.'-back.jpg', $prefix.'|back'),
            'photo_left' => $this->evidenceImage($prefix.'-left.jpg', $prefix.'|left'),
            'photo_right' => $this->evidenceImage($prefix.'-right.jpg', $prefix.'|right'),
            'photo_odometer' => $this->evidenceImage($prefix.'-odometer.jpg', $prefix.'|odometer'),
            'photo_fuel' => $this->evidenceImage($prefix.'-fuel.jpg', $prefix.'|fuel'),
            'signature_data' => $this->signatureDataUrl(),
            'condition_consent' => '1',
            ...$overrides,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function signaturePayload(): array
    {
        return [
            'photo_borrower_with_key' => UploadedFile::fake()
                ->image('peminjam-memegang-kunci.jpg', 701, 383)
                ->size(120),
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

    private function evidenceImage(
        string $name,
        string $marker,
    ): UploadedFile {
        $hash = crc32($marker);
        $width = 100 + ($hash & 0x1ff);
        $height = 100 + (($hash >> 9) & 0x1ff);

        return UploadedFile::fake()
            ->image($name, $width, $height)
            ->size(120);
    }
}
