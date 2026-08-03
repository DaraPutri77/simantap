<?php

namespace App\Services;

use App\Enums\DocumentType;
use App\Enums\VehicleLoanStatus;
use App\Enums\VehicleStatus;
use App\Models\DigitalSignature;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleLoan;
use App\Models\VehicleLoanStatusHistory;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class VehicleLoanService
{
    public const SUBMISSION_SIGNATURE = 'vehicle_loan_submission';

    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly DocumentNumberService $documentNumberService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function createDraft(
        array $data,
        User $actor,
        ?Request $httpRequest = null,
    ): VehicleLoan {
        return DB::transaction(function () use (
            $data,
            $actor,
            $httpRequest,
        ): VehicleLoan {
            $this->requireBorrowerPhone($actor);
            [$start, $end] = $this->schedule($data);
            $vehicle = $this->lockVehicle((int) $data['vehicle_id']);
            $this->assertVehicleCanBeScheduled($vehicle, $end);
            $this->assertNoScheduleConflict(
                $vehicle,
                $start,
                $end,
            );

            $vehicleLoan = VehicleLoan::query()->create([
                'loan_number' => $this->documentNumberService->next(
                    DocumentType::VehicleLoan,
                    $start,
                ),
                'borrower_id' => $actor->getKey(),
                'borrower_name_snapshot' => $actor->name,
                'employee_number_snapshot' => $actor->employee_number,
                'work_unit_snapshot' => $actor->work_unit,
                'vehicle_id' => $vehicle->getKey(),
                'vehicle_code_snapshot' => $vehicle->vehicle_code,
                'license_plate_snapshot' => $vehicle->license_plate,
                'vehicle_name_snapshot' => $vehicle->displayName(),
                'purpose' => $data['purpose'],
                'destination' => $data['destination'],
                'reason' => $data['reason'] ?? null,
                'phone_snapshot' => $actor->phone,
                'planned_start_at' => $start,
                'planned_end_at' => $end,
                'status' => VehicleLoanStatus::Draft,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->recordStatus(
                $vehicleLoan,
                null,
                VehicleLoanStatus::Draft,
                'Draft peminjaman dibuat.',
                $actor,
            );
            $this->auditLogger->log(
                event: 'vehicle_loan_created',
                module: 'vehicle_loan',
                auditable: $vehicleLoan,
                newValues: [
                    'loan_number' => $vehicleLoan->loan_number,
                    'vehicle_id' => $vehicle->getKey(),
                    'planned_start_at' => $start->toIso8601String(),
                    'planned_end_at' => $end->toIso8601String(),
                    'status' => VehicleLoanStatus::Draft->value,
                ],
                request: $httpRequest,
                actorId: $actor->getKey(),
            );

            return $this->loadLoan($vehicleLoan);
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateDraft(
        VehicleLoan $vehicleLoan,
        array $data,
        User $actor,
        ?Request $httpRequest = null,
    ): VehicleLoan {
        return DB::transaction(function () use (
            $vehicleLoan,
            $data,
            $actor,
            $httpRequest,
        ): VehicleLoan {
            $this->requireBorrowerPhone($actor);
            $locked = $this->lockLoan($vehicleLoan);
            $this->requireStatus(
                $locked,
                [VehicleLoanStatus::Draft],
                'Hanya draft peminjaman yang dapat diubah.',
            );
            [$start, $end] = $this->schedule($data);
            $vehicle = $this->lockVehicle((int) $data['vehicle_id']);
            $this->assertVehicleCanBeScheduled($vehicle, $end);
            $this->assertNoScheduleConflict(
                $vehicle,
                $start,
                $end,
                $locked,
            );
            $oldValues = [
                ...$locked->only([
                    'vehicle_id',
                    'purpose',
                    'destination',
                    'reason',
                    'notes',
                ]),
                'planned_start_at' => $locked
                    ->planned_start_at
                    ?->toIso8601String(),
                'planned_end_at' => $locked
                    ->planned_end_at
                    ?->toIso8601String(),
            ];

            $locked->forceFill([
                'vehicle_id' => $vehicle->getKey(),
                'vehicle_code_snapshot' => $vehicle->vehicle_code,
                'license_plate_snapshot' => $vehicle->license_plate,
                'vehicle_name_snapshot' => $vehicle->displayName(),
                'purpose' => $data['purpose'],
                'destination' => $data['destination'],
                'reason' => $data['reason'] ?? null,
                'phone_snapshot' => $actor->phone,
                'planned_start_at' => $start,
                'planned_end_at' => $end,
                'notes' => $data['notes'] ?? null,
            ])->save();

            $this->auditLogger->log(
                event: 'vehicle_loan_updated',
                module: 'vehicle_loan',
                auditable: $locked,
                oldValues: $oldValues,
                newValues: [
                    ...$locked->only([
                        'vehicle_id',
                        'purpose',
                        'destination',
                        'reason',
                        'notes',
                    ]),
                    'planned_start_at' => $start->toIso8601String(),
                    'planned_end_at' => $end->toIso8601String(),
                ],
                request: $httpRequest,
                actorId: $actor->getKey(),
            );

            return $this->loadLoan($locked);
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function submit(
        VehicleLoan $vehicleLoan,
        array $data,
        User $actor,
        ?Request $httpRequest = null,
    ): VehicleLoan {
        return DB::transaction(function () use (
            $vehicleLoan,
            $data,
            $actor,
            $httpRequest,
        ): VehicleLoan {
            $locked = $this->lockLoan($vehicleLoan);
            $this->requireStatus(
                $locked,
                [VehicleLoanStatus::Draft],
                'Peminjaman pada status ini tidak dapat diajukan.',
            );
            $vehicle = $this->lockVehicle($locked->vehicle_id);
            $this->assertVehicleCanBeScheduled(
                $vehicle,
                CarbonImmutable::instance($locked->planned_end_at),
            );
            $this->assertNoScheduleConflict(
                $vehicle,
                CarbonImmutable::instance($locked->planned_start_at),
                CarbonImmutable::instance($locked->planned_end_at),
                $locked,
            );
            $previousStatus = $locked->status;

            $this->replaceSignature(
                $locked,
                $actor,
                $data['signature_data'],
                $httpRequest,
            );
            $locked->forceFill([
                'status' => VehicleLoanStatus::Submitted,
                'submitted_at' => now(),
                'reviewed_by' => null,
                'reviewed_at' => null,
                'approved_by' => null,
                'approved_at' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
                'cancelled_at' => null,
                'cancellation_reason' => null,
                'admin_notes' => null,
            ])->save();
            $this->recordStatus(
                $locked,
                $previousStatus,
                VehicleLoanStatus::Submitted,
                'Peminjaman diajukan oleh pegawai.',
                $actor,
            );
            $this->auditTransition(
                $locked,
                $previousStatus,
                VehicleLoanStatus::Submitted,
                'vehicle_loan_submitted',
                $actor,
                $httpRequest,
            );

            return $this->loadLoan($locked);
        }, 3);
    }

    public function startReview(
        VehicleLoan $vehicleLoan,
        User $actor,
        ?Request $httpRequest = null,
    ): VehicleLoan {
        return DB::transaction(function () use (
            $vehicleLoan,
            $actor,
            $httpRequest,
        ): VehicleLoan {
            $locked = $this->lockLoan($vehicleLoan);
            $this->requireStatus(
                $locked,
                [VehicleLoanStatus::Submitted],
                'Hanya peminjaman yang sudah diajukan yang dapat diperiksa.',
            );
            $previousStatus = $locked->status;

            $locked->forceFill([
                'status' => VehicleLoanStatus::UnderReview,
                'reviewed_by' => $actor->getKey(),
                'reviewed_at' => now(),
            ])->save();
            $this->recordStatus(
                $locked,
                $previousStatus,
                VehicleLoanStatus::UnderReview,
                'Pemeriksaan dimulai oleh Administrator.',
                $actor,
            );
            $this->auditTransition(
                $locked,
                $previousStatus,
                VehicleLoanStatus::UnderReview,
                'vehicle_loan_review_started',
                $actor,
                $httpRequest,
            );

            return $this->loadLoan($locked);
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function approve(
        VehicleLoan $vehicleLoan,
        array $data,
        User $actor,
        ?Request $httpRequest = null,
    ): VehicleLoan {
        return DB::transaction(function () use (
            $vehicleLoan,
            $data,
            $actor,
            $httpRequest,
        ): VehicleLoan {
            $locked = $this->lockLoan($vehicleLoan);
            $this->requireStatus(
                $locked,
                [VehicleLoanStatus::UnderReview],
                'Peminjaman harus diperiksa sebelum disetujui.',
            );
            $vehicle = $this->lockVehicle($locked->vehicle_id);
            $this->assertVehicleCanBeScheduled(
                $vehicle,
                CarbonImmutable::instance($locked->planned_end_at),
            );
            $this->assertNoScheduleConflict(
                $vehicle,
                CarbonImmutable::instance($locked->planned_start_at),
                CarbonImmutable::instance($locked->planned_end_at),
                $locked,
            );
            $previousStatus = $locked->status;

            $locked->forceFill([
                'status' => VehicleLoanStatus::Approved,
                'approved_by' => $actor->getKey(),
                'approved_at' => now(),
                'rejected_at' => null,
                'rejection_reason' => null,
                'admin_notes' => $data['admin_notes'] ?? null,
            ])->save();

            if ($vehicle->status === VehicleStatus::Available) {
                $vehicle->forceFill([
                    'status' => VehicleStatus::Reserved,
                ])->save();
            }

            $this->recordStatus(
                $locked,
                $previousStatus,
                VehicleLoanStatus::Approved,
                $data['admin_notes'] ?? 'Peminjaman disetujui.',
                $actor,
            );
            $this->auditTransition(
                $locked,
                $previousStatus,
                VehicleLoanStatus::Approved,
                'vehicle_loan_approved',
                $actor,
                $httpRequest,
                ['vehicle_status' => $vehicle->status->value],
            );

            return $this->loadLoan($locked);
        }, 3);
    }

    public function reject(
        VehicleLoan $vehicleLoan,
        string $reason,
        User $actor,
        ?Request $httpRequest = null,
    ): VehicleLoan {
        return DB::transaction(function () use (
            $vehicleLoan,
            $reason,
            $actor,
            $httpRequest,
        ): VehicleLoan {
            $locked = $this->lockLoan($vehicleLoan);
            $this->requireStatus(
                $locked,
                [VehicleLoanStatus::UnderReview],
                'Peminjaman harus diperiksa sebelum ditolak.',
            );
            $previousStatus = $locked->status;

            $locked->forceFill([
                'status' => VehicleLoanStatus::Rejected,
                'rejected_at' => now(),
                'rejection_reason' => $reason,
                'approved_by' => null,
                'approved_at' => null,
            ])->save();
            $this->recordStatus(
                $locked,
                $previousStatus,
                VehicleLoanStatus::Rejected,
                $reason,
                $actor,
            );
            $this->auditTransition(
                $locked,
                $previousStatus,
                VehicleLoanStatus::Rejected,
                'vehicle_loan_rejected',
                $actor,
                $httpRequest,
            );

            return $this->loadLoan($locked);
        }, 3);
    }

    public function cancel(
        VehicleLoan $vehicleLoan,
        string $reason,
        User $actor,
        ?Request $httpRequest = null,
    ): VehicleLoan {
        return DB::transaction(function () use (
            $vehicleLoan,
            $reason,
            $actor,
            $httpRequest,
        ): VehicleLoan {
            $locked = $this->lockLoan($vehicleLoan);

            if (
                $locked->status->isFinal()
                || in_array($locked->status, [
                    VehicleLoanStatus::Borrowed,
                    VehicleLoanStatus::AwaitingReturnInspection,
                    VehicleLoanStatus::ReturnIssue,
                ], true)
            ) {
                throw ValidationException::withMessages([
                    'loan' => 'Peminjaman pada status ini tidak dapat dibatalkan.',
                ]);
            }

            $previousStatus = $locked->status;
            $wasApproved = $previousStatus === VehicleLoanStatus::Approved;
            $locked->forceFill([
                'status' => VehicleLoanStatus::Cancelled,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ])->save();

            if ($wasApproved) {
                $vehicle = $this->lockVehicle($locked->vehicle_id);
                $this->refreshReservationStatus($vehicle, $locked);
            }

            $this->recordStatus(
                $locked,
                $previousStatus,
                VehicleLoanStatus::Cancelled,
                $reason,
                $actor,
            );
            $this->auditTransition(
                $locked,
                $previousStatus,
                VehicleLoanStatus::Cancelled,
                'vehicle_loan_cancelled',
                $actor,
                $httpRequest,
            );

            return $this->loadLoan($locked);
        }, 3);
    }

    public function signatureDataUri(
        ?DigitalSignature $signature,
    ): ?string {
        if ($signature === null) {
            return null;
        }

        $disk = Storage::disk($this->signatureDisk());

        if (! $disk->exists($signature->image_path)) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode(
            $disk->get($signature->image_path),
        );
    }

    private function replaceSignature(
        VehicleLoan $vehicleLoan,
        User $actor,
        string $dataUrl,
        ?Request $httpRequest,
    ): DigitalSignature {
        $binary = $this->signatureBinary($dataUrl);
        $checksum = hash(
            (string) config(
                'simantap.signature.hash_algorithm',
                'sha256',
            ),
            $binary,
        );
        $path = sprintf(
            'signatures/vehicle-loans/%s/%s.png',
            $vehicleLoan->public_id,
            Str::uuid(),
        );
        $disk = Storage::disk($this->signatureDisk());

        if (! $disk->put($path, $binary)) {
            throw new RuntimeException(
                'Tanda tangan digital tidak dapat disimpan.',
            );
        }

        $this->deleteSubmissionSignatures($vehicleLoan);
        $signedAt = now();

        return $vehicleLoan->signatures()->create([
            'signer_id' => $actor->getKey(),
            'signer_name_snapshot' => $actor->name,
            'employee_number_snapshot' => $actor->employee_number,
            'purpose' => self::SUBMISSION_SIGNATURE,
            'image_path' => $path,
            'transaction_hash' => hash(
                'sha256',
                implode('|', [
                    $vehicleLoan->loan_number,
                    self::SUBMISSION_SIGNATURE,
                    $actor->getKey(),
                    $checksum,
                    $signedAt->toIso8601String(),
                ]),
            ),
            'image_checksum' => $checksum,
            'ip_address' => $httpRequest?->ip(),
            'user_agent' => Str::limit(
                (string) $httpRequest?->userAgent(),
                2000,
                '',
            ),
            'signed_at' => $signedAt,
        ]);
    }

    private function deleteSubmissionSignatures(
        VehicleLoan $vehicleLoan,
    ): void {
        $signatures = $vehicleLoan->signatures()
            ->where('purpose', self::SUBMISSION_SIGNATURE)
            ->get();
        $disk = Storage::disk($this->signatureDisk());

        foreach ($signatures as $signature) {
            $disk->delete($signature->image_path);
            DigitalSignature::query()
                ->whereKey($signature->getKey())
                ->delete();
        }
    }

    private function signatureBinary(string $dataUrl): string
    {
        $prefix = 'data:image/png;base64,';

        if (! str_starts_with($dataUrl, $prefix)) {
            throw ValidationException::withMessages([
                'signature_data' => 'Format tanda tangan digital tidak valid.',
            ]);
        }

        $binary = base64_decode(
            substr($dataUrl, strlen($prefix)),
            true,
        );

        if (
            $binary === false
            || ! str_starts_with($binary, "\x89PNG\r\n\x1a\n")
        ) {
            throw ValidationException::withMessages([
                'signature_data' => 'Berkas tanda tangan digital tidak valid.',
            ]);
        }

        return $binary;
    }

    private function recordStatus(
        VehicleLoan $vehicleLoan,
        ?VehicleLoanStatus $previous,
        VehicleLoanStatus $new,
        ?string $notes,
        User $actor,
    ): VehicleLoanStatusHistory {
        return $vehicleLoan->statusHistories()->create([
            'previous_status' => $previous,
            'new_status' => $new,
            'notes' => $notes,
            'changed_by' => $actor->getKey(),
            'changed_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $extraValues
     */
    private function auditTransition(
        VehicleLoan $vehicleLoan,
        VehicleLoanStatus $previous,
        VehicleLoanStatus $new,
        string $event,
        User $actor,
        ?Request $httpRequest,
        array $extraValues = [],
    ): void {
        $this->auditLogger->log(
            event: $event,
            module: 'vehicle_loan',
            auditable: $vehicleLoan,
            oldValues: ['status' => $previous->value],
            newValues: [
                'status' => $new->value,
                ...$extraValues,
            ],
            request: $httpRequest,
            actorId: $actor->getKey(),
        );
    }

    private function lockLoan(VehicleLoan $vehicleLoan): VehicleLoan
    {
        return VehicleLoan::query()
            ->whereKey($vehicleLoan->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function lockVehicle(int $vehicleId): Vehicle
    {
        return Vehicle::query()
            ->whereKey($vehicleId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function assertVehicleCanBeScheduled(
        Vehicle $vehicle,
        CarbonImmutable $plannedEnd,
    ): void {
        if (! $vehicle->is_active) {
            throw ValidationException::withMessages([
                'vehicle_id' => 'Kendaraan sudah dinonaktifkan.',
            ]);
        }

        if (! in_array($vehicle->status, [
            VehicleStatus::Available,
            VehicleStatus::Reserved,
        ], true)) {
            throw ValidationException::withMessages([
                'vehicle_id' => sprintf(
                    'Kendaraan tidak dapat dijadwalkan karena berstatus %s.',
                    $vehicle->status->label(),
                ),
            ]);
        }

        if ($vehicle->registration_expiry_date === null) {
            throw ValidationException::withMessages([
                'vehicle_id' => 'Masa berlaku STNK kendaraan belum dicatat.',
            ]);
        }

        $timezone = $this->displayTimezone();
        $registrationExpiry = CarbonImmutable::parse(
            $vehicle->registration_expiry_date->toDateString(),
            $timezone,
        )->endOfDay();

        if ($registrationExpiry->lt($plannedEnd->setTimezone($timezone))) {
            throw ValidationException::withMessages([
                'vehicle_id' => 'STNK kendaraan tidak berlaku hingga jadwal selesai.',
            ]);
        }
    }

    private function assertNoScheduleConflict(
        Vehicle $vehicle,
        CarbonImmutable $start,
        CarbonImmutable $end,
        ?VehicleLoan $ignoredLoan = null,
    ): void {
        $statuses = array_values(array_map(
            static fn (VehicleLoanStatus $status): string => $status->value,
            array_filter(
                VehicleLoanStatus::cases(),
                static fn (VehicleLoanStatus $status): bool => $status
                    ->reservesSchedule(),
            ),
        ));

        $conflict = VehicleLoan::query()
            ->where('vehicle_id', $vehicle->getKey())
            ->whereIn('status', $statuses)
            ->when(
                $ignoredLoan !== null,
                static fn ($query) => $query->where(
                    $ignoredLoan->getQualifiedKeyName(),
                    '!=',
                    $ignoredLoan->getKey(),
                ),
            )
            ->where('planned_start_at', '<', $end)
            ->where('planned_end_at', '>', $start)
            ->orderBy('planned_start_at')
            ->lockForUpdate()
            ->first();

        if ($conflict === null) {
            return;
        }

        throw ValidationException::withMessages([
            'planned_start_at' => sprintf(
                'Jadwal berbenturan dengan peminjaman %s.',
                $conflict->loan_number,
            ),
        ]);
    }

    private function refreshReservationStatus(
        Vehicle $vehicle,
        VehicleLoan $ignoredLoan,
    ): void {
        if ($vehicle->status !== VehicleStatus::Reserved) {
            return;
        }

        $hasOtherReservation = VehicleLoan::query()
            ->where('vehicle_id', $vehicle->getKey())
            ->where(
                $ignoredLoan->getQualifiedKeyName(),
                '!=',
                $ignoredLoan->getKey(),
            )
            ->whereIn('status', [
                VehicleLoanStatus::Approved->value,
                VehicleLoanStatus::ReadyForPickup->value,
            ])
            ->where('planned_end_at', '>=', now())
            ->lockForUpdate()
            ->exists();

        if (! $hasOtherReservation) {
            $vehicle->forceFill([
                'status' => VehicleStatus::Available,
            ])->save();
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    private function schedule(array $data): array
    {
        $timezone = $this->displayTimezone();

        return [
            CarbonImmutable::createFromFormat(
                '!Y-m-d\TH:i',
                (string) $data['planned_start_at'],
                $timezone,
            )->utc(),
            CarbonImmutable::createFromFormat(
                '!Y-m-d\TH:i',
                (string) $data['planned_end_at'],
                $timezone,
            )->utc(),
        ];
    }

    private function requireBorrowerPhone(User $actor): void
    {
        if (filled($actor->phone)) {
            return;
        }

        throw ValidationException::withMessages([
            'phone' => 'Nomor telepon profil wajib diisi sebelum membuat peminjaman.',
        ]);
    }

    /**
     * @param  list<VehicleLoanStatus>  $allowed
     */
    private function requireStatus(
        VehicleLoan $vehicleLoan,
        array $allowed,
        string $message,
    ): void {
        if (! in_array($vehicleLoan->status, $allowed, true)) {
            throw ValidationException::withMessages([
                'loan' => $message,
            ]);
        }
    }

    private function loadLoan(VehicleLoan $vehicleLoan): VehicleLoan
    {
        return $vehicleLoan->load([
            'borrower:id,employee_number,name,phone,work_unit,position',
            'vehicle:id,public_id,vehicle_code,license_plate,brand,model,status,registration_expiry_date,storage_location',
            'reviewer:id,name',
            'approver:id,name,position',
            'statusHistories.changer:id,name',
            'signatures.signer:id,name',
        ]);
    }

    private function signatureDisk(): string
    {
        return (string) config(
            'simantap.uploads.disk',
            'local',
        );
    }

    private function displayTimezone(): string
    {
        return (string) config(
            'simantap.display_timezone',
            'Asia/Jakarta',
        );
    }
}
