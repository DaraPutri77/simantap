<?php

namespace App\Services;

use App\Enums\AttachmentCategory;
use App\Enums\ConditionCheckType;
use App\Enums\VehicleLoanStatus;
use App\Enums\VehicleOverallCondition;
use App\Enums\VehicleStatus;
use App\Models\Attachment;
use App\Models\DigitalSignature;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleConditionCheck;
use App\Models\VehicleLoan;
use App\Models\VehicleLoanStatusHistory;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class VehicleLoanLifecycleService
{
    /**
     * @var array<string, AttachmentCategory>
     */
    private const EVIDENCE_FIELDS = [
        'photo_front' => AttachmentCategory::VehicleFront,
        'photo_back' => AttachmentCategory::VehicleBack,
        'photo_left' => AttachmentCategory::VehicleLeft,
        'photo_right' => AttachmentCategory::VehicleRight,
        'photo_odometer' => AttachmentCategory::Odometer,
        'photo_fuel' => AttachmentCategory::Fuel,
        'photo_damage' => AttachmentCategory::Damage,
    ];

    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function recordCheckout(
        VehicleLoan $vehicleLoan,
        array $data,
        User $actor,
        ?Request $httpRequest = null,
    ): VehicleLoan {
        $storedEvidence = $this->storeEvidenceFiles(
            $vehicleLoan,
            ConditionCheckType::Checkout,
            $data,
            $actor,
        );

        try {
            $result = DB::transaction(function () use (
                $vehicleLoan,
                $data,
                $actor,
                $httpRequest,
                $storedEvidence,
            ): VehicleLoan {
                $locked = $this->lockLoan($vehicleLoan);
                $this->requireStatus(
                    $locked,
                    [VehicleLoanStatus::Approved],
                    'Hanya peminjaman yang sudah disetujui yang dapat diperiksa untuk serah terima.',
                );

                $vehicle = $this->lockVehicle($locked->vehicle_id);
                $this->requireVehicleStatus(
                    $vehicle,
                    VehicleStatus::Reserved,
                    'Kendaraan harus berstatus Dipesan sebelum pemeriksaan serah terima.',
                );

                $this->assertNoConditionCheck(
                    $locked,
                    ConditionCheckType::Checkout,
                );
                $this->assertOdometerNotLower(
                    (float) $data['odometer'],
                    (float) $vehicle->current_odometer,
                    'Odometer awal tidak boleh lebih kecil daripada odometer master kendaraan.',
                );

                $conditionCheck = $this->createConditionCheck(
                    $locked,
                    ConditionCheckType::Checkout,
                    $data,
                    $actor,
                );
                $this->createAttachmentRecords(
                    $conditionCheck,
                    $storedEvidence,
                );

                $previousStatus = $locked->status;
                $locked->forceFill([
                    'status' => VehicleLoanStatus::ReadyForPickup,
                ])->save();

                $this->recordStatus(
                    $locked,
                    $previousStatus,
                    VehicleLoanStatus::ReadyForPickup,
                    'Pemeriksaan kondisi awal selesai. Kendaraan siap dikonfirmasi peminjam.',
                    $actor,
                );
                $this->auditTransition(
                    $locked,
                    $previousStatus,
                    VehicleLoanStatus::ReadyForPickup,
                    'vehicle_loan_checkout_recorded',
                    $actor,
                    $httpRequest,
                    [
                        'condition_check_id' => $conditionCheck->getKey(),
                        'odometer' => (string) $conditionCheck->odometer,
                        'fuel_level' => $conditionCheck->fuel_level,
                        'overall_condition' => $conditionCheck->overall_condition->value,
                        'evidence_count' => count($storedEvidence),
                    ],
                );

                return $locked;
            }, 3);
        } catch (Throwable $exception) {
            $this->deleteStoredFiles($storedEvidence);

            throw $exception;
        }

        return $this->loadLoan($result);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function confirmPickup(
        VehicleLoan $vehicleLoan,
        array $data,
        User $actor,
        ?Request $httpRequest = null,
    ): VehicleLoan {
        $signatureFile = $this->storeSignatureFile(
            $vehicleLoan,
            (string) $data['signature_data'],
        );

        try {
            $result = DB::transaction(function () use (
                $vehicleLoan,
                $actor,
                $httpRequest,
                $signatureFile,
            ): VehicleLoan {
                $locked = $this->lockLoan($vehicleLoan);
                $this->assertBorrower($locked, $actor);
                $this->requireStatus(
                    $locked,
                    [VehicleLoanStatus::ReadyForPickup],
                    'Kendaraan belum berada pada tahap siap diambil.',
                );

                $vehicle = $this->lockVehicle($locked->vehicle_id);
                $this->requireVehicleStatus(
                    $vehicle,
                    VehicleStatus::Reserved,
                    'Kendaraan tidak lagi berada dalam reservasi yang sah.',
                );

                $checkout = VehicleConditionCheck::query()
                    ->where('vehicle_loan_id', $locked->getKey())
                    ->where('check_type', ConditionCheckType::Checkout->value)
                    ->lockForUpdate()
                    ->first();

                if ($checkout === null) {
                    throw ValidationException::withMessages([
                        'loan' => 'Pemeriksaan kondisi awal wajib tersedia sebelum kendaraan diserahkan.',
                    ]);
                }

                if ($checkout->borrower_confirmed_at !== null) {
                    throw ValidationException::withMessages([
                        'loan' => 'Serah terima kendaraan sudah pernah dikonfirmasi.',
                    ]);
                }

                $existingSignature = DigitalSignature::query()
                    ->where('signable_type', $locked->getMorphClass())
                    ->where('signable_id', $locked->getKey())
                    ->where('purpose', VehicleLoan::PICKUP_SIGNATURE_PURPOSE)
                    ->lockForUpdate()
                    ->first();

                if ($existingSignature !== null) {
                    throw ValidationException::withMessages([
                        'loan' => 'Tanda tangan serah terima sudah tersimpan.',
                    ]);
                }

                $signedAt = now();
                $locked->signatures()->create([
                    'signer_id' => $actor->getKey(),
                    'signer_name_snapshot' => $actor->name,
                    'employee_number_snapshot' => $actor->employee_number,
                    'purpose' => VehicleLoan::PICKUP_SIGNATURE_PURPOSE,
                    'image_path' => $signatureFile['path'],
                    'transaction_hash' => hash(
                        'sha256',
                        implode('|', [
                            $locked->loan_number,
                            VehicleLoan::PICKUP_SIGNATURE_PURPOSE,
                            $actor->getKey(),
                            $signatureFile['checksum'],
                            $signedAt->toIso8601String(),
                        ]),
                    ),
                    'image_checksum' => $signatureFile['checksum'],
                    'ip_address' => $httpRequest?->ip(),
                    'user_agent' => Str::limit(
                        (string) $httpRequest?->userAgent(),
                        2000,
                        '',
                    ),
                    'signed_at' => $signedAt,
                ]);

                $checkout->forceFill([
                    'borrower_confirmed_at' => $signedAt,
                ])->save();

                $previousStatus = $locked->status;
                $locked->forceFill([
                    'status' => VehicleLoanStatus::Borrowed,
                    'actual_start_at' => $signedAt,
                ])->save();

                $vehicle->forceFill([
                    'status' => VehicleStatus::Borrowed,
                ])->save();

                $this->recordStatus(
                    $locked,
                    $previousStatus,
                    VehicleLoanStatus::Borrowed,
                    'Serah terima dikonfirmasi peminjam. Kendaraan mulai dipinjam.',
                    $actor,
                );
                $this->auditTransition(
                    $locked,
                    $previousStatus,
                    VehicleLoanStatus::Borrowed,
                    'vehicle_loan_pickup_confirmed',
                    $actor,
                    $httpRequest,
                    [
                        'actual_start_at' => $signedAt->toIso8601String(),
                        'vehicle_status' => VehicleStatus::Borrowed->value,
                    ],
                );

                return $locked;
            }, 3);
        } catch (Throwable $exception) {
            Storage::disk($this->evidenceDisk())
                ->delete($signatureFile['path']);

            throw $exception;
        }

        return $this->loadLoan($result);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function requestReturn(
        VehicleLoan $vehicleLoan,
        array $data,
        User $actor,
        ?Request $httpRequest = null,
    ): VehicleLoan {
        $result = DB::transaction(function () use (
            $vehicleLoan,
            $data,
            $actor,
            $httpRequest,
        ): VehicleLoan {
            $locked = $this->lockLoan($vehicleLoan);
            $this->assertBorrower($locked, $actor);
            $this->requireStatus(
                $locked,
                [VehicleLoanStatus::Borrowed],
                'Hanya kendaraan yang sedang dipinjam yang dapat diajukan untuk pengembalian.',
            );

            $vehicle = $this->lockVehicle($locked->vehicle_id);
            $this->requireVehicleStatus(
                $vehicle,
                VehicleStatus::Borrowed,
                'Status kendaraan tidak sesuai dengan peminjaman aktif.',
            );

            $returnedAt = now();
            $isLate = $locked->planned_end_at !== null
                && $returnedAt->gt($locked->planned_end_at);
            $overdueAt = $locked->overdue_at;

            if ($isLate && $overdueAt === null) {
                $overdueAt = $locked->planned_end_at;
            }

            $previousStatus = $locked->status;
            $locked->forceFill([
                'status' => VehicleLoanStatus::AwaitingReturnInspection,
                'actual_end_at' => $returnedAt,
                'overdue_at' => $overdueAt,
            ])->save();

            $notes = trim((string) ($data['return_notes'] ?? ''));
            $historyNote = $isLate
                ? 'Pengembalian diajukan setelah batas waktu dan menunggu pemeriksaan Administrator.'
                : 'Pengembalian diajukan dan menunggu pemeriksaan Administrator.';

            if ($notes !== '') {
                $historyNote .= "\nCatatan peminjam: {$notes}";
            }

            $this->recordStatus(
                $locked,
                $previousStatus,
                VehicleLoanStatus::AwaitingReturnInspection,
                $historyNote,
                $actor,
            );
            $this->auditTransition(
                $locked,
                $previousStatus,
                VehicleLoanStatus::AwaitingReturnInspection,
                'vehicle_loan_return_requested',
                $actor,
                $httpRequest,
                [
                    'actual_end_at' => $returnedAt->toIso8601String(),
                    'overdue_at' => $overdueAt?->toIso8601String(),
                    'is_late' => $isLate,
                ],
            );

            return $locked;
        }, 3);

        return $this->loadLoan($result);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function inspectReturn(
        VehicleLoan $vehicleLoan,
        array $data,
        User $actor,
        ?Request $httpRequest = null,
    ): VehicleLoan {
        $storedEvidence = $this->storeEvidenceFiles(
            $vehicleLoan,
            ConditionCheckType::Return,
            $data,
            $actor,
        );

        try {
            $result = DB::transaction(function () use (
                $vehicleLoan,
                $data,
                $actor,
                $httpRequest,
                $storedEvidence,
            ): VehicleLoan {
                $locked = $this->lockLoan($vehicleLoan);
                $this->requireStatus(
                    $locked,
                    [VehicleLoanStatus::AwaitingReturnInspection],
                    'Peminjaman belum berada pada tahap pemeriksaan pengembalian.',
                );

                $vehicle = $this->lockVehicle($locked->vehicle_id);
                $this->requireVehicleStatus(
                    $vehicle,
                    VehicleStatus::Borrowed,
                    'Kendaraan tidak berada pada status Dipinjam.',
                );

                $checkout = VehicleConditionCheck::query()
                    ->where('vehicle_loan_id', $locked->getKey())
                    ->where('check_type', ConditionCheckType::Checkout->value)
                    ->lockForUpdate()
                    ->first();

                if ($checkout === null) {
                    throw ValidationException::withMessages([
                        'loan' => 'Pemeriksaan kondisi awal tidak ditemukan.',
                    ]);
                }

                $this->assertNoConditionCheck(
                    $locked,
                    ConditionCheckType::Return,
                );

                $returnOdometer = (float) $data['odometer'];
                $minimumOdometer = max(
                    (float) $checkout->odometer,
                    (float) $vehicle->current_odometer,
                );
                $this->assertOdometerNotLower(
                    $returnOdometer,
                    $minimumOdometer,
                    'Odometer akhir tidak boleh lebih kecil daripada odometer awal atau odometer master kendaraan.',
                );

                $returnCheck = $this->createConditionCheck(
                    $locked,
                    ConditionCheckType::Return,
                    $data,
                    $actor,
                );
                $this->createAttachmentRecords(
                    $returnCheck,
                    $storedEvidence,
                );

                $actualEndAt = $locked->actual_end_at ?? now();
                $overdueAt = $locked->overdue_at;

                if (
                    $locked->planned_end_at !== null
                    && $actualEndAt->gt($locked->planned_end_at)
                    && $overdueAt === null
                ) {
                    $overdueAt = $locked->planned_end_at;
                }

                $isGood = $returnCheck->overall_condition
                    === VehicleOverallCondition::Good;
                $newLoanStatus = $isGood
                    ? VehicleLoanStatus::Completed
                    : VehicleLoanStatus::ReturnIssue;

                $vehicleStatus = $isGood
                    ? $this->statusAfterSuccessfulReturn($vehicle, $locked)
                    : VehicleStatus::Inspection;

                $previousStatus = $locked->status;
                $locked->forceFill([
                    'status' => $newLoanStatus,
                    'actual_end_at' => $actualEndAt,
                    'overdue_at' => $overdueAt,
                ])->save();

                $vehicle->forceFill([
                    'current_odometer' => $returnOdometer,
                    'status' => $vehicleStatus,
                ])->save();

                $historyNote = $isGood
                    ? 'Pemeriksaan pengembalian selesai. Kendaraan dinyatakan baik dan peminjaman diselesaikan.'
                    : 'Pemeriksaan pengembalian menemukan kondisi yang memerlukan tindak lanjut. Kendaraan masuk status Perlu Pemeriksaan.';

                $this->recordStatus(
                    $locked,
                    $previousStatus,
                    $newLoanStatus,
                    $historyNote,
                    $actor,
                );
                $this->auditTransition(
                    $locked,
                    $previousStatus,
                    $newLoanStatus,
                    $isGood
                        ? 'vehicle_loan_return_completed'
                        : 'vehicle_loan_return_issue',
                    $actor,
                    $httpRequest,
                    [
                        'condition_check_id' => $returnCheck->getKey(),
                        'actual_end_at' => $actualEndAt->toIso8601String(),
                        'overdue_at' => $overdueAt?->toIso8601String(),
                        'odometer' => (string) $returnCheck->odometer,
                        'fuel_level' => $returnCheck->fuel_level,
                        'overall_condition' => $returnCheck->overall_condition->value,
                        'vehicle_status' => $vehicleStatus->value,
                        'evidence_count' => count($storedEvidence),
                    ],
                );

                return $locked;
            }, 3);
        } catch (Throwable $exception) {
            $this->deleteStoredFiles($storedEvidence);

            throw $exception;
        }

        return $this->loadLoan($result);
    }

    public function signatureDataUri(
        ?DigitalSignature $signature,
    ): ?string {
        if ($signature === null) {
            return null;
        }

        $disk = Storage::disk($this->evidenceDisk());

        if (! $disk->exists($signature->image_path)) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode(
            $disk->get($signature->image_path),
        );
    }

    public function attachmentDataUri(Attachment $attachment): ?string
    {
        if (! $attachment->isImage()) {
            return null;
        }

        $disk = Storage::disk($attachment->disk);

        if (! $disk->exists($attachment->file_path)) {
            return null;
        }

        return sprintf(
            'data:%s;base64,%s',
            $attachment->mime_type,
            base64_encode($disk->get($attachment->file_path)),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createConditionCheck(
        VehicleLoan $vehicleLoan,
        ConditionCheckType $type,
        array $data,
        User $actor,
    ): VehicleConditionCheck {
        return $vehicleLoan->conditionChecks()->create([
            'check_type' => $type,
            'odometer' => $data['odometer'],
            'fuel_level' => $data['fuel_level'],
            'overall_condition' => $data['overall_condition'],
            'body_condition' => $data['body_condition'],
            'engine_condition' => $data['engine_condition'],
            'tire_condition' => $data['tire_condition'],
            'equipment_condition' => $data['equipment_condition'],
            'damage_notes' => $data['damage_notes'] ?? null,
            'checked_by' => $actor->getKey(),
            'checked_at' => now(),
        ]);
    }

    private function assertNoConditionCheck(
        VehicleLoan $vehicleLoan,
        ConditionCheckType $type,
    ): void {
        $existing = VehicleConditionCheck::query()
            ->where('vehicle_loan_id', $vehicleLoan->getKey())
            ->where('check_type', $type->value)
            ->lockForUpdate()
            ->first();

        if ($existing !== null) {
            throw ValidationException::withMessages([
                'loan' => sprintf(
                    '%s sudah pernah dibuat untuk peminjaman ini.',
                    $type->label(),
                ),
            ]);
        }
    }

    private function assertBorrower(
        VehicleLoan $vehicleLoan,
        User $actor,
    ): void {
        if ($vehicleLoan->borrower_id === $actor->getKey()) {
            return;
        }

        throw ValidationException::withMessages([
            'loan' => 'Tindakan ini hanya dapat dilakukan oleh peminjam yang tercatat.',
        ]);
    }

    private function assertOdometerNotLower(
        float $actual,
        float $minimum,
        string $message,
    ): void {
        if ($actual + 0.00001 >= $minimum) {
            return;
        }

        throw ValidationException::withMessages([
            'odometer' => $message,
        ]);
    }

    private function requireVehicleStatus(
        Vehicle $vehicle,
        VehicleStatus $required,
        string $message,
    ): void {
        if ($vehicle->is_active && $vehicle->status === $required) {
            return;
        }

        throw ValidationException::withMessages([
            'vehicle' => $message,
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
        if (in_array($vehicleLoan->status, $allowed, true)) {
            return;
        }

        throw ValidationException::withMessages([
            'loan' => $message,
        ]);
    }

    private function statusAfterSuccessfulReturn(
        Vehicle $vehicle,
        VehicleLoan $returnedLoan,
    ): VehicleStatus {
        $futureReservation = VehicleLoan::query()
            ->where('vehicle_id', $vehicle->getKey())
            ->where(
                $returnedLoan->getQualifiedKeyName(),
                '!=',
                $returnedLoan->getKey(),
            )
            ->whereIn('status', [
                VehicleLoanStatus::Approved->value,
                VehicleLoanStatus::ReadyForPickup->value,
            ])
            ->where('planned_end_at', '>=', now())
            ->orderBy('planned_start_at')
            ->lockForUpdate()
            ->first();

        return $futureReservation === null
            ? VehicleStatus::Available
            : VehicleStatus::Reserved;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array{
     *     category: AttachmentCategory,
     *     disk: string,
     *     original_name: string,
     *     stored_name: string,
     *     path: string,
     *     mime_type: string,
     *     file_size: int,
     *     checksum: string,
     *     uploaded_by: int,
     *     metadata: array<string, mixed>
     * }>
     */
    private function storeEvidenceFiles(
        VehicleLoan $vehicleLoan,
        ConditionCheckType $type,
        array $data,
        User $actor,
    ): array {
        $diskName = $this->evidenceDisk();
        $disk = Storage::disk($diskName);
        $directory = sprintf(
            'vehicle-loans/%s/%s',
            $vehicleLoan->public_id,
            $type->value,
        );
        $stored = [];

        try {
            foreach (self::EVIDENCE_FIELDS as $field => $category) {
                $file = $data[$field] ?? null;

                if (! $file instanceof UploadedFile) {
                    continue;
                }

                $extension = strtolower(
                    (string) ($file->guessExtension()
                        ?: $file->getClientOriginalExtension()
                        ?: 'bin'),
                );
                $storedName = Str::uuid().'.'.$extension;
                $path = $disk->putFileAs(
                    $directory,
                    $file,
                    $storedName,
                );

                if (! is_string($path) || $path === '') {
                    throw new RuntimeException(
                        'Bukti foto kondisi kendaraan tidak dapat disimpan.',
                    );
                }

                $checksum = hash_file(
                    'sha256',
                    $file->getRealPath(),
                );

                if (! is_string($checksum)) {
                    throw new RuntimeException(
                        'Checksum bukti foto kondisi kendaraan tidak dapat dibuat.',
                    );
                }

                $stored[] = [
                    'category' => $category,
                    'disk' => $diskName,
                    'original_name' => $file->getClientOriginalName(),
                    'stored_name' => $storedName,
                    'path' => $path,
                    'mime_type' => (string) ($file->getMimeType()
                        ?: 'application/octet-stream'),
                    'file_size' => (int) $file->getSize(),
                    'checksum' => $checksum,
                    'uploaded_by' => (int) $actor->getKey(),
                    'metadata' => [
                        'check_type' => $type->value,
                        'source' => 'vehicle_loan_lifecycle',
                    ],
                ];
            }
        } catch (Throwable $exception) {
            $this->deleteStoredFiles($stored);

            throw $exception;
        }

        return $stored;
    }

    /**
     * @param  list<array{
     *     category: AttachmentCategory,
     *     disk: string,
     *     original_name: string,
     *     stored_name: string,
     *     path: string,
     *     mime_type: string,
     *     file_size: int,
     *     checksum: string,
     *     uploaded_by: int,
     *     metadata: array<string, mixed>
     * }>  $storedEvidence
     */
    private function createAttachmentRecords(
        VehicleConditionCheck $conditionCheck,
        array $storedEvidence,
    ): void {
        foreach ($storedEvidence as $evidence) {
            $conditionCheck->attachments()->create([
                'file_category' => $evidence['category'],
                'disk' => $evidence['disk'],
                'original_name' => $evidence['original_name'],
                'stored_name' => $evidence['stored_name'],
                'file_path' => $evidence['path'],
                'mime_type' => $evidence['mime_type'],
                'file_size' => $evidence['file_size'],
                'checksum' => $evidence['checksum'],
                'metadata' => $evidence['metadata'],
                'uploaded_by' => $evidence['uploaded_by'],
            ]);
        }
    }

    /**
     * @param  list<array{disk: string, path: string}>  $storedFiles
     */
    private function deleteStoredFiles(array $storedFiles): void
    {
        foreach ($storedFiles as $storedFile) {
            Storage::disk($storedFile['disk'])
                ->delete($storedFile['path']);
        }
    }

    /**
     * @return array{path: string, checksum: string}
     */
    private function storeSignatureFile(
        VehicleLoan $vehicleLoan,
        string $dataUrl,
    ): array {
        $binary = $this->signatureBinary($dataUrl);
        $checksum = hash(
            (string) config(
                'simantap.signature.hash_algorithm',
                'sha256',
            ),
            $binary,
        );
        $path = sprintf(
            'signatures/vehicle-loans/%s/pickup/%s.png',
            $vehicleLoan->public_id,
            Str::uuid(),
        );

        if (! Storage::disk($this->evidenceDisk())->put($path, $binary)) {
            throw new RuntimeException(
                'Tanda tangan serah terima tidak dapat disimpan.',
            );
        }

        return [
            'path' => $path,
            'checksum' => $checksum,
        ];
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

    private function loadLoan(VehicleLoan $vehicleLoan): VehicleLoan
    {
        return $vehicleLoan->load([
            'borrower:id,employee_number,name,phone,work_unit,position',
            'vehicle:id,public_id,vehicle_code,license_plate,brand,model,status,current_odometer,registration_expiry_date,storage_location,responsible_person',
            'conditionChecks.checker:id,name',
            'conditionChecks.attachments',
            'statusHistories.changer:id,name',
            'signatures.signer:id,name',
        ]);
    }

    private function evidenceDisk(): string
    {
        return (string) config(
            'simantap.uploads.disk',
            'local',
        );
    }
}
