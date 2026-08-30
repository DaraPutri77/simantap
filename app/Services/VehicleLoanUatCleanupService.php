<?php

namespace App\Services;

use App\Enums\ConditionCheckType;
use App\Enums\DocumentType;
use App\Enums\MaintenanceStatus;
use App\Enums\OperationalAssetStatus;
use App\Enums\VehicleLoanStatus;
use App\Enums\VehicleStatus;
use App\Models\MaintenanceRecord;
use App\Models\VehicleConditionCheck;
use App\Models\VehicleLoan;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class VehicleLoanUatCleanupService
{
    private const CLEANUP_EVENT = 'uat_vehicle_loan_cleanup_completed';

    /**
     * @return array<string, mixed>
     */
    public function inspect(
        bool $keepOdometers = false,
        bool $includeAllMaintenance = false,
    ): array {
        $loans = DB::table('vehicle_loans')
            ->orderBy('id')
            ->get([
                'id',
                'public_id',
                'loan_number',
                'vehicle_id',
                'status',
            ]);
        $loanIds = $loans->pluck('id')->map(
            static fn (mixed $id): int => (int) $id,
        )->all();

        $conditionChecks = $loanIds === []
            ? collect()
            : DB::table('vehicle_condition_checks')
                ->whereIn('vehicle_loan_id', $loanIds)
                ->orderBy('checked_at')
                ->orderBy('id')
                ->get([
                    'id',
                    'vehicle_loan_id',
                    'check_type',
                    'odometer',
                    'checked_at',
                ]);
        $conditionCheckIds = $conditionChecks->pluck('id')->map(
            static fn (mixed $id): int => (int) $id,
        )->all();

        $linkedMaintenance = $loanIds === []
            ? collect()
            : DB::table('maintenance_records')
                ->whereIn('source_vehicle_loan_id', $loanIds)
                ->orderBy('id')
                ->get([
                    'id',
                    'public_id',
                    'maintenance_number',
                    'vehicle_id',
                    'operational_asset_id',
                    'source_vehicle_loan_id',
                    'status',
                    'vehicle_status_before',
                    'operational_asset_status_before',
                ]);
        $linkedMaintenanceIds = $linkedMaintenance->pluck('id')->map(
            static fn (mixed $id): int => (int) $id,
        )->all();

        $standaloneMaintenance = $includeAllMaintenance
            ? DB::table('maintenance_records')
                ->when(
                    $linkedMaintenanceIds !== [],
                    static fn (Builder $query): Builder => $query
                        ->whereNotIn('id', $linkedMaintenanceIds),
                )
                ->orderBy('id')
                ->get([
                    'id',
                    'public_id',
                    'maintenance_number',
                    'vehicle_id',
                    'operational_asset_id',
                    'source_vehicle_loan_id',
                    'status',
                    'vehicle_status_before',
                    'operational_asset_status_before',
                ])
            : collect();
        $maintenanceRecords = $linkedMaintenance
            ->concat($standaloneMaintenance)
            ->sortBy('id')
            ->values();
        $maintenanceIds = $maintenanceRecords->pluck('id')->map(
            static fn (mixed $id): int => (int) $id,
        )->all();

        $contexts = $this->morphContexts(
            $loanIds,
            $conditionCheckIds,
            $maintenanceIds,
        );
        $attachments = $this->morphQuery(
            'attachments',
            'attachable_type',
            'attachable_id',
            $contexts,
        )
            ->orderBy('id')
            ->get(['id', 'disk', 'file_path']);
        $signatures = $this->morphQuery(
            'digital_signatures',
            'signable_type',
            'signable_id',
            $this->loanMorphContexts($loanIds),
        )
            ->orderBy('id')
            ->get(['id', 'image_path']);
        $verifications = $this->morphQuery(
            'document_verifications',
            'verifiable_type',
            'verifiable_id',
            $contexts,
        )
            ->orderBy('id')
            ->get(['id']);
        $auditLogs = $this->morphQuery(
            'audit_logs',
            'auditable_type',
            'auditable_id',
            $contexts,
        )
            ->orderBy('id')
            ->get(['id']);
        $notificationIds = $this->notificationIds(
            $loanIds,
            $maintenanceIds,
        );

        $files = $this->filePlan($attachments, $signatures);
        $vehicleAdjustments = $this->vehicleAdjustmentPlan(
            $loans,
            $conditionChecks,
            $maintenanceRecords,
            $keepOdometers,
        );
        $operationalAssetAdjustments = $this
            ->operationalAssetAdjustmentPlan($maintenanceRecords);

        return [
            'generated_at' => now()->toISOString(),
            'keep_odometers' => $keepOdometers,
            'include_all_maintenance' => $includeAllMaintenance,
            'loans' => [
                'count' => count($loanIds),
                'ids' => $loanIds,
                'items' => $loans->map(static fn (object $loan): array => [
                    'id' => (int) $loan->id,
                    'public_id' => (string) $loan->public_id,
                    'loan_number' => (string) $loan->loan_number,
                    'vehicle_id' => (int) $loan->vehicle_id,
                    'status' => (string) $loan->status,
                ])->all(),
            ],
            'vehicle_loan_status_histories' => [
                'count' => $this->countWhereIn(
                    'vehicle_loan_status_histories',
                    'vehicle_loan_id',
                    $loanIds,
                ),
            ],
            'condition_checks' => [
                'count' => count($conditionCheckIds),
                'ids' => $conditionCheckIds,
            ],
            'linked_maintenance' => [
                'count' => count($linkedMaintenanceIds),
                'ids' => $linkedMaintenanceIds,
                'items' => $linkedMaintenance->map(
                    static fn (object $record): array => [
                        'id' => (int) $record->id,
                        'public_id' => (string) $record->public_id,
                        'maintenance_number' => (string) $record->maintenance_number,
                        'vehicle_id' => (int) $record->vehicle_id,
                        'operational_asset_id' => $record->operational_asset_id === null
                            ? null
                            : (int) $record->operational_asset_id,
                        'source_vehicle_loan_id' => (int) $record->source_vehicle_loan_id,
                        'status' => (string) $record->status,
                    ],
                )->all(),
            ],
            'standalone_maintenance' => [
                'count' => $standaloneMaintenance->count(),
                'ids' => $standaloneMaintenance->pluck('id')->map(
                    static fn (mixed $id): int => (int) $id,
                )->all(),
            ],
            'maintenance_records' => [
                'count' => $maintenanceRecords->count(),
                'ids' => $maintenanceIds,
                'items' => $maintenanceRecords->map(
                    static fn (object $record): array => [
                        'id' => (int) $record->id,
                        'maintenance_number' => (string) $record->maintenance_number,
                        'vehicle_id' => $record->vehicle_id === null
                            ? null
                            : (int) $record->vehicle_id,
                        'operational_asset_id' => $record->operational_asset_id === null
                            ? null
                            : (int) $record->operational_asset_id,
                        'source_vehicle_loan_id' => $record->source_vehicle_loan_id === null
                            ? null
                            : (int) $record->source_vehicle_loan_id,
                        'status' => (string) $record->status,
                    ],
                )->all(),
            ],
            'maintenance_status_histories' => [
                'count' => $this->countWhereIn(
                    'maintenance_status_histories',
                    'maintenance_record_id',
                    $maintenanceIds,
                ),
            ],
            'attachments' => [
                'count' => $attachments->count(),
                'ids' => $attachments->pluck('id')->map(
                    static fn (mixed $id): int => (int) $id,
                )->all(),
            ],
            'digital_signatures' => [
                'count' => $signatures->count(),
                'ids' => $signatures->pluck('id')->map(
                    static fn (mixed $id): int => (int) $id,
                )->all(),
            ],
            'document_verifications' => [
                'count' => $verifications->count(),
                'ids' => $verifications->pluck('id')->map(
                    static fn (mixed $id): int => (int) $id,
                )->all(),
            ],
            'audit_logs' => [
                'count' => $auditLogs->count(),
                'ids' => $auditLogs->pluck('id')->map(
                    static fn (mixed $id): int => (int) $id,
                )->all(),
            ],
            'notifications' => [
                'count' => count($notificationIds),
                'ids' => $notificationIds,
            ],
            'document_sequences' => [
                'count' => DB::table('document_sequences')
                    ->whereIn(
                        'document_type',
                        $this->documentTypes($includeAllMaintenance),
                    )
                    ->count(),
                'types' => $this->documentTypes($includeAllMaintenance),
            ],
            'files' => [
                'count' => count($files),
                'items' => $files,
            ],
            'vehicle_adjustments' => $vehicleAdjustments,
            'operational_asset_adjustments' => $operationalAssetAdjustments,
            'cleanup_already_executed' => DB::table('audit_logs')
                ->where('event', self::CLEANUP_EVENT)
                ->exists(),
        ];
    }

    /**
     * @param  array{
     *     database: array{path: string, size: int, sha256: string},
     *     private_files: array{path: string, size: int, sha256: string}
     * }  $backupReference
     * @return array<string, mixed>
     */
    public function cleanup(
        array $backupReference,
        bool $keepOdometers = false,
        bool $includeAllMaintenance = false,
    ): array {
        if (
            DB::table('audit_logs')
                ->where('event', self::CLEANUP_EVENT)
                ->exists()
        ) {
            throw new RuntimeException(
                'Pembersihan UAT pernah diselesaikan dan tidak dapat dijalankan ulang.',
            );
        }

        $plan = $this->inspect($keepOdometers, $includeAllMaintenance);
        $manifestPath = sprintf(
            'cleanup-manifests/%s-vehicle-loan-uat.json',
            now()->format('Ymd-His').'-'.Str::lower(Str::random(8)),
        );

        $this->writeManifest($manifestPath, [
            'status' => 'planned',
            'backup_reference' => $backupReference,
            'plan' => $plan,
        ]);

        if (
            $plan['loans']['count'] === 0
            && $plan['maintenance_records']['count'] === 0
        ) {
            $result = [
                ...$plan,
                'execution' => [
                    'status' => 'nothing_to_delete',
                    'backup_reference' => $backupReference,
                    'manifest_path' => $manifestPath,
                    'deleted_files' => 0,
                    'failed_files' => [],
                ],
            ];
            $this->writeManifest($manifestPath, $result);

            return $result;
        }

        try {
            $deleted = DB::transaction(
                fn (): array => $this->deleteDatabaseRows(
                    $plan,
                    $backupReference,
                    $manifestPath,
                ),
                3,
            );
        } catch (Throwable $exception) {
            $this->writeManifest($manifestPath, [
                'status' => 'database_failed',
                'backup_reference' => $backupReference,
                'error' => $exception->getMessage(),
                'plan' => $plan,
            ]);

            throw $exception;
        }

        $fileResult = $this->deleteFiles($plan['files']['items']);
        $result = [
            ...$plan,
            'execution' => [
                'status' => $fileResult['failed'] === []
                    ? 'completed'
                    : 'completed_with_file_errors',
                'backup_reference' => $backupReference,
                'manifest_path' => $manifestPath,
                'deleted_rows' => $deleted,
                'deleted_files' => $fileResult['deleted'],
                'failed_files' => $fileResult['failed'],
            ],
        ];
        $this->writeManifest($manifestPath, $result);

        return $result;
    }

    /**
     * @param  array<string, mixed>  $plan
     * @param  array{
     *     database: array{path: string, size: int, sha256: string},
     *     private_files: array{path: string, size: int, sha256: string}
     * }  $backupReference
     * @return array<string, int>
     */
    private function deleteDatabaseRows(
        array $plan,
        array $backupReference,
        string $manifestPath,
    ): array {
        $loanIds = $plan['loans']['ids'];
        $conditionCheckIds = $plan['condition_checks']['ids'];
        $maintenanceIds = $plan['maintenance_records']['ids'];
        $now = now();

        $deleted = [
            'notifications' => $this->deleteWhereIn(
                'notifications',
                'id',
                $plan['notifications']['ids'],
            ),
            'document_verifications' => $this->deleteWhereIn(
                'document_verifications',
                'id',
                $plan['document_verifications']['ids'],
            ),
            'audit_logs' => $this->deleteWhereIn(
                'audit_logs',
                'id',
                $plan['audit_logs']['ids'],
            ),
            'attachments' => $this->deleteWhereIn(
                'attachments',
                'id',
                $plan['attachments']['ids'],
            ),
            'digital_signatures' => $this->deleteWhereIn(
                'digital_signatures',
                'id',
                $plan['digital_signatures']['ids'],
            ),
            'maintenance_status_histories' => $this->deleteWhereIn(
                'maintenance_status_histories',
                'maintenance_record_id',
                $maintenanceIds,
            ),
            'maintenance_records' => $this->deleteWhereIn(
                'maintenance_records',
                'id',
                $maintenanceIds,
            ),
            'vehicle_loan_status_histories' => $this->deleteWhereIn(
                'vehicle_loan_status_histories',
                'vehicle_loan_id',
                $loanIds,
            ),
            'condition_checks' => $this->deleteWhereIn(
                'vehicle_condition_checks',
                'id',
                $conditionCheckIds,
            ),
            'vehicle_loans' => $this->deleteWhereIn(
                'vehicle_loans',
                'id',
                $loanIds,
            ),
            'document_sequences' => DB::table('document_sequences')
                ->whereIn('document_type', $plan['document_sequences']['types'])
                ->delete(),
            'vehicles_updated' => 0,
            'operational_assets_updated' => 0,
        ];

        foreach ($plan['vehicle_adjustments'] as $adjustment) {
            $changes = [];

            if ($adjustment['current_status'] !== $adjustment['target_status']) {
                $changes['status'] = $adjustment['target_status'];
            }

            if ($adjustment['current_is_active'] !== $adjustment['target_is_active']) {
                $changes['is_active'] = $adjustment['target_is_active'];
            }

            if ($adjustment['current_odometer'] !== $adjustment['target_odometer']) {
                $changes['current_odometer'] = $adjustment['target_odometer'];
            }

            if ($changes === []) {
                continue;
            }

            $changes['updated_at'] = $now;
            $deleted['vehicles_updated'] += DB::table('vehicles')
                ->where('id', $adjustment['vehicle_id'])
                ->update($changes);
        }

        foreach ($plan['operational_asset_adjustments'] as $adjustment) {
            $changes = [];

            if ($adjustment['current_status'] !== $adjustment['target_status']) {
                $changes['status'] = $adjustment['target_status'];
            }

            if ($adjustment['current_is_active'] !== $adjustment['target_is_active']) {
                $changes['is_active'] = $adjustment['target_is_active'];
            }

            if ($changes === []) {
                continue;
            }

            $changes['updated_at'] = $now;
            $deleted['operational_assets_updated'] += DB::table(
                'operational_assets',
            )
                ->where('id', $adjustment['operational_asset_id'])
                ->update($changes);
        }

        DB::table('audit_logs')->insert([
            'request_id' => (string) Str::uuid(),
            'actor_id' => null,
            'event' => self::CLEANUP_EVENT,
            'module' => 'system',
            'auditable_type' => null,
            'auditable_id' => null,
            'old_values' => null,
            'new_values' => json_encode([
                'vehicle_loans_deleted' => $deleted['vehicle_loans'],
                'maintenance_records_deleted' => $deleted['maintenance_records'],
                'vehicles_updated' => $deleted['vehicles_updated'],
                'operational_assets_updated' => $deleted['operational_assets_updated'],
                'include_all_maintenance' => $plan['include_all_maintenance'],
                'backup_reference' => $backupReference,
                'manifest_path' => $manifestPath,
            ], JSON_THROW_ON_ERROR),
            'ip_address' => null,
            'user_agent' => 'SIMANTAP CLI go-live cleanup',
            'url' => null,
            'http_method' => 'CLI',
            'created_at' => $now,
        ]);

        return $deleted;
    }

    /**
     * @param  iterable<int, object>  $attachments
     * @param  iterable<int, object>  $signatures
     * @return list<array{disk: string, path: string, source: string}>
     */
    private function filePlan(iterable $attachments, iterable $signatures): array
    {
        $files = [];

        foreach ($attachments as $attachment) {
            $this->appendFile(
                $files,
                (string) $attachment->disk,
                (string) $attachment->file_path,
                'attachment',
            );
        }

        $signatureDisk = (string) config(
            'simantap.uploads.disk',
            'local',
        );
        foreach ($signatures as $signature) {
            $this->appendFile(
                $files,
                $signatureDisk,
                (string) $signature->image_path,
                'digital_signature',
            );
        }

        return array_values($files);
    }

    /**
     * @param  array<string, array{disk: string, path: string, source: string}>  $files
     */
    private function appendFile(
        array &$files,
        string $disk,
        string $path,
        string $source,
    ): void {
        $disk = trim($disk);
        $path = trim($path);

        if ($disk === '' || $path === '') {
            return;
        }

        $files[$disk.'|'.$path] = [
            'disk' => $disk,
            'path' => $path,
            'source' => $source,
        ];
    }

    /**
     * @param  list<array{disk: string, path: string, source: string}>  $files
     * @return array{deleted: int, failed: list<array{disk: string, path: string, error: string}>}
     */
    private function deleteFiles(array $files): array
    {
        $deleted = 0;
        $failed = [];

        foreach ($files as $file) {
            try {
                $disk = Storage::disk($file['disk']);

                if (! $disk->exists($file['path'])) {
                    continue;
                }

                if (! $disk->delete($file['path'])) {
                    throw new RuntimeException('Filesystem menolak penghapusan.');
                }

                $deleted++;
            } catch (Throwable $exception) {
                $failed[] = [
                    'disk' => $file['disk'],
                    'path' => $file['path'],
                    'error' => $exception->getMessage(),
                ];
            }
        }

        return [
            'deleted' => $deleted,
            'failed' => $failed,
        ];
    }

    /**
     * @param  iterable<int, object>  $loans
     * @param  iterable<int, object>  $conditionChecks
     * @param  iterable<int, object>  $maintenanceRecords
     * @return list<array<string, mixed>>
     */
    private function vehicleAdjustmentPlan(
        iterable $loans,
        iterable $conditionChecks,
        iterable $maintenanceRecords,
        bool $keepOdometers,
    ): array {
        $loansByVehicle = collect($loans)->groupBy(
            static fn (object $loan): int => (int) $loan->vehicle_id,
        );
        $selectedMaintenance = collect($maintenanceRecords);
        $maintenanceByVehicle = $selectedMaintenance
            ->filter(
                static fn (object $record): bool => $record->vehicle_id !== null,
            )
            ->groupBy(
                static fn (object $record): int => (int) $record->vehicle_id,
            );
        $vehicleIds = $loansByVehicle->keys()
            ->merge($maintenanceByVehicle->keys())
            ->map(static fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($vehicleIds === []) {
            return [];
        }

        $loanVehicleIds = collect($loans)->mapWithKeys(
            static fn (object $loan): array => [
                (int) $loan->id => (int) $loan->vehicle_id,
            ],
        );
        $checkoutBaselines = [];
        foreach ($conditionChecks as $check) {
            if ((string) $check->check_type !== ConditionCheckType::Checkout->value) {
                continue;
            }

            $vehicleId = $loanVehicleIds->get((int) $check->vehicle_loan_id);
            if ($vehicleId === null || isset($checkoutBaselines[$vehicleId])) {
                continue;
            }

            $checkoutBaselines[$vehicleId] = (string) $check->odometer;
        }

        $preservedVehicleIds = DB::table('maintenance_records')
            ->whereIn('vehicle_id', $vehicleIds)
            ->whereNull('deleted_at')
            ->whereNotIn(
                'id',
                $selectedMaintenance->pluck('id')->all(),
            )
            ->whereIn('status', [
                MaintenanceStatus::Reported->value,
                MaintenanceStatus::Approved->value,
                MaintenanceStatus::InProgress->value,
                MaintenanceStatus::FurtherActionRequired->value,
                MaintenanceStatus::SeverelyDamaged->value,
                MaintenanceStatus::Unserviceable->value,
            ])
            ->pluck('vehicle_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->flip();
        $activeLoanStatuses = [
            VehicleLoanStatus::Approved->value,
            VehicleLoanStatus::ReadyForPickup->value,
            VehicleLoanStatus::Borrowed->value,
            VehicleLoanStatus::AwaitingReturnInspection->value,
            VehicleLoanStatus::ReturnIssue->value,
        ];

        return DB::table('vehicles')
            ->whereIn('id', $vehicleIds)
            ->orderBy('vehicle_code')
            ->get([
                'id',
                'vehicle_code',
                'license_plate',
                'current_odometer',
                'status',
                'is_active',
                'deleted_at',
            ])
            ->map(function (object $vehicle) use (
                $loansByVehicle,
                $maintenanceByVehicle,
                $checkoutBaselines,
                $preservedVehicleIds,
                $activeLoanStatuses,
                $keepOdometers,
            ): array {
                $vehicleId = (int) $vehicle->id;
                $vehicleLoans = $loansByVehicle->get($vehicleId, collect());
                $selectedRecords = $maintenanceByVehicle->get(
                    $vehicleId,
                    collect(),
                );
                $hasStatusImpact = in_array(
                    (string) $vehicle->status,
                    [
                        VehicleStatus::Reserved->value,
                        VehicleStatus::Borrowed->value,
                    ],
                    true,
                ) || $vehicleLoans->contains(
                    static fn (object $loan): bool => in_array(
                        (string) $loan->status,
                        $activeLoanStatuses,
                        true,
                    ),
                ) || $selectedRecords->isNotEmpty();
                $hasRemainingMaintenance = $preservedVehicleIds->has(
                    $vehicleId,
                );
                $wasDisabledBySelectedMaintenance = $selectedRecords->contains(
                    static fn (object $record): bool => (string) $record->status
                        === MaintenanceStatus::Unserviceable->value,
                );
                $statusBeforeMaintenance = $selectedRecords
                    ->first(
                        static fn (object $record): bool => filled(
                            $record->vehicle_status_before,
                        ),
                    )?->vehicle_status_before;
                $canRestore = $vehicle->deleted_at === null
                    && ! $hasRemainingMaintenance;
                $targetActive = (bool) $vehicle->is_active;
                $targetStatus = (string) $vehicle->status;

                if ($canRestore && $wasDisabledBySelectedMaintenance) {
                    $targetActive = true;
                }

                if ($canRestore && $hasStatusImpact) {
                    if ($vehicleLoans->isNotEmpty()) {
                        $targetStatus = $targetActive
                            ? VehicleStatus::Available->value
                            : VehicleStatus::Inactive->value;
                    } elseif ($statusBeforeMaintenance !== null) {
                        $targetStatus = (string) $statusBeforeMaintenance;
                    } else {
                        $targetStatus = $targetActive
                            ? VehicleStatus::Available->value
                            : VehicleStatus::Inactive->value;
                    }
                }

                $currentOdometer = (string) $vehicle->current_odometer;
                $targetOdometer = $currentOdometer;
                if (
                    $canRestore
                    && ! $keepOdometers
                    && isset($checkoutBaselines[$vehicleId])
                ) {
                    $targetOdometer = $checkoutBaselines[$vehicleId];
                }

                return [
                    'vehicle_id' => $vehicleId,
                    'vehicle_code' => (string) $vehicle->vehicle_code,
                    'license_plate' => (string) $vehicle->license_plate,
                    'current_status' => (string) $vehicle->status,
                    'target_status' => $targetStatus,
                    'current_is_active' => (bool) $vehicle->is_active,
                    'target_is_active' => $targetActive,
                    'current_odometer' => $currentOdometer,
                    'target_odometer' => $targetOdometer,
                    'preserved_for_manual_maintenance' => $hasRemainingMaintenance,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  iterable<int, object>  $maintenanceRecords
     * @return list<array<string, mixed>>
     */
    private function operationalAssetAdjustmentPlan(
        iterable $maintenanceRecords,
    ): array {
        $selectedMaintenance = collect($maintenanceRecords);
        $maintenanceByAsset = $selectedMaintenance
            ->filter(
                static fn (object $record): bool => $record->operational_asset_id
                    !== null,
            )
            ->groupBy(
                static fn (object $record): int => (int) $record
                    ->operational_asset_id,
            );
        $assetIds = $maintenanceByAsset->keys()
            ->map(static fn (mixed $id): int => (int) $id)
            ->values()
            ->all();

        if ($assetIds === []) {
            return [];
        }

        $preservedAssetIds = DB::table('maintenance_records')
            ->whereIn('operational_asset_id', $assetIds)
            ->whereNull('deleted_at')
            ->whereNotIn('id', $selectedMaintenance->pluck('id')->all())
            ->whereIn('status', [
                MaintenanceStatus::Reported->value,
                MaintenanceStatus::Approved->value,
                MaintenanceStatus::InProgress->value,
                MaintenanceStatus::FurtherActionRequired->value,
                MaintenanceStatus::SeverelyDamaged->value,
                MaintenanceStatus::Unserviceable->value,
            ])
            ->pluck('operational_asset_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->flip();

        return DB::table('operational_assets')
            ->whereIn('id', $assetIds)
            ->orderBy('asset_code')
            ->get([
                'id',
                'asset_code',
                'status',
                'is_active',
                'deleted_at',
            ])
            ->map(function (object $asset) use (
                $maintenanceByAsset,
                $preservedAssetIds,
            ): array {
                $assetId = (int) $asset->id;
                $selectedRecords = $maintenanceByAsset->get(
                    $assetId,
                    collect(),
                );
                $hasRemainingMaintenance = $preservedAssetIds->has($assetId);
                $wasDisabledBySelectedMaintenance = $selectedRecords->contains(
                    static fn (object $record): bool => (string) $record->status
                        === MaintenanceStatus::Unserviceable->value,
                );
                $statusBeforeMaintenance = $selectedRecords
                    ->first(
                        static fn (object $record): bool => filled(
                            $record->operational_asset_status_before,
                        ),
                    )?->operational_asset_status_before;
                $canRestore = $asset->deleted_at === null
                    && ! $hasRemainingMaintenance;
                $targetActive = (bool) $asset->is_active;
                $targetStatus = (string) $asset->status;

                if ($canRestore && $wasDisabledBySelectedMaintenance) {
                    $targetActive = true;
                }

                if ($canRestore && $selectedRecords->isNotEmpty()) {
                    $targetStatus = $statusBeforeMaintenance === null
                        ? ($targetActive
                            ? OperationalAssetStatus::Available->value
                            : OperationalAssetStatus::Inactive->value)
                        : (string) $statusBeforeMaintenance;
                }

                return [
                    'operational_asset_id' => $assetId,
                    'asset_code' => (string) $asset->asset_code,
                    'current_status' => (string) $asset->status,
                    'target_status' => $targetStatus,
                    'current_is_active' => (bool) $asset->is_active,
                    'target_is_active' => $targetActive,
                    'preserved_for_remaining_maintenance' => $hasRemainingMaintenance,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function documentTypes(bool $includeAllMaintenance): array
    {
        $types = [DocumentType::VehicleLoan->value, 'vehicle_loan'];

        if ($includeAllMaintenance) {
            $types[] = DocumentType::Maintenance->value;
            $types[] = 'maintenance_record';
        }

        return $types;
    }

    /**
     * @param  list<int>  $loanIds
     * @param  list<int>  $maintenanceIds
     * @return list<string>
     */
    private function notificationIds(
        array $loanIds,
        array $maintenanceIds,
    ): array {
        if ($loanIds === [] && $maintenanceIds === []) {
            return [];
        }

        $lookup = [
            VehicleLoan::class => array_fill_keys(
                array_map('strval', $loanIds),
                true,
            ),
            'vehicle_loan' => array_fill_keys(
                array_map('strval', $loanIds),
                true,
            ),
            MaintenanceRecord::class => array_fill_keys(
                array_map('strval', $maintenanceIds),
                true,
            ),
            'maintenance_record' => array_fill_keys(
                array_map('strval', $maintenanceIds),
                true,
            ),
        ];

        return DB::table('notifications')
            ->orderBy('created_at')
            ->get(['id', 'data'])
            ->filter(static function (object $notification) use ($lookup): bool {
                $payload = json_decode((string) $notification->data, true);
                if (! is_array($payload)) {
                    return false;
                }

                $type = (string) ($payload['resource_type'] ?? '');
                $id = (string) ($payload['resource_id'] ?? '');

                return isset($lookup[$type][$id]);
            })
            ->pluck('id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();
    }

    /**
     * @param  list<int>  $loanIds
     * @param  list<int>  $conditionCheckIds
     * @param  list<int>  $maintenanceIds
     * @return list<array{types: list<string>, ids: list<int>}>
     */
    private function morphContexts(
        array $loanIds,
        array $conditionCheckIds,
        array $maintenanceIds,
    ): array {
        return [
            ...$this->loanMorphContexts($loanIds),
            [
                'types' => [
                    'vehicle_condition_check',
                    VehicleConditionCheck::class,
                ],
                'ids' => $conditionCheckIds,
            ],
            [
                'types' => ['maintenance_record', MaintenanceRecord::class],
                'ids' => $maintenanceIds,
            ],
        ];
    }

    /**
     * @param  list<int>  $loanIds
     * @return list<array{types: list<string>, ids: list<int>}>
     */
    private function loanMorphContexts(array $loanIds): array
    {
        return [[
            'types' => ['vehicle_loan', VehicleLoan::class],
            'ids' => $loanIds,
        ]];
    }

    /**
     * @param  list<array{types: list<string>, ids: list<int>}>  $contexts
     */
    private function morphQuery(
        string $table,
        string $typeColumn,
        string $idColumn,
        array $contexts,
    ): Builder {
        return DB::table($table)->where(
            function (Builder $query) use (
                $typeColumn,
                $idColumn,
                $contexts,
            ): void {
                $hasContext = false;

                foreach ($contexts as $context) {
                    if ($context['ids'] === []) {
                        continue;
                    }

                    $hasContext = true;
                    $query->orWhere(
                        function (Builder $contextQuery) use (
                            $typeColumn,
                            $idColumn,
                            $context,
                        ): void {
                            $contextQuery
                                ->whereIn($typeColumn, $context['types'])
                                ->whereIn($idColumn, $context['ids']);
                        },
                    );
                }

                if (! $hasContext) {
                    $query->whereRaw('1 = 0');
                }
            },
        );
    }

    /**
     * @param  list<int|string>  $ids
     */
    private function countWhereIn(
        string $table,
        string $column,
        array $ids,
    ): int {
        if ($ids === []) {
            return 0;
        }

        return DB::table($table)->whereIn($column, $ids)->count();
    }

    /**
     * @param  list<int|string>  $ids
     */
    private function deleteWhereIn(
        string $table,
        string $column,
        array $ids,
    ): int {
        if ($ids === []) {
            return 0;
        }

        return DB::table($table)->whereIn($column, $ids)->delete();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function writeManifest(string $path, array $payload): void
    {
        $written = Storage::disk('local')->put(
            $path,
            json_encode(
                $payload,
                JSON_PRETTY_PRINT
                    | JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                    | JSON_THROW_ON_ERROR,
            ),
        );

        if (! $written) {
            throw new RuntimeException(
                'Manifest pembersihan tidak dapat disimpan pada disk private.',
            );
        }
    }
}
