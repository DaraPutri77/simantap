<?php

namespace App\Services;

use App\Enums\MaintenanceStatus;
use App\Enums\OperationalAssetStatus;
use App\Models\OperationalAsset;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OperationalAssetService
{
    /**
     * @var list<MaintenanceStatus>
     */
    private const ACTIVE_MAINTENANCE_STATUSES = [
        MaintenanceStatus::Reported,
        MaintenanceStatus::Approved,
        MaintenanceStatus::InProgress,
        MaintenanceStatus::FurtherActionRequired,
    ];

    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(
        array $data,
        User $actor,
        ?Request $request = null,
    ): OperationalAsset {
        return DB::transaction(function () use (
            $data,
            $actor,
            $request,
        ): OperationalAsset {
            $isActive = (bool) $data['is_active'];
            $asset = OperationalAsset::query()->create([
                ...$this->attributes($data),
                'status' => $isActive
                    ? OperationalAssetStatus::from((string) $data['status'])
                    : OperationalAssetStatus::Inactive,
                'is_active' => $isActive,
            ]);

            $this->auditLogger->log(
                event: 'operational_asset_created',
                module: 'operational_asset',
                auditable: $asset,
                newValues: $this->snapshot($asset),
                request: $request,
                actorId: $actor->getKey(),
            );

            return $asset;
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(
        OperationalAsset $asset,
        array $data,
        User $actor,
        ?Request $request = null,
    ): OperationalAsset {
        return DB::transaction(function () use (
            $asset,
            $data,
            $actor,
            $request,
        ): OperationalAsset {
            $locked = $this->lock($asset->getKey());
            $requestedStatus = OperationalAssetStatus::from(
                (string) $data['status'],
            );

            if (
                in_array($locked->status, [
                    OperationalAssetStatus::Maintenance,
                    OperationalAssetStatus::Inactive,
                ], true)
                && $requestedStatus !== $locked->status
            ) {
                throw ValidationException::withMessages([
                    'status' => 'Status aset ini dikendalikan oleh alur pemeliharaan atau aktivasi master.',
                ]);
            }

            $oldValues = $this->snapshot($locked);
            $locked->fill([
                ...$this->attributes($data),
                'status' => $locked->is_active
                    ? $requestedStatus
                    : OperationalAssetStatus::Inactive,
            ])->save();

            $newValues = $this->snapshot($locked);
            $changes = $this->changes($oldValues, $newValues);

            if ($changes['new'] !== []) {
                $this->auditLogger->log(
                    event: 'operational_asset_updated',
                    module: 'operational_asset',
                    auditable: $locked,
                    oldValues: $changes['old'],
                    newValues: $changes['new'],
                    request: $request,
                    actorId: $actor->getKey(),
                );
            }

            return $locked;
        }, 3);
    }

    public function setActive(
        OperationalAsset $asset,
        bool $isActive,
        User $actor,
        ?Request $request = null,
    ): OperationalAsset {
        return DB::transaction(function () use (
            $asset,
            $isActive,
            $actor,
            $request,
        ): OperationalAsset {
            $locked = $this->lock($asset->getKey());

            if (! $isActive && $this->hasActiveMaintenance($locked)) {
                throw ValidationException::withMessages([
                    'operational_asset' => 'Aset yang memiliki tiket pemeliharaan aktif tidak dapat dinonaktifkan.',
                ]);
            }

            if ($locked->is_active === $isActive) {
                return $locked;
            }

            $oldValues = [
                'is_active' => $locked->is_active,
                'status' => $locked->status->value,
            ];
            $locked->forceFill([
                'is_active' => $isActive,
                'status' => $isActive
                    ? OperationalAssetStatus::Available
                    : OperationalAssetStatus::Inactive,
            ])->save();

            $this->auditLogger->log(
                event: $isActive
                    ? 'operational_asset_activated'
                    : 'operational_asset_deactivated',
                module: 'operational_asset',
                auditable: $locked,
                oldValues: $oldValues,
                newValues: [
                    'is_active' => $locked->is_active,
                    'status' => $locked->status->value,
                ],
                request: $request,
                actorId: $actor->getKey(),
            );

            return $locked;
        }, 3);
    }

    private function lock(int $id): OperationalAsset
    {
        return OperationalAsset::query()
            ->whereKey($id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function hasActiveMaintenance(OperationalAsset $asset): bool
    {
        return $asset->maintenanceRecords()
            ->whereIn(
                'status',
                array_map(
                    static fn (MaintenanceStatus $status): string => $status->value,
                    self::ACTIVE_MAINTENANCE_STATUSES,
                ),
            )
            ->lockForUpdate()
            ->first() !== null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return [
            'asset_code' => $data['asset_code'],
            'bmn_code' => $data['bmn_code'] ?? null,
            'nup' => $data['nup'] ?? null,
            'register_code' => $data['register_code'] ?? null,
            'type' => $data['type'],
            'brand' => $data['brand'],
            'model' => $data['model'] ?? null,
            'serial_number' => $data['serial_number'] ?? null,
            'acquisition_year' => $data['acquisition_year'] ?? null,
            'location' => $data['location'] ?? null,
            'responsible_person' => $data['responsible_person'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(OperationalAsset $asset): array
    {
        return [
            'asset_code' => $asset->asset_code,
            'bmn_code' => $asset->bmn_code,
            'nup' => $asset->nup,
            'register_code' => $asset->register_code,
            'type' => $asset->type->value,
            'brand' => $asset->brand,
            'model' => $asset->model,
            'serial_number' => $asset->serial_number,
            'acquisition_year' => $asset->acquisition_year,
            'location' => $asset->location,
            'responsible_person' => $asset->responsible_person,
            'status' => $asset->status->value,
            'notes' => $asset->notes,
            'is_active' => $asset->is_active,
        ];
    }

    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     * @return array{old: array<string, mixed>, new: array<string, mixed>}
     */
    private function changes(array $oldValues, array $newValues): array
    {
        $changedKeys = array_keys(array_filter(
            $newValues,
            static fn (mixed $value, string $key): bool => $oldValues[$key] !== $value,
            ARRAY_FILTER_USE_BOTH,
        ));

        return [
            'old' => array_intersect_key($oldValues, array_flip($changedKeys)),
            'new' => array_intersect_key($newValues, array_flip($changedKeys)),
        ];
    }
}
