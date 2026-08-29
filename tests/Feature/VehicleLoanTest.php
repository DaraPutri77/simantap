<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\DigitalSignaturePurpose;
use App\Enums\RoleName;
use App\Enums\VehicleLoanStatus;
use App\Enums\VehicleStatus;
use App\Models\DigitalSignature;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleLoan;
use App\Services\VehicleLoanService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class VehicleLoanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
        Carbon::setTestNow('2026-08-03 02:00:00 UTC');
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_employee_can_create_draft_with_snapshot_uuid_history_and_audit(): void
    {
        $employee = $this->employee();
        $vehicle = $this->vehicle();

        $response = $this->actingAs($employee)
            ->post(
                route('my.vehicle-loans.store'),
                $this->validPayload($vehicle),
            );

        $vehicleLoan = VehicleLoan::query()->firstOrFail();

        $response->assertRedirect(
            route('my.vehicle-loans.show', $vehicleLoan),
        );
        $this->assertTrue(Str::isUuid($vehicleLoan->public_id));
        $this->assertStringStartsWith('LOAN/2026/08/', $vehicleLoan->loan_number);
        $this->assertSame($employee->name, $vehicleLoan->borrower_name_snapshot);
        $this->assertSame(
            $employee->employee_number,
            $vehicleLoan->employee_number_snapshot,
        );
        $this->assertSame($employee->work_unit, $vehicleLoan->work_unit_snapshot);
        $this->assertSame($employee->phone, $vehicleLoan->phone_snapshot);
        $this->assertSame($vehicle->vehicle_code, $vehicleLoan->vehicle_code_snapshot);
        $this->assertSame($vehicle->license_plate, $vehicleLoan->license_plate_snapshot);
        $this->assertSame($vehicle->displayName(), $vehicleLoan->vehicle_name_snapshot);
        $this->assertSame(VehicleLoanStatus::Draft, $vehicleLoan->status);
        $this->assertSame(VehicleStatus::Available, $vehicle->refresh()->status);
        $this->assertDatabaseHas('vehicle_loan_status_histories', [
            'vehicle_loan_id' => $vehicleLoan->id,
            'previous_status' => null,
            'new_status' => VehicleLoanStatus::Draft->value,
            'changed_by' => $employee->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'vehicle_loan_created',
            'module' => 'vehicle_loan',
            'auditable_type' => 'vehicle_loan',
            'auditable_id' => $vehicleLoan->id,
            'actor_id' => $employee->id,
        ]);
    }

    public function test_employee_can_submit_draft_with_immutable_digital_signature(): void
    {
        $employee = $this->employee();
        $vehicleLoan = $this->vehicleLoan(
            $employee,
            $this->vehicle(),
        );

        $this->actingAs($employee)
            ->post(
                route('my.vehicle-loans.submit', $vehicleLoan),
                $this->signaturePayload(),
            )
            ->assertRedirect(
                route('my.vehicle-loans.show', $vehicleLoan),
            );

        $vehicleLoan->refresh();
        $signature = $vehicleLoan->signatures()->firstOrFail();

        $this->assertSame(VehicleLoanStatus::Submitted, $vehicleLoan->status);
        $this->assertNotNull($vehicleLoan->submitted_at);
        $this->assertSame(
            DigitalSignaturePurpose::VehicleLoanSubmission,
            $signature->purpose,
        );
        $this->assertSame(1, $signature->version);
        $this->assertSame($employee->name, $signature->signer_name_snapshot);
        Storage::disk('local')->assertExists($signature->image_path);
        $this->assertDatabaseHas('vehicle_loan_status_histories', [
            'vehicle_loan_id' => $vehicleLoan->id,
            'previous_status' => VehicleLoanStatus::Draft->value,
            'new_status' => VehicleLoanStatus::Submitted->value,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'vehicle_loan_submitted',
            'auditable_id' => $vehicleLoan->id,
        ]);
    }

    public function test_submission_cleans_signature_file_when_transaction_rolls_back_after_signature_create(): void
    {
        $employee = $this->employee([
            'employee_number' => 'PEG-VEH-SIGN-ROLLBACK-001',
            'email' => 'signature.rollback.vehicle@example.test',
        ]);
        $vehicle = $this->vehicle([
            'vehicle_code' => 'VEH-SIGN-ROLLBACK-001',
            'license_plate' => 'S 9001 RB',
        ]);
        $vehicleLoan = $this->vehicleLoan($employee, $vehicle);

        $signatureObservedBeforeFailure = false;
        $shouldFail = true;

        User::retrieved(function (User $retrieved) use (
            $employee,
            $vehicleLoan,
            &$signatureObservedBeforeFailure,
            &$shouldFail,
        ): void {
            if (! $shouldFail || $retrieved->id !== $employee->id) {
                return;
            }

            $signatureObservedBeforeFailure = DigitalSignature::query()
                ->where('signable_type', $vehicleLoan->getMorphClass())
                ->where('signable_id', $vehicleLoan->id)
                ->where(
                    'purpose',
                    DigitalSignaturePurpose::VehicleLoanSubmission->value,
                )
                ->exists();

            if (! $signatureObservedBeforeFailure) {
                return;
            }

            $shouldFail = false;

            throw new \RuntimeException(
                'SIMANTAP vehicle signature rollback probe.',
            );
        });

        try {
            app(VehicleLoanService::class)->submit(
                $vehicleLoan,
                $this->signaturePayload(),
                $employee,
            );

            $this->fail(
                'Transaction rollback probe seharusnya melempar exception.',
            );
        } catch (\RuntimeException $exception) {
            $this->assertSame(
                'SIMANTAP vehicle signature rollback probe.',
                $exception->getMessage(),
            );
        }

        $this->assertTrue($signatureObservedBeforeFailure);
        $this->assertSame(
            VehicleLoanStatus::Draft,
            $vehicleLoan->refresh()->status,
        );
        $this->assertDatabaseCount('digital_signatures', 0);
        $this->assertSame(
            [],
            Storage::disk('local')->allFiles(
                'signatures/vehicle-loans/'.$vehicleLoan->public_id,
            ),
        );
    }

    public function test_schedule_validation_rejects_past_reverse_and_excessive_duration(): void
    {
        $employee = $this->employee();
        $vehicle = $this->vehicle();

        $this->actingAs($employee)
            ->from(route('my.vehicle-loans.create'))
            ->post(route('my.vehicle-loans.store'), $this->validPayload(
                $vehicle,
                [
                    'planned_start_at' => '2026-08-03T08:00',
                    'planned_end_at' => '2026-08-03T07:00',
                ],
            ))
            ->assertRedirect(route('my.vehicle-loans.create'))
            ->assertSessionHasErrors([
                'planned_start_at',
                'planned_end_at',
            ]);

        $this->actingAs($employee)
            ->from(route('my.vehicle-loans.create'))
            ->post(route('my.vehicle-loans.store'), $this->validPayload(
                $vehicle,
                [
                    'planned_start_at' => '2026-08-04T08:00',
                    'planned_end_at' => '2026-08-08T08:01',
                ],
            ))
            ->assertRedirect(route('my.vehicle-loans.create'))
            ->assertSessionHasErrors('planned_end_at');

        $this->assertDatabaseCount('vehicle_loans', 0);
    }

    public function test_overlapping_active_schedule_is_rejected_before_submission(): void
    {
        $firstEmployee = $this->employee();
        $secondEmployee = $this->employee();
        $vehicle = $this->vehicle();
        $this->vehicleLoan(
            $firstEmployee,
            $vehicle,
            [
                'status' => VehicleLoanStatus::Submitted,
                'planned_start_at' => '2026-08-04 01:00:00',
                'planned_end_at' => '2026-08-04 05:00:00',
                'submitted_at' => now(),
            ],
        );
        $secondLoan = $this->vehicleLoan(
            $secondEmployee,
            $vehicle,
            [
                'planned_start_at' => '2026-08-04 03:00:00',
                'planned_end_at' => '2026-08-04 06:00:00',
            ],
        );

        $this->actingAs($secondEmployee)
            ->from(route('my.vehicle-loans.show', $secondLoan))
            ->post(
                route('my.vehicle-loans.submit', $secondLoan),
                $this->signaturePayload(),
            )
            ->assertRedirect(route('my.vehicle-loans.show', $secondLoan))
            ->assertSessionHasErrors('planned_start_at');

        $this->assertSame(
            VehicleLoanStatus::Draft,
            $secondLoan->refresh()->status,
        );
        $this->assertDatabaseCount('digital_signatures', 0);
    }

    public function test_employee_only_sees_own_loans_and_routes_use_public_uuid(): void
    {
        $owner = $this->employee();
        $otherEmployee = $this->employee();
        $ownerLoan = $this->vehicleLoan($owner, $this->vehicle());
        $otherLoan = $this->vehicleLoan($otherEmployee, $this->vehicle());

        $this->actingAs($owner)
            ->get(route('my.vehicle-loans.index'))
            ->assertOk()
            ->assertSee($ownerLoan->loan_number)
            ->assertDontSee($otherLoan->loan_number);

        $this->actingAs($owner)
            ->get(route('my.vehicle-loans.show', $otherLoan))
            ->assertForbidden();

        $this->actingAs($owner)
            ->get('/peminjaman-saya/'.$ownerLoan->id)
            ->assertNotFound();
    }

    public function test_employee_index_uses_simple_status_groups_and_clear_filter_copy(): void
    {
        $employee = $this->employee();
        $waitingLoan = $this->vehicleLoan(
            $employee,
            $this->vehicle(),
            [
                'status' => VehicleLoanStatus::UnderReview,
                'submitted_at' => now(),
            ],
        );
        $completedLoan = $this->vehicleLoan(
            $employee,
            $this->vehicle(),
            [
                'status' => VehicleLoanStatus::Completed,
                'submitted_at' => now(),
            ],
        );

        $this->actingAs($employee)
            ->get(route('my.vehicle-loans.index', [
                'status' => 'waiting',
            ]))
            ->assertOk()
            ->assertSee($waitingLoan->loan_number)
            ->assertDontSee($completedLoan->loan_number)
            ->assertSee('Terapkan Filter')
            ->assertSee('Buat Pengajuan Baru')
            ->assertSee('Menunggu Persetujuan');
    }

    public function test_draft_detail_clearly_explains_that_draft_is_not_submitted(): void
    {
        $employee = $this->employee();
        $vehicleLoan = $this->vehicleLoan(
            $employee,
            $this->vehicle(),
        );

        $this->actingAs($employee)
            ->get(route('my.vehicle-loans.show', $vehicleLoan))
            ->assertOk()
            ->assertSee('Draft belum dikirim ke Administrator')
            ->assertSee('Langkah 2 dari 2')
            ->assertSee('id="kirim-pengajuan"', false)
            ->assertSee('data-signature-form', false)
            ->assertSee('Tanda Tangani & Kirim ke Administrator', false);
    }

    public function test_admin_detail_uses_admin_approval_route_and_employee_page_has_session_guard(): void
    {
        $admin = $this->admin();
        $employee = $this->employee();
        $vehicle = $this->vehicle();

        $draftLoan = $this->vehicleLoan($employee, $vehicle);

        $this->actingAs($employee)
            ->get(route('my.vehicle-loans.show', $draftLoan))
            ->assertOk()
            ->assertSee('data-role-session-guard', false)
            ->assertSee(
                'action="'.route('my.vehicle-loans.submit', $draftLoan).'"',
                false,
            );

        $submittedLoan = $this->vehicleLoan(
            $employee,
            $this->vehicle(),
            [
                'status' => VehicleLoanStatus::Submitted,
                'submitted_at' => now(),
            ],
        );

        $this->actingAs($admin)
            ->get(route('vehicle-loans.show', $submittedLoan))
            ->assertOk()
            ->assertSee('Verifikasi Pengajuan')
            ->assertDontSee('Mulai Pemeriksaan')
            ->assertDontSee('Mulai Periksa');

        $reviewedLoan = $this->vehicleLoan(
            $employee,
            $this->vehicle(),
            [
                'status' => VehicleLoanStatus::UnderReview,
                'submitted_at' => now(),
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ],
        );

        $this->actingAs($admin)
            ->get(route('vehicle-loans.show', $reviewedLoan))
            ->assertOk()
            ->assertSee(
                'action="'.route('vehicle-loans.approve', $reviewedLoan).'"',
                false,
            )
            ->assertDontSee(
                'action="'.route('my.vehicle-loans.submit', $reviewedLoan).'"',
                false,
            )
            ->assertDontSee('Draft belum dikirim ke Administrator')
            ->assertDontSee('Ubah Draft')
            ->assertDontSee('data-role-session-guard', false);
    }

    public function test_admin_approval_requires_signature_and_consent(): void
    {
        $admin = $this->admin();
        $employee = $this->employee();
        $vehicle = $this->vehicle();
        $vehicleLoan = $this->vehicleLoan($employee, $vehicle, [
            'status' => VehicleLoanStatus::UnderReview,
            'submitted_at' => now(),
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);

        $this->actingAs($admin)
            ->from(route('vehicle-loans.show', $vehicleLoan))
            ->post(route('vehicle-loans.approve', $vehicleLoan), [
                'admin_notes' => 'Tanpa tanda tangan.',
            ])
            ->assertRedirect(route('vehicle-loans.show', $vehicleLoan))
            ->assertSessionHasErrors([
                'signature_data',
                'approval_consent',
            ]);

        $this->assertSame(
            VehicleLoanStatus::UnderReview,
            $vehicleLoan->refresh()->status,
        );
        $this->assertDatabaseMissing('digital_signatures', [
            'signable_type' => $vehicleLoan->getMorphClass(),
            'signable_id' => $vehicleLoan->id,
            'purpose' => DigitalSignaturePurpose::VehicleLoanApproval->value,
        ]);
    }

    public function test_admin_approval_rejects_malformed_png_signature(): void
    {
        $admin = $this->admin();
        $employee = $this->employee();
        $vehicle = $this->vehicle();
        $vehicleLoan = $this->vehicleLoan($employee, $vehicle, [
            'status' => VehicleLoanStatus::UnderReview,
            'submitted_at' => now(),
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);

        $this->actingAs($admin)
            ->from(route('vehicle-loans.show', $vehicleLoan))
            ->post(route('vehicle-loans.approve', $vehicleLoan), [
                'signature_data' => 'data:image/png;base64,'.base64_encode(
                    "\x89PNG\r\n\x1a\nNOT-A-REAL-PNG",
                ),
                'approval_consent' => '1',
            ])
            ->assertRedirect(route('vehicle-loans.show', $vehicleLoan))
            ->assertSessionHasErrors('signature_data');

        $this->assertSame(
            VehicleLoanStatus::UnderReview,
            $vehicleLoan->refresh()->status,
        );
        $this->assertDatabaseMissing('digital_signatures', [
            'signable_type' => $vehicleLoan->getMorphClass(),
            'signable_id' => $vehicleLoan->id,
            'purpose' => DigitalSignaturePurpose::VehicleLoanApproval->value,
        ]);
    }

    public function test_admin_review_and_approval_reserve_vehicle_atomically(): void
    {
        $admin = $this->admin();
        $employee = $this->employee();
        $vehicle = $this->vehicle();
        $vehicleLoan = $this->vehicleLoan($employee, $vehicle, [
            'status' => VehicleLoanStatus::Submitted,
            'submitted_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('vehicle-loans.review', $vehicleLoan))
            ->assertRedirect(route('vehicle-loans.show', $vehicleLoan));

        $this->assertSame(
            VehicleLoanStatus::UnderReview,
            $vehicleLoan->refresh()->status,
        );

        $this->actingAs($admin)
            ->post(route('vehicle-loans.approve', $vehicleLoan), [
                ...$this->approvalSignaturePayload(),
                'admin_notes' => 'Kendaraan dapat digunakan sesuai jadwal.',
            ])
            ->assertRedirect(route('vehicle-loans.show', $vehicleLoan));

        $vehicleLoan->refresh();

        $approvalSignature = $vehicleLoan->signatures()
            ->where('purpose', DigitalSignaturePurpose::VehicleLoanApproval->value)
            ->firstOrFail();

        $this->assertSame(
            $admin->id,
            $approvalSignature->signer_id,
        );
        $this->assertSame(
            $admin->name,
            $approvalSignature->signer_name_snapshot,
        );
        $this->assertSame(
            $admin->employee_number,
            $approvalSignature->employee_number_snapshot,
        );
        $this->assertNotNull($approvalSignature->signed_at);
        $this->assertSame(1, $approvalSignature->version);
        $this->assertSame(
            DigitalSignaturePurpose::VehicleLoanApproval,
            $approvalSignature->purpose,
        );
        $this->assertTrue(
            $vehicleLoan->approved_at->equalTo(
                $approvalSignature->signed_at,
            ),
        );

        Storage::disk('local')->assertExists(
            $approvalSignature->image_path,
        );

        $storedSignature = Storage::disk('local')->get(
            $approvalSignature->image_path,
        );
        $expectedChecksum = hash(
            (string) config(
                'simantap.signature.hash_algorithm',
                'sha256',
            ),
            $storedSignature,
        );

        $this->assertSame(
            $expectedChecksum,
            $approvalSignature->image_checksum,
        );
        $this->assertSame(
            hash(
                'sha256',
                implode('|', [
                    $vehicleLoan->loan_number,
                    DigitalSignaturePurpose::VehicleLoanApproval->value,
                    $approvalSignature->version,
                    $admin->id,
                    $expectedChecksum,
                    $approvalSignature->signed_at->toIso8601String(),
                ]),
            ),
            $approvalSignature->transaction_hash,
        );

        $service = app(VehicleLoanService::class);

        $this->assertNotNull(
            $service->signatureDataUri($approvalSignature),
        );

        Storage::disk('local')->put(
            $approvalSignature->image_path,
            'tampered-signature',
        );

        $this->assertNull(
            $service->signatureDataUri($approvalSignature),
        );

        $this->assertSame(VehicleLoanStatus::Approved, $vehicleLoan->status);
        $this->assertSame($admin->id, $vehicleLoan->approved_by);
        $this->assertNotNull($vehicleLoan->approved_at);
        $this->assertSame(VehicleStatus::Reserved, $vehicle->refresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'vehicle_loan_approved',
            'auditable_id' => $vehicleLoan->id,
        ]);
    }

    public function test_approval_rechecks_schedule_and_cannot_double_book_vehicle(): void
    {
        $admin = $this->admin();
        $firstEmployee = $this->employee();
        $secondEmployee = $this->employee();
        $vehicle = $this->vehicle();
        $this->vehicleLoan($firstEmployee, $vehicle, [
            'status' => VehicleLoanStatus::Submitted,
            'planned_start_at' => '2026-08-04 01:00:00',
            'planned_end_at' => '2026-08-04 05:00:00',
            'submitted_at' => now(),
        ]);
        $reviewedLoan = $this->vehicleLoan($secondEmployee, $vehicle, [
            'status' => VehicleLoanStatus::UnderReview,
            'planned_start_at' => '2026-08-04 03:00:00',
            'planned_end_at' => '2026-08-04 06:00:00',
            'submitted_at' => now(),
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);

        $this->actingAs($admin)
            ->from(route('vehicle-loans.show', $reviewedLoan))
            ->post(route('vehicle-loans.approve', $reviewedLoan), $this->approvalSignaturePayload())
            ->assertRedirect(route('vehicle-loans.show', $reviewedLoan))
            ->assertSessionHasErrors('planned_start_at');

        $this->assertSame(
            VehicleLoanStatus::UnderReview,
            $reviewedLoan->refresh()->status,
        );
        $this->assertSame(VehicleStatus::Available, $vehicle->refresh()->status);
        $this->assertSame(
            [],
            Storage::disk('local')->allFiles(
                'signatures/vehicle-loans/'.$reviewedLoan->public_id,
            ),
        );
        $this->assertDatabaseMissing('digital_signatures', [
            'signable_type' => $reviewedLoan->getMorphClass(),
            'signable_id' => $reviewedLoan->id,
            'purpose' => DigitalSignaturePurpose::VehicleLoanApproval->value,
        ]);
    }

    public function test_admin_can_reject_reviewed_loan_without_reserving_vehicle(): void
    {
        $admin = $this->admin();
        $employee = $this->employee();
        $vehicle = $this->vehicle();
        $vehicleLoan = $this->vehicleLoan($employee, $vehicle, [
            'status' => VehicleLoanStatus::UnderReview,
            'submitted_at' => now(),
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('vehicle-loans.reject', $vehicleLoan), [
                'rejection_reason' => 'Kendaraan dialokasikan untuk kegiatan prioritas lain.',
            ])
            ->assertRedirect(route('vehicle-loans.show', $vehicleLoan));

        $vehicleLoan->refresh();
        $this->assertSame(VehicleLoanStatus::Rejected, $vehicleLoan->status);
        $this->assertNotNull($vehicleLoan->rejected_at);
        $this->assertSame(VehicleStatus::Available, $vehicle->refresh()->status);
    }

    public function test_cancelling_approved_loan_releases_vehicle_reservation(): void
    {
        $employee = $this->employee();
        $vehicle = $this->vehicle([
            'status' => VehicleStatus::Reserved,
        ]);
        $vehicleLoan = $this->vehicleLoan($employee, $vehicle, [
            'status' => VehicleLoanStatus::Approved,
            'submitted_at' => now(),
            'approved_at' => now(),
        ]);

        $this->actingAs($employee)
            ->patch(route('my.vehicle-loans.cancel', $vehicleLoan), [
                'cancellation_reason' => 'Kegiatan dinas dibatalkan oleh unit penyelenggara.',
            ])
            ->assertRedirect(route('my.vehicle-loans.show', $vehicleLoan));

        $this->assertSame(
            VehicleLoanStatus::Cancelled,
            $vehicleLoan->refresh()->status,
        );
        $this->assertSame(VehicleStatus::Available, $vehicle->refresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'vehicle_loan_cancelled',
            'auditable_id' => $vehicleLoan->id,
        ]);
    }

    public function test_expired_registration_and_non_operational_status_block_submission(): void
    {
        $employee = $this->employee();
        $expiredVehicle = $this->vehicle([
            'registration_expiry_date' => '2026-08-03',
        ]);
        $expiredLoan = $this->vehicleLoan($employee, $expiredVehicle);

        $this->actingAs($employee)
            ->from(route('my.vehicle-loans.show', $expiredLoan))
            ->post(
                route('my.vehicle-loans.submit', $expiredLoan),
                $this->signaturePayload(),
            )
            ->assertRedirect(route('my.vehicle-loans.show', $expiredLoan))
            ->assertSessionHasErrors('vehicle_id');

        $maintenanceVehicle = $this->vehicle([
            'status' => VehicleStatus::Maintenance,
        ]);
        $maintenanceLoan = $this->vehicleLoan(
            $employee,
            $maintenanceVehicle,
        );

        $this->actingAs($employee)
            ->from(route('my.vehicle-loans.show', $maintenanceLoan))
            ->post(
                route('my.vehicle-loans.submit', $maintenanceLoan),
                $this->signaturePayload(),
            )
            ->assertRedirect(route('my.vehicle-loans.show', $maintenanceLoan))
            ->assertSessionHasErrors('vehicle_id');
    }

    public function test_detail_pdf_and_approval_queue_render_wib_and_filters(): void
    {
        $admin = $this->admin();
        $employee = $this->employee([
            'name' => 'Pegawai Antrean Kendaraan',
        ]);
        $vehicleLoan = $this->vehicleLoan(
            $employee,
            $this->vehicle([
                'license_plate' => 'S 9090 WIB',
            ]),
            [
                'status' => VehicleLoanStatus::Submitted,
                'planned_start_at' => '2026-08-04 02:00:00',
                'planned_end_at' => '2026-08-04 04:00:00',
                'submitted_at' => now(),
            ],
        );

        $this->actingAs($admin)
            ->get(route('vehicle-loans.show', $vehicleLoan))
            ->assertOk()
            ->assertSee('09:00')
            ->assertSee('WIB');

        $this->actingAs($admin)
            ->get(route('vehicle-loans.approval-queue', [
                'q' => 'S 9090 WIB',
                'stage' => VehicleLoanStatus::Submitted->value,
            ]))
            ->assertOk()
            ->assertSee($vehicleLoan->loan_number)
            ->assertSee('Pegawai Antrean Kendaraan');

        $this->actingAs($admin)
            ->get(route('vehicle-loans.pdf', $vehicleLoan))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_loan_pdf_download_is_audited_and_wrong_employee_is_denied(): void
    {
        $admin = $this->admin();
        $owner = $this->employee();
        $otherEmployee = $this->employee();

        $loan = $this->vehicleLoan(
            $owner,
            $this->vehicle(),
        );

        $this->actingAs($owner)
            ->get(route('my.vehicle-loans.pdf', $loan))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'vehicle_loan_pdf_downloaded',
            'module' => 'vehicle_loan',
            'auditable_id' => $loan->id,
            'actor_id' => $owner->id,
        ]);

        $this->actingAs($otherEmployee)
            ->get(route('my.vehicle-loans.pdf', $loan))
            ->assertForbidden();

        $this->assertDatabaseMissing('audit_logs', [
            'event' => 'vehicle_loan_pdf_downloaded',
            'auditable_id' => $loan->id,
            'actor_id' => $otherEmployee->id,
        ]);

        $this->actingAs($admin)
            ->get(route('vehicle-loans.pdf', $loan))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'vehicle_loan_pdf_downloaded',
            'module' => 'vehicle_loan',
            'auditable_id' => $loan->id,
            'actor_id' => $admin->id,
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
    private function validPayload(
        Vehicle $vehicle,
        array $overrides = [],
    ): array {
        return [
            'vehicle_id' => $vehicle->id,
            'planned_start_at' => '2026-08-04T08:00',
            'planned_end_at' => '2026-08-04T12:00',
            'purpose' => 'Kunjungan lapangan untuk kegiatan statistik sektoral.',
            'destination' => 'Kantor Kecamatan Sukolilo',
            'reason' => 'Membawa perlengkapan survei.',
            'notes' => 'Berangkat dari kantor BPS.',
            ...$overrides,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function approvalSignaturePayload(): array
    {
        return [
            'signature_data' => $this->signatureDataUrl(),
            'approval_consent' => '1',
        ];
    }

    private function signaturePayload(): array
    {
        return [
            'signature_data' => $this->signatureDataUrl(),
            'signature_consent' => '1',
        ];
    }

    private function signatureDataUrl(): string
    {
        return 'data:image/png;base64,'
            .'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwC'
            .'AAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';
    }
}
