<?php

namespace App\Services;

use App\Enums\VehicleStatus;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class VehicleService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function createVehicle(
        array $data,
        User $actor,
        ?Request $request = null,
    ): Vehicle {
        $imagePath = $this->storeImage($data['image'] ?? null);

        try {
            return DB::transaction(function () use (
                $data,
                $actor,
                $request,
                $imagePath,
            ): Vehicle {
                $isActive = (bool) $data['is_active'];
                $status = $isActive
                    ? VehicleStatus::from((string) $data['status'])
                    : VehicleStatus::Inactive;
                $vehicle = Vehicle::query()->create([
                    ...$this->attributes($data),
                    'status' => $status,
                    'image_path' => $imagePath,
                    'is_active' => $isActive,
                ]);

                $this->auditLogger->log(
                    event: 'vehicle_created',
                    module: 'vehicle',
                    auditable: $vehicle,
                    newValues: $this->snapshot($vehicle),
                    request: $request,
                    actorId: $actor->getKey(),
                );

                return $vehicle;
            }, 3);
        } catch (Throwable $throwable) {
            if ($imagePath !== null) {
                Storage::disk('public')->delete($imagePath);
            }

            throw $throwable;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateVehicle(
        Vehicle $vehicle,
        array $data,
        User $actor,
        ?Request $request = null,
    ): Vehicle {
        $newImagePath = $this->storeImage($data['image'] ?? null);
        $oldImagePath = $vehicle->image_path;

        try {
            $updatedVehicle = DB::transaction(function () use (
                $vehicle,
                $data,
                $actor,
                $request,
                $newImagePath,
            ): Vehicle {
                $lockedVehicle = Vehicle::query()
                    ->whereKey($vehicle->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();
                $requestedStatus = VehicleStatus::from(
                    (string) $data['status'],
                );

                if (
                    $lockedVehicle->status->isTransactionControlled()
                    && $requestedStatus !== $lockedVehicle->status
                ) {
                    throw ValidationException::withMessages([
                        'status' => 'Status kendaraan yang sedang dipesan atau dipinjam hanya dapat diubah melalui alur peminjaman.',
                    ]);
                }

                $requestedOdometer = round(
                    (float) $data['current_odometer'],
                    1,
                );

                if (
                    $lockedVehicle->status->isTransactionControlled()
                    && abs(
                        $requestedOdometer
                            - (float) $lockedVehicle->current_odometer,
                    ) >= 0.05
                ) {
                    throw ValidationException::withMessages([
                        'current_odometer' => 'Odometer kendaraan yang sedang dipesan atau dipinjam hanya dapat berubah melalui pemeriksaan serah terima.',
                    ]);
                }

                if (
                    $requestedOdometer
                    < (float) $lockedVehicle->current_odometer
                ) {
                    throw ValidationException::withMessages([
                        'current_odometer' => 'Odometer baru tidak boleh lebih kecil dari catatan kendaraan saat ini.',
                    ]);
                }

                if (! $lockedVehicle->is_active) {
                    $requestedStatus = VehicleStatus::Inactive;
                }

                $oldValues = $this->snapshot($lockedVehicle);
                $lockedVehicle->fill([
                    ...$this->attributes($data),
                    'status' => $requestedStatus,
                ]);

                if ($newImagePath !== null) {
                    $lockedVehicle->image_path = $newImagePath;
                } elseif (($data['remove_image'] ?? false) === true) {
                    $lockedVehicle->image_path = null;
                }

                $lockedVehicle->save();
                $newValues = $this->snapshot($lockedVehicle);
                $changes = $this->changes($oldValues, $newValues);

                if ($changes['new'] !== []) {
                    $this->auditLogger->log(
                        event: 'vehicle_updated',
                        module: 'vehicle',
                        auditable: $lockedVehicle,
                        oldValues: $changes['old'],
                        newValues: $changes['new'],
                        request: $request,
                        actorId: $actor->getKey(),
                    );
                }

                return $lockedVehicle;
            }, 3);

            if (
                $oldImagePath !== null
                && $oldImagePath !== $updatedVehicle->image_path
            ) {
                Storage::disk('public')->delete($oldImagePath);
            }

            return $updatedVehicle;
        } catch (Throwable $throwable) {
            if ($newImagePath !== null) {
                Storage::disk('public')->delete($newImagePath);
            }

            throw $throwable;
        }
    }

    public function setVehicleActive(
        Vehicle $vehicle,
        bool $isActive,
        User $actor,
        ?Request $request = null,
    ): Vehicle {
        return DB::transaction(function () use (
            $vehicle,
            $isActive,
            $actor,
            $request,
        ): Vehicle {
            $lockedVehicle = Vehicle::query()
                ->whereKey($vehicle->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (
                ! $isActive
                && $lockedVehicle->status->isTransactionControlled()
            ) {
                throw ValidationException::withMessages([
                    'vehicle' => 'Kendaraan yang sedang dipesan atau dipinjam tidak dapat dinonaktifkan.',
                ]);
            }

            if ($lockedVehicle->is_active === $isActive) {
                return $lockedVehicle;
            }

            $oldValues = [
                'is_active' => $lockedVehicle->is_active,
                'status' => $lockedVehicle->status->value,
            ];
            $lockedVehicle->forceFill([
                'is_active' => $isActive,
                'status' => $isActive
                    ? VehicleStatus::Available
                    : VehicleStatus::Inactive,
            ])->save();

            $this->auditLogger->log(
                event: $isActive
                    ? 'vehicle_activated'
                    : 'vehicle_deactivated',
                module: 'vehicle',
                auditable: $lockedVehicle,
                oldValues: $oldValues,
                newValues: [
                    'is_active' => $lockedVehicle->is_active,
                    'status' => $lockedVehicle->status->value,
                ],
                request: $request,
                actorId: $actor->getKey(),
            );

            return $lockedVehicle;
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return [
            'vehicle_code' => $data['vehicle_code'],
            'license_plate' => $data['license_plate'],
            'brand' => $data['brand'],
            'model' => $data['model'],
            'year' => $data['year'] ?? null,
            'color' => $data['color'] ?? null,
            'chassis_number' => $data['chassis_number'] ?? null,
            'engine_number' => $data['engine_number'] ?? null,
            'current_odometer' => round(
                (float) $data['current_odometer'],
                1,
            ),
            'registration_expiry_date' => $data[
                'registration_expiry_date'
            ] ?? null,
            'storage_location' => $data['storage_location'] ?? null,
            'responsible_person' => $data['responsible_person']
                ?? null,
            'notes' => $data['notes'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Vehicle $vehicle): array
    {
        return [
            'vehicle_code' => $vehicle->vehicle_code,
            'license_plate' => $vehicle->license_plate,
            'brand' => $vehicle->brand,
            'model' => $vehicle->model,
            'year' => $vehicle->year,
            'color' => $vehicle->color,
            'chassis_number' => $vehicle->chassis_number,
            'engine_number' => $vehicle->engine_number,
            'current_odometer' => (float) $vehicle->current_odometer,
            'status' => $vehicle->status->value,
            'registration_expiry_date' => $vehicle
                ->registration_expiry_date?->toDateString(),
            'storage_location' => $vehicle->storage_location,
            'responsible_person' => $vehicle->responsible_person,
            'image_path' => $vehicle->image_path,
            'notes' => $vehicle->notes,
            'is_active' => $vehicle->is_active,
        ];
    }

    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     * @return array{
     *     old: array<string, mixed>,
     *     new: array<string, mixed>
     * }
     */
    private function changes(
        array $oldValues,
        array $newValues,
    ): array {
        $changedKeys = array_keys(array_filter(
            $newValues,
            static fn (
                mixed $value,
                string $key,
            ): bool => $oldValues[$key] !== $value,
            ARRAY_FILTER_USE_BOTH,
        ));

        return [
            'old' => array_intersect_key(
                $oldValues,
                array_flip($changedKeys),
            ),
            'new' => array_intersect_key(
                $newValues,
                array_flip($changedKeys),
            ),
        ];
    }

    private function storeImage(mixed $image): ?string
    {
        if (! $image instanceof UploadedFile) {
            return null;
        }

        $path = $image->store('vehicles', 'public');

        if ($path === false) {
            throw new RuntimeException('Foto kendaraan gagal disimpan.');
        }

        return $path;
    }
}
