<?php

namespace App\Services;

use App\Enums\AttachmentCategory;
use App\Enums\DocumentType;
use App\Enums\MaintenanceStatus;
use App\Enums\MaintenanceSubjectType;
use App\Enums\OperationalAssetStatus;
use App\Enums\VehicleLoanStatus;
use App\Enums\VehicleStatus;
use App\Models\MaintenanceRecord;
use App\Models\MaintenanceStatusHistory;
use App\Models\OperationalAsset;
use App\Models\User;
use App\Models\Vehicle;
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

class MaintenanceService
{
    /**
     * @var list<MaintenanceStatus>
     */
    private const ACTIVE_STATUSES = [
        MaintenanceStatus::Reported,
        MaintenanceStatus::Approved,
        MaintenanceStatus::InProgress,
        MaintenanceStatus::FurtherActionRequired,
    ];

    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly DocumentNumberService $documentNumberService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function report(
        Vehicle $vehicle,
        ?VehicleLoan $sourceVehicleLoan,
        array $data,
        User $actor,
        ?Request $httpRequest = null,
    ): MaintenanceRecord {
        $publicId = (string) Str::uuid();
        $storedFiles = $this->storeReportFiles(
            $publicId,
            $data,
            $actor,
        );

        try {
            $record = DB::transaction(function () use (
                $vehicle,
                $sourceVehicleLoan,
                $data,
                $actor,
                $httpRequest,
                $publicId,
                $storedFiles,
            ): MaintenanceRecord {
                $lockedVehicle = $this->lockVehicle($vehicle->getKey());
                $this->assertVehicleMayEnterMaintenance($lockedVehicle);
                $this->assertNoActiveMaintenance($lockedVehicle);

                $lockedSourceLoan = null;
                if ($sourceVehicleLoan !== null) {
                    $lockedSourceLoan = $this->lockLoan(
                        $sourceVehicleLoan->getKey(),
                    );
                    $this->assertValidReturnIssueSource(
                        $lockedVehicle,
                        $lockedSourceLoan,
                    );
                    $this->assertNoMaintenanceForSourceLoan(
                        $lockedSourceLoan,
                    );
                } else {
                    $this->assertNoUnlinkedReturnIssue($lockedVehicle);
                }

                $statusBefore = $lockedVehicle->status;
                $record = MaintenanceRecord::query()->create([
                    'public_id' => $publicId,
                    'maintenance_number' => $this->documentNumberService
                        ->next(DocumentType::Maintenance),
                    'vehicle_id' => $lockedVehicle->getKey(),
                    'source_vehicle_loan_id' => $lockedSourceLoan?->getKey(),
                    'vehicle_snapshot' => $this->vehicleSnapshot($lockedVehicle),
                    'vehicle_status_before' => $statusBefore,
                    'reported_by' => $actor->getKey(),
                    'maintenance_type' => $data['maintenance_type'],
                    'complaint' => $data['complaint'],
                    'initial_condition' => $data['initial_condition'],
                    'reported_date' => $data['reported_date'],
                    'status' => MaintenanceStatus::Reported,
                ]);

                $this->createAttachmentRecords($record, $storedFiles);

                if ($lockedVehicle->status === VehicleStatus::Available) {
                    $lockedVehicle->forceFill([
                        'status' => VehicleStatus::Inspection,
                    ])->save();
                }

                $this->recordStatus(
                    $record,
                    null,
                    MaintenanceStatus::Reported,
                    $lockedSourceLoan === null
                        ? 'Laporan pemeliharaan dibuat secara manual.'
                        : 'Laporan pemeliharaan dibuat dari masalah pengembalian kendaraan.',
                    $actor,
                );

                $this->auditLogger->log(
                    'maintenance_reported',
                    'maintenance',
                    $record,
                    null,
                    [
                        'maintenance_number' => $record->maintenance_number,
                        'vehicle_id' => $record->vehicle_id,
                        'source_vehicle_loan_id' => $record->source_vehicle_loan_id,
                        'status' => MaintenanceStatus::Reported->value,
                        'vehicle_status' => $lockedVehicle->status->value,
                        'evidence_count' => count($storedFiles),
                    ],
                    $httpRequest,
                    (int) $actor->getKey(),
                );

                return $record;
            }, 3);
        } catch (Throwable $exception) {
            $this->deleteStoredFiles($storedFiles);

            throw $exception;
        }

        return $this->loadRecord($record);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function reportOperationalAsset(
        OperationalAsset $operationalAsset,
        array $data,
        User $actor,
        ?Request $httpRequest = null,
    ): MaintenanceRecord {
        $publicId = (string) Str::uuid();
        $storedFiles = $this->storeReportFiles(
            $publicId,
            $data,
            $actor,
        );

        try {
            $record = DB::transaction(function () use (
                $operationalAsset,
                $data,
                $actor,
                $httpRequest,
                $publicId,
                $storedFiles,
            ): MaintenanceRecord {
                $lockedAsset = $this->lockOperationalAsset(
                    $operationalAsset->getKey(),
                );
                $this->assertOperationalAssetMayEnterMaintenance($lockedAsset);
                $this->assertNoActiveOperationalAssetMaintenance($lockedAsset);

                $statusBefore = $lockedAsset->status;
                $record = MaintenanceRecord::query()->create([
                    'public_id' => $publicId,
                    'maintenance_number' => $this->documentNumberService
                        ->next(DocumentType::Maintenance),
                    'vehicle_id' => null,
                    'operational_asset_id' => $lockedAsset->getKey(),
                    'source_vehicle_loan_id' => null,
                    'vehicle_snapshot' => null,
                    'operational_asset_snapshot' => $this->operationalAssetSnapshot(
                        $lockedAsset,
                    ),
                    'vehicle_status_before' => null,
                    'operational_asset_status_before' => $statusBefore,
                    'reported_by' => $actor->getKey(),
                    'maintenance_type' => $data['maintenance_type'],
                    'complaint' => $data['complaint'],
                    'initial_condition' => $data['initial_condition'],
                    'reported_date' => $data['reported_date'],
                    'status' => MaintenanceStatus::Reported,
                ]);

                $this->createAttachmentRecords($record, $storedFiles);

                if ($lockedAsset->status === OperationalAssetStatus::Available) {
                    $lockedAsset->forceFill([
                        'status' => OperationalAssetStatus::Inspection,
                    ])->save();
                }

                $this->recordStatus(
                    $record,
                    null,
                    MaintenanceStatus::Reported,
                    'Laporan pemeliharaan aset perangkat dibuat secara manual.',
                    $actor,
                );

                $this->auditLogger->log(
                    'maintenance_reported',
                    'maintenance',
                    $record,
                    null,
                    [
                        'maintenance_number' => $record->maintenance_number,
                        'subject_type' => MaintenanceSubjectType::OperationalAsset->value,
                        'operational_asset_id' => $record->operational_asset_id,
                        'status' => MaintenanceStatus::Reported->value,
                        'operational_asset_status' => $lockedAsset->status->value,
                        'evidence_count' => count($storedFiles),
                    ],
                    $httpRequest,
                    (int) $actor->getKey(),
                );

                return $record;
            }, 3);
        } catch (Throwable $exception) {
            $this->deleteStoredFiles($storedFiles);

            throw $exception;
        }

        return $this->loadRecord($record);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function approve(
        MaintenanceRecord $maintenanceRecord,
        array $data,
        User $actor,
        ?Request $httpRequest = null,
    ): MaintenanceRecord {
        $record = DB::transaction(function () use (
            $maintenanceRecord,
            $data,
            $actor,
            $httpRequest,
        ): MaintenanceRecord {
            $locked = $this->lockRecord($maintenanceRecord->getKey());
            $this->requireStatus(
                $locked,
                [MaintenanceStatus::Reported],
                'Hanya laporan pemeliharaan berstatus Dilaporkan yang dapat disetujui.',
            );
            $this->lockSubject($locked);

            $previousStatus = $locked->status;
            $locked->forceFill([
                'status' => MaintenanceStatus::Approved,
                'handled_by' => $locked->handled_by ?? $actor->getKey(),
                'approved_by' => $actor->getKey(),
                'approved_at' => now(),
                'approval_notes' => $data['approval_notes'] ?? null,
            ])->save();

            $this->recordStatus(
                $locked,
                $previousStatus,
                MaintenanceStatus::Approved,
                trim((string) ($data['approval_notes'] ?? '')) !== ''
                    ? (string) $data['approval_notes']
                    : 'Laporan pemeliharaan disetujui untuk ditindaklanjuti.',
                $actor,
            );

            $this->auditTransition(
                $locked,
                $previousStatus,
                MaintenanceStatus::Approved,
                'maintenance_approved',
                $actor,
                $httpRequest,
            );

            return $locked;
        }, 3);

        return $this->loadRecord($record);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function start(
        MaintenanceRecord $maintenanceRecord,
        array $data,
        User $actor,
        ?Request $httpRequest = null,
    ): MaintenanceRecord {
        $record = DB::transaction(function () use (
            $maintenanceRecord,
            $data,
            $actor,
            $httpRequest,
        ): MaintenanceRecord {
            $locked = $this->lockRecord($maintenanceRecord->getKey());
            $this->requireStatus(
                $locked,
                [
                    MaintenanceStatus::Approved,
                    MaintenanceStatus::FurtherActionRequired,
                ],
                'Pemeliharaan belum dapat dimulai pada status saat ini.',
            );
            $subject = $this->lockSubject($locked);

            if ($subject instanceof Vehicle) {
                if (! $subject->is_active) {
                    throw ValidationException::withMessages([
                        'vehicle' => 'Kendaraan nonaktif tidak dapat dimasukkan ke proses pemeliharaan operasional.',
                    ]);
                }

                if (in_array($subject->status, [
                    VehicleStatus::Reserved,
                    VehicleStatus::Borrowed,
                ], true)) {
                    throw ValidationException::withMessages([
                        'vehicle' => 'Kendaraan sedang terikat transaksi peminjaman dan tidak dapat mulai dipelihara.',
                    ]);
                }
            } elseif (! $subject->is_active) {
                throw ValidationException::withMessages([
                    'operational_asset' => 'Aset perangkat nonaktif tidak dapat mulai dipelihara.',
                ]);
            }

            $previousStatus = $locked->status;
            $locked->forceFill([
                'status' => MaintenanceStatus::InProgress,
                'handled_by' => $actor->getKey(),
                'service_provider' => $data['service_provider'] ?? null,
                'start_date' => $data['start_date'],
                'started_at' => now(),
                'completion_date' => null,
                'completed_at' => null,
            ])->save();

            $subject->forceFill([
                'status' => $subject instanceof Vehicle
                    ? VehicleStatus::Maintenance
                    : OperationalAssetStatus::Maintenance,
            ])->save();

            $this->recordStatus(
                $locked,
                $previousStatus,
                MaintenanceStatus::InProgress,
                'Pengerjaan pemeliharaan dimulai.',
                $actor,
            );

            $this->auditTransition(
                $locked,
                $previousStatus,
                MaintenanceStatus::InProgress,
                'maintenance_started',
                $actor,
                $httpRequest,
                [
                    ...$this->subjectAuditValues($locked, $subject),
                    'start_date' => (string) $locked->start_date?->toDateString(),
                    'service_provider' => $locked->service_provider,
                ],
            );

            return $locked;
        }, 3);

        return $this->loadRecord($record);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function complete(
        MaintenanceRecord $maintenanceRecord,
        array $data,
        User $actor,
        ?Request $httpRequest = null,
    ): MaintenanceRecord {
        $storedFiles = $this->storeCompletionFiles(
            $maintenanceRecord->public_id,
            $data,
            $actor,
        );

        try {
            $record = DB::transaction(function () use (
                $maintenanceRecord,
                $data,
                $actor,
                $httpRequest,
                $storedFiles,
            ): MaintenanceRecord {
                $locked = $this->lockRecord($maintenanceRecord->getKey());
                $this->requireStatus(
                    $locked,
                    [MaintenanceStatus::InProgress],
                    'Hanya pemeliharaan yang sedang dikerjakan yang dapat diselesaikan.',
                );

                $subject = $this->lockSubject($locked);
                $outcome = MaintenanceStatus::from(
                    (string) $data['outcome_status'],
                );
                $this->assertCompletionOutcome($outcome);

                $previousStatus = $locked->status;
                $terminal = $outcome !== MaintenanceStatus::FurtherActionRequired;

                $locked->forceFill([
                    'status' => $outcome,
                    'completion_date' => $data['completion_date'],
                    'completed_at' => $terminal ? now() : null,
                    'cost' => $data['cost'] ?? null,
                    'result' => $data['result'],
                    'final_condition' => $data['final_condition'],
                ])->save();

                $this->createAttachmentRecords($locked, $storedFiles);

                $subjectStatus = $subject instanceof Vehicle
                    ? $this->vehicleStatusForOutcome(
                        $outcome,
                        $subject,
                        $locked,
                    )
                    : $this->operationalAssetStatusForOutcome(
                        $outcome,
                        $subject,
                    );
                $subjectChanges = ['status' => $subjectStatus];

                if ($outcome === MaintenanceStatus::Unserviceable) {
                    $subjectChanges['is_active'] = false;
                }

                $subject->forceFill($subjectChanges)->save();

                if ($subject instanceof Vehicle && in_array($outcome, [
                    MaintenanceStatus::Completed,
                    MaintenanceStatus::CompletedWithNotes,
                ], true)) {
                    $this->resolveSourceReturnIssue(
                        $locked,
                        $actor,
                        $httpRequest,
                    );
                }

                $this->recordStatus(
                    $locked,
                    $previousStatus,
                    $outcome,
                    $this->outcomeHistoryNote(
                        $outcome,
                        $locked->subjectType(),
                    ),
                    $actor,
                );

                $this->auditTransition(
                    $locked,
                    $previousStatus,
                    $outcome,
                    'maintenance_completed',
                    $actor,
                    $httpRequest,
                    [
                        ...$this->subjectAuditValues($locked, $subject),
                        'cost' => $locked->cost,
                        'completion_date' => (string) $locked->completion_date?->toDateString(),
                        'evidence_count' => count($storedFiles),
                    ],
                );

                return $locked;
            }, 3);
        } catch (Throwable $exception) {
            $this->deleteStoredFiles($storedFiles);

            throw $exception;
        }

        return $this->loadRecord($record);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function cancel(
        MaintenanceRecord $maintenanceRecord,
        array $data,
        User $actor,
        ?Request $httpRequest = null,
    ): MaintenanceRecord {
        $record = DB::transaction(function () use (
            $maintenanceRecord,
            $data,
            $actor,
            $httpRequest,
        ): MaintenanceRecord {
            $locked = $this->lockRecord($maintenanceRecord->getKey());
            $this->requireStatus(
                $locked,
                self::ACTIVE_STATUSES,
                'Pemeliharaan pada status ini tidak dapat dibatalkan.',
            );
            $subject = $this->lockSubject($locked);

            $previousStatus = $locked->status;
            $locked->forceFill([
                'status' => MaintenanceStatus::Cancelled,
                'cancelled_by' => $actor->getKey(),
                'cancelled_at' => now(),
                'cancellation_reason' => $data['cancellation_reason'],
            ])->save();

            $subjectStatus = $subject instanceof Vehicle
                ? $this->statusAfterCancellation($subject, $locked)
                : $this->operationalAssetStatusAfterCancellation(
                    $subject,
                    $locked,
                );
            $subject->forceFill([
                'status' => $subjectStatus,
            ])->save();

            $this->recordStatus(
                $locked,
                $previousStatus,
                MaintenanceStatus::Cancelled,
                (string) $data['cancellation_reason'],
                $actor,
            );

            $this->auditTransition(
                $locked,
                $previousStatus,
                MaintenanceStatus::Cancelled,
                'maintenance_cancelled',
                $actor,
                $httpRequest,
                [
                    ...$this->subjectAuditValues($locked, $subject),
                    'cancellation_reason' => $data['cancellation_reason'],
                ],
            );

            return $locked;
        }, 3);

        return $this->loadRecord($record);
    }

    public function auditPdfDownload(
        MaintenanceRecord $maintenanceRecord,
        User $actor,
        ?Request $httpRequest = null,
    ): void {
        $this->auditLogger->log(
            event: 'maintenance_pdf_downloaded',
            module: 'maintenance',
            auditable: $maintenanceRecord,
            oldValues: [],
            newValues: [
                'document_type' => 'maintenance_record',
                'maintenance_number' => $maintenanceRecord
                    ->maintenance_number,
                'status' => $maintenanceRecord->status->value,
            ],
            request: $httpRequest,
            actorId: (int) $actor->getKey(),
        );
    }

    private function lockRecord(int $id): MaintenanceRecord
    {
        return MaintenanceRecord::query()
            ->whereKey($id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function lockVehicle(int $id): Vehicle
    {
        return Vehicle::query()
            ->whereKey($id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function lockOperationalAsset(int $id): OperationalAsset
    {
        return OperationalAsset::query()
            ->whereKey($id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function lockSubject(
        MaintenanceRecord $record,
    ): Vehicle|OperationalAsset {
        if (
            $record->vehicle_id !== null
            && $record->operational_asset_id === null
        ) {
            return $this->lockVehicle($record->vehicle_id);
        }

        if (
            $record->vehicle_id === null
            && $record->operational_asset_id !== null
        ) {
            return $this->lockOperationalAsset(
                $record->operational_asset_id,
            );
        }

        throw new RuntimeException(
            'Subjek pemeliharaan tidak valid. Tepat satu subjek wajib tersedia.',
        );
    }

    private function lockLoan(int $id): VehicleLoan
    {
        return VehicleLoan::query()
            ->whereKey($id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function assertVehicleMayEnterMaintenance(Vehicle $vehicle): void
    {
        if (! $vehicle->is_active || $vehicle->status === VehicleStatus::Inactive) {
            throw ValidationException::withMessages([
                'vehicle_public_id' => 'Kendaraan nonaktif tidak dapat dibuatkan pemeliharaan operasional.',
            ]);
        }

        if (in_array($vehicle->status, [
            VehicleStatus::Reserved,
            VehicleStatus::Borrowed,
        ], true)) {
            throw ValidationException::withMessages([
                'vehicle_public_id' => 'Kendaraan sedang dipesan atau dipinjam dan belum dapat dibuatkan pemeliharaan.',
            ]);
        }
    }

    private function assertOperationalAssetMayEnterMaintenance(
        OperationalAsset $asset,
    ): void {
        if (! $asset->canEnterMaintenance()) {
            throw ValidationException::withMessages([
                'operational_asset_public_id' => 'Aset perangkat nonaktif tidak dapat dibuatkan pemeliharaan operasional.',
            ]);
        }
    }

    private function assertNoActiveMaintenance(Vehicle $vehicle): void
    {
        $existing = MaintenanceRecord::query()
            ->where('vehicle_id', $vehicle->getKey())
            ->whereIn(
                'status',
                array_map(
                    static fn (MaintenanceStatus $status): string => $status->value,
                    self::ACTIVE_STATUSES,
                ),
            )
            ->lockForUpdate()
            ->first();

        if ($existing === null) {
            return;
        }

        throw ValidationException::withMessages([
            'vehicle_public_id' => "Kendaraan sudah memiliki pemeliharaan aktif {$existing->maintenance_number}.",
        ]);
    }

    private function assertNoActiveOperationalAssetMaintenance(
        OperationalAsset $asset,
    ): void {
        $existing = MaintenanceRecord::query()
            ->where('operational_asset_id', $asset->getKey())
            ->whereIn(
                'status',
                array_map(
                    static fn (MaintenanceStatus $status): string => $status->value,
                    self::ACTIVE_STATUSES,
                ),
            )
            ->lockForUpdate()
            ->first();

        if ($existing === null) {
            return;
        }

        throw ValidationException::withMessages([
            'operational_asset_public_id' => "Aset perangkat sudah memiliki pemeliharaan aktif {$existing->maintenance_number}.",
        ]);
    }

    private function assertValidReturnIssueSource(
        Vehicle $vehicle,
        VehicleLoan $sourceLoan,
    ): void {
        if ($sourceLoan->vehicle_id !== $vehicle->getKey()) {
            throw ValidationException::withMessages([
                'source_vehicle_loan_public_id' => 'Sumber pengembalian tidak sesuai dengan kendaraan yang dipilih.',
            ]);
        }

        if ($sourceLoan->status !== VehicleLoanStatus::ReturnIssue) {
            throw ValidationException::withMessages([
                'source_vehicle_loan_public_id' => 'Hanya peminjaman berstatus Masalah Pengembalian yang dapat menjadi sumber pemeliharaan.',
            ]);
        }

        if ($vehicle->status !== VehicleStatus::Inspection) {
            throw ValidationException::withMessages([
                'vehicle_public_id' => 'Kendaraan dari masalah pengembalian harus masih berstatus Perlu Pemeriksaan.',
            ]);
        }
    }

    private function assertNoMaintenanceForSourceLoan(VehicleLoan $sourceLoan): void
    {
        $existing = MaintenanceRecord::query()
            ->where('source_vehicle_loan_id', $sourceLoan->getKey())
            ->lockForUpdate()
            ->first();

        if ($existing === null) {
            return;
        }

        throw ValidationException::withMessages([
            'source_vehicle_loan_public_id' => "Masalah pengembalian ini sudah terhubung dengan {$existing->maintenance_number}.",
        ]);
    }

    private function assertNoUnlinkedReturnIssue(Vehicle $vehicle): void
    {
        $returnIssue = VehicleLoan::query()
            ->where('vehicle_id', $vehicle->getKey())
            ->where('status', VehicleLoanStatus::ReturnIssue->value)
            ->lockForUpdate()
            ->first();

        if ($returnIssue === null) {
            return;
        }

        throw ValidationException::withMessages([
            'source_vehicle_loan_public_id' => 'Kendaraan memiliki masalah pengembalian aktif. Buat pemeliharaan dari transaksi pengembalian tersebut agar histori tetap tertaut.',
        ]);
    }

    /**
     * @param  list<MaintenanceStatus>  $allowed
     */
    private function requireStatus(
        MaintenanceRecord $record,
        array $allowed,
        string $message,
    ): void {
        if (in_array($record->status, $allowed, true)) {
            return;
        }

        throw ValidationException::withMessages([
            'maintenance' => $message,
        ]);
    }

    private function assertCompletionOutcome(MaintenanceStatus $outcome): void
    {
        if (in_array($outcome, [
            MaintenanceStatus::Completed,
            MaintenanceStatus::CompletedWithNotes,
            MaintenanceStatus::FurtherActionRequired,
            MaintenanceStatus::SeverelyDamaged,
            MaintenanceStatus::Unserviceable,
        ], true)) {
            return;
        }

        throw ValidationException::withMessages([
            'outcome_status' => 'Hasil pemeliharaan tidak valid.',
        ]);
    }

    private function vehicleStatusForOutcome(
        MaintenanceStatus $outcome,
        Vehicle $vehicle,
        MaintenanceRecord $record,
    ): VehicleStatus {
        return match ($outcome) {
            MaintenanceStatus::Completed,
            MaintenanceStatus::CompletedWithNotes => $this->statusAfterSuccessfulMaintenance(
                $vehicle,
                $record,
            ),
            MaintenanceStatus::FurtherActionRequired => VehicleStatus::Maintenance,
            MaintenanceStatus::SeverelyDamaged => VehicleStatus::Damaged,
            MaintenanceStatus::Unserviceable => VehicleStatus::Inactive,
            default => throw new RuntimeException('Status hasil pemeliharaan tidak didukung.'),
        };
    }

    private function operationalAssetStatusForOutcome(
        MaintenanceStatus $outcome,
        OperationalAsset $asset,
    ): OperationalAssetStatus {
        return match ($outcome) {
            MaintenanceStatus::Completed,
            MaintenanceStatus::CompletedWithNotes => $asset->is_active
                ? OperationalAssetStatus::Available
                : OperationalAssetStatus::Inactive,
            MaintenanceStatus::FurtherActionRequired => OperationalAssetStatus::Maintenance,
            MaintenanceStatus::SeverelyDamaged => OperationalAssetStatus::Damaged,
            MaintenanceStatus::Unserviceable => OperationalAssetStatus::Inactive,
            default => throw new RuntimeException('Status hasil pemeliharaan aset tidak didukung.'),
        };
    }

    private function statusAfterSuccessfulMaintenance(
        Vehicle $vehicle,
        MaintenanceRecord $record,
    ): VehicleStatus {
        if (! $vehicle->is_active) {
            return VehicleStatus::Inactive;
        }

        $futureReservation = VehicleLoan::query()
            ->where('vehicle_id', $vehicle->getKey())
            ->when(
                $record->source_vehicle_loan_id !== null,
                static fn ($query) => $query->where(
                    'id',
                    '!=',
                    $record->source_vehicle_loan_id,
                ),
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

    private function statusAfterCancellation(
        Vehicle $vehicle,
        MaintenanceRecord $record,
    ): VehicleStatus {
        if ($record->source_vehicle_loan_id !== null) {
            $source = VehicleLoan::query()
                ->whereKey($record->source_vehicle_loan_id)
                ->lockForUpdate()
                ->first();

            if ($source?->status === VehicleLoanStatus::ReturnIssue) {
                return VehicleStatus::Inspection;
            }
        }

        return match ($record->vehicle_status_before) {
            VehicleStatus::Available => $this->statusAfterSuccessfulMaintenance(
                $vehicle,
                $record,
            ),
            VehicleStatus::Damaged => VehicleStatus::Damaged,
            VehicleStatus::Maintenance => VehicleStatus::Inspection,
            VehicleStatus::Inactive => VehicleStatus::Inactive,
            default => VehicleStatus::Inspection,
        };
    }

    private function operationalAssetStatusAfterCancellation(
        OperationalAsset $asset,
        MaintenanceRecord $record,
    ): OperationalAssetStatus {
        if (! $asset->is_active) {
            return OperationalAssetStatus::Inactive;
        }

        return match ($record->operational_asset_status_before) {
            OperationalAssetStatus::Available => OperationalAssetStatus::Available,
            OperationalAssetStatus::Damaged => OperationalAssetStatus::Damaged,
            OperationalAssetStatus::Maintenance => OperationalAssetStatus::Inspection,
            OperationalAssetStatus::Inactive => OperationalAssetStatus::Inactive,
            default => OperationalAssetStatus::Inspection,
        };
    }

    private function resolveSourceReturnIssue(
        MaintenanceRecord $record,
        User $actor,
        ?Request $httpRequest,
    ): void {
        if ($record->source_vehicle_loan_id === null) {
            return;
        }

        $loan = $this->lockLoan($record->source_vehicle_loan_id);
        if ($loan->status !== VehicleLoanStatus::ReturnIssue) {
            return;
        }

        $previousStatus = $loan->status;
        $loan->forceFill([
            'status' => VehicleLoanStatus::Completed,
        ])->save();

        VehicleLoanStatusHistory::query()->create([
            'vehicle_loan_id' => $loan->getKey(),
            'previous_status' => $previousStatus,
            'new_status' => VehicleLoanStatus::Completed,
            'notes' => "Masalah pengembalian diselesaikan melalui {$record->maintenance_number}.",
            'changed_by' => $actor->getKey(),
            'changed_at' => now(),
        ]);

        $this->auditLogger->log(
            'vehicle_loan_return_issue_resolved',
            'vehicle_loan',
            $loan,
            ['status' => $previousStatus->value],
            [
                'status' => VehicleLoanStatus::Completed->value,
                'maintenance_record_id' => $record->getKey(),
                'maintenance_number' => $record->maintenance_number,
            ],
            $httpRequest,
            (int) $actor->getKey(),
        );
    }

    private function recordStatus(
        MaintenanceRecord $record,
        ?MaintenanceStatus $previousStatus,
        MaintenanceStatus $newStatus,
        ?string $notes,
        User $actor,
    ): void {
        MaintenanceStatusHistory::query()->create([
            'maintenance_record_id' => $record->getKey(),
            'previous_status' => $previousStatus,
            'new_status' => $newStatus,
            'notes' => $notes,
            'changed_by' => $actor->getKey(),
            'changed_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function auditTransition(
        MaintenanceRecord $record,
        MaintenanceStatus $previousStatus,
        MaintenanceStatus $newStatus,
        string $event,
        User $actor,
        ?Request $httpRequest,
        array $extra = [],
    ): void {
        $this->auditLogger->log(
            $event,
            'maintenance',
            $record,
            ['status' => $previousStatus->value],
            [
                'status' => $newStatus->value,
                ...$extra,
            ],
            $httpRequest,
            (int) $actor->getKey(),
        );
    }

    private function vehicleSnapshot(Vehicle $vehicle): string
    {
        return implode(' | ', array_filter([
            $vehicle->vehicle_code,
            $vehicle->license_plate,
            $vehicle->displayName(),
        ]));
    }

    private function operationalAssetSnapshot(
        OperationalAsset $asset,
    ): string {
        return implode(' | ', array_filter([
            $asset->asset_code,
            $asset->administrativeCode() !== $asset->asset_code
                ? $asset->administrativeCode()
                : null,
            $asset->displayName(),
            $asset->location,
        ]));
    }

    private function outcomeHistoryNote(
        MaintenanceStatus $outcome,
        MaintenanceSubjectType $subjectType,
    ): string {
        $subject = $subjectType === MaintenanceSubjectType::Vehicle
            ? 'kendaraan'
            : 'aset perangkat';

        return match ($outcome) {
            MaintenanceStatus::Completed => "Pemeliharaan selesai dan {$subject} dapat kembali digunakan.",
            MaintenanceStatus::CompletedWithNotes => "Pemeliharaan selesai dengan catatan dan {$subject} dapat kembali digunakan.",
            MaintenanceStatus::FurtherActionRequired => "Pemeliharaan memerlukan tindakan lanjutan dan {$subject} tetap dalam pemeliharaan.",
            MaintenanceStatus::SeverelyDamaged => "Hasil pemeliharaan menyatakan {$subject} rusak berat.",
            MaintenanceStatus::Unserviceable => "Hasil pemeliharaan menyatakan {$subject} tidak layak digunakan dan dinonaktifkan.",
            default => 'Status pemeliharaan diperbarui.',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function subjectAuditValues(
        MaintenanceRecord $record,
        Vehicle|OperationalAsset $subject,
    ): array {
        if ($subject instanceof Vehicle) {
            return [
                'subject_type' => MaintenanceSubjectType::Vehicle->value,
                'vehicle_id' => $record->vehicle_id,
                'vehicle_status' => $subject->status->value,
            ];
        }

        return [
            'subject_type' => MaintenanceSubjectType::OperationalAsset->value,
            'operational_asset_id' => $record->operational_asset_id,
            'operational_asset_status' => $subject->status->value,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>
     */
    private function storeReportFiles(
        string $publicId,
        array $data,
        User $actor,
    ): array {
        $stored = [];
        try {
            $before = $data['photo_before'] ?? null;
            if ($before instanceof UploadedFile) {
                $stored[] = $this->storeFile(
                    $before,
                    "maintenance-records/{$publicId}/reported",
                    AttachmentCategory::MaintenanceBefore,
                    $actor,
                    ['stage' => 'reported'],
                );
            }

            $document = $data['supporting_document'] ?? null;
            if ($document instanceof UploadedFile) {
                $stored[] = $this->storeFile(
                    $document,
                    "maintenance-records/{$publicId}/reported",
                    AttachmentCategory::Document,
                    $actor,
                    ['stage' => 'reported'],
                );
            }
        } catch (Throwable $exception) {
            $this->deleteStoredFiles($stored);

            throw $exception;
        }

        return $stored;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>
     */
    private function storeCompletionFiles(
        string $publicId,
        array $data,
        User $actor,
    ): array {
        $stored = [];
        try {
            $after = $data['photo_after'] ?? null;
            if ($after instanceof UploadedFile) {
                $stored[] = $this->storeFile(
                    $after,
                    "maintenance-records/{$publicId}/completion",
                    AttachmentCategory::MaintenanceAfter,
                    $actor,
                    ['stage' => 'completion'],
                );
            }

            $receipt = $data['receipt'] ?? null;
            if ($receipt instanceof UploadedFile) {
                $stored[] = $this->storeFile(
                    $receipt,
                    "maintenance-records/{$publicId}/completion",
                    AttachmentCategory::Receipt,
                    $actor,
                    ['stage' => 'completion'],
                );
            }
        } catch (Throwable $exception) {
            $this->deleteStoredFiles($stored);

            throw $exception;
        }

        return $stored;
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function storeFile(
        UploadedFile $file,
        string $directory,
        AttachmentCategory $category,
        User $actor,
        array $metadata,
    ): array {
        $diskName = (string) config('simantap.uploads.disk', 'local');
        $disk = Storage::disk($diskName);
        $extension = strtolower((string) (
            $file->guessExtension()
            ?: $file->getClientOriginalExtension()
            ?: 'bin'
        ));
        $storedName = Str::uuid().'.'.$extension;
        $path = $disk->putFileAs($directory, $file, $storedName);

        if (! is_string($path) || $path === '') {
            throw new RuntimeException('Bukti pemeliharaan tidak dapat disimpan.');
        }

        $checksum = hash_file('sha256', $file->getRealPath());
        if (! is_string($checksum)) {
            $disk->delete($path);
            throw new RuntimeException('Checksum bukti pemeliharaan tidak dapat dibuat.');
        }

        return [
            'category' => $category,
            'disk' => $diskName,
            'original_name' => $file->getClientOriginalName(),
            'stored_name' => $storedName,
            'path' => $path,
            'mime_type' => (string) ($file->getMimeType() ?: 'application/octet-stream'),
            'file_size' => (int) $file->getSize(),
            'checksum' => $checksum,
            'metadata' => [
                'source' => 'maintenance_workflow',
                ...$metadata,
            ],
            'uploaded_by' => (int) $actor->getKey(),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $storedFiles
     */
    private function createAttachmentRecords(
        MaintenanceRecord $record,
        array $storedFiles,
    ): void {
        foreach ($storedFiles as $file) {
            $record->attachments()->create([
                'file_category' => $file['category'],
                'disk' => $file['disk'],
                'original_name' => $file['original_name'],
                'stored_name' => $file['stored_name'],
                'file_path' => $file['path'],
                'mime_type' => $file['mime_type'],
                'file_size' => $file['file_size'],
                'checksum' => $file['checksum'],
                'metadata' => $file['metadata'],
                'uploaded_by' => $file['uploaded_by'],
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $storedFiles
     */
    private function deleteStoredFiles(array $storedFiles): void
    {
        foreach ($storedFiles as $file) {
            $disk = $file['disk'] ?? null;
            $path = $file['path'] ?? null;

            if (is_string($disk) && is_string($path) && $path !== '') {
                Storage::disk($disk)->delete($path);
            }
        }
    }

    private function loadRecord(MaintenanceRecord $record): MaintenanceRecord
    {
        return $record->fresh([
            'vehicle',
            'operationalAsset',
            'sourceVehicleLoan.borrower',
            'reporter',
            'handler',
            'approver',
            'canceller',
            'attachments.uploader',
            'statusHistories.changer',
        ]) ?? $record;
    }
}
