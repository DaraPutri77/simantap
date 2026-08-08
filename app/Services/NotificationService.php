<?php

namespace App\Services;

use App\Enums\InventoryRequestStatus;
use App\Enums\MaintenanceStatus;
use App\Enums\RoleName;
use App\Enums\VehicleLoanStatus;
use App\Models\InventoryRequest;
use App\Models\Item;
use App\Models\MaintenanceRecord;
use App\Models\User;
use App\Models\VehicleLoan;
use App\Notifications\SystemNotification;
use Illuminate\Support\Collection;

class NotificationService
{
    public const DEFAULT_DEDUPLICATION_MINUTES = 1440;

    public function inventoryRequestChanged(
        InventoryRequest $inventoryRequest,
    ): void {
        $status = $inventoryRequest->status;

        if ($status === InventoryRequestStatus::Submitted) {
            $this->notifyAdministrators(
                event: 'inventory_request_submitted',
                module: 'inventory_request',
                title: 'Permintaan barang baru',
                message: "{$inventoryRequest->requester_name_snapshot} mengajukan {$inventoryRequest->request_number}.",
                level: 'info',
                routeName: 'inventory-requests.approval-queue',
                routeParams: [],
                resourceType: InventoryRequest::class,
                resourceId: $inventoryRequest->getKey(),
            );

            return;
        }

        if ($status === InventoryRequestStatus::Cancelled) {
            $this->notifyAdministrators(
                event: 'inventory_request_cancelled',
                module: 'inventory_request',
                title: 'Permintaan barang dibatalkan',
                message: "{$inventoryRequest->request_number} telah dibatalkan.",
                level: 'warning',
                routeName: 'inventory-requests.index',
                routeParams: [],
                resourceType: InventoryRequest::class,
                resourceId: $inventoryRequest->getKey(),
            );

            return;
        }

        $recipient = $inventoryRequest->requester;

        if ($recipient === null) {
            return;
        }

        $presentation = match ($status) {
            InventoryRequestStatus::RevisionRequired => [
                'title' => 'Permintaan perlu diperbaiki',
                'message' => "{$inventoryRequest->request_number} dikembalikan untuk diperbaiki.",
                'level' => 'warning',
            ],
            InventoryRequestStatus::Approved => [
                'title' => 'Permintaan disetujui',
                'message' => "{$inventoryRequest->request_number} telah disetujui.",
                'level' => 'success',
            ],
            InventoryRequestStatus::PartiallyApproved => [
                'title' => 'Permintaan disetujui sebagian',
                'message' => "Sebagian item pada {$inventoryRequest->request_number} telah disetujui.",
                'level' => 'success',
            ],
            InventoryRequestStatus::WaitingStock => [
                'title' => 'Permintaan menunggu stok',
                'message' => "{$inventoryRequest->request_number} menunggu ketersediaan stok.",
                'level' => 'warning',
            ],
            InventoryRequestStatus::ReadyForDelivery => [
                'title' => 'Barang siap diserahkan',
                'message' => "Barang untuk {$inventoryRequest->request_number} sudah siap diserahkan.",
                'level' => 'success',
            ],
            InventoryRequestStatus::Delivered => [
                'title' => 'Barang telah diserahkan',
                'message' => "Barang {$inventoryRequest->request_number} telah diserahkan dan menunggu konfirmasi penerimaan Anda.",
                'level' => 'info',
            ],
            InventoryRequestStatus::Rejected => [
                'title' => 'Permintaan ditolak',
                'message' => "{$inventoryRequest->request_number} tidak disetujui.",
                'level' => 'danger',
            ],
            InventoryRequestStatus::Expired => [
                'title' => 'Permintaan kedaluwarsa',
                'message' => "Masa proses {$inventoryRequest->request_number} telah berakhir.",
                'level' => 'warning',
            ],
            default => null,
        };

        if ($presentation === null) {
            return;
        }

        $this->notify(
            recipient: $recipient,
            event: 'inventory_request_'.$status->value,
            module: 'inventory_request',
            title: $presentation['title'],
            message: $presentation['message'],
            level: $presentation['level'],
            routeName: 'my.inventory-requests.show',
            routeParams: [
                'inventory_request' => $inventoryRequest->getRouteKey(),
            ],
            resourceType: InventoryRequest::class,
            resourceId: $inventoryRequest->getKey(),
        );
    }

    public function vehicleLoanChanged(VehicleLoan $vehicleLoan): void
    {
        $status = $vehicleLoan->status;

        if ($status === VehicleLoanStatus::Submitted) {
            $this->notifyAdministrators(
                event: 'vehicle_loan_submitted',
                module: 'vehicle_loan',
                title: 'Peminjaman kendaraan baru',
                message: "{$vehicleLoan->borrower_name_snapshot} mengajukan {$vehicleLoan->loan_number}.",
                level: 'info',
                routeName: 'vehicle-loans.approval-queue',
                routeParams: [],
                resourceType: VehicleLoan::class,
                resourceId: $vehicleLoan->getKey(),
            );

            return;
        }

        if ($status === VehicleLoanStatus::AwaitingReturnInspection) {
            $this->notifyAdministrators(
                event: 'vehicle_loan_return_requested',
                module: 'vehicle_loan',
                title: 'Kendaraan menunggu pemeriksaan pengembalian',
                message: "{$vehicleLoan->loan_number} telah diajukan untuk pengembalian.",
                level: 'warning',
                routeName: 'vehicle-loan-lifecycle.admin.index',
                routeParams: [],
                resourceType: VehicleLoan::class,
                resourceId: $vehicleLoan->getKey(),
            );

            return;
        }

        if ($status === VehicleLoanStatus::ReturnIssue) {
            $this->notifyAdministrators(
                event: 'vehicle_loan_return_issue',
                module: 'vehicle_loan',
                title: 'Masalah pengembalian kendaraan',
                message: "{$vehicleLoan->loan_number} memerlukan tindak lanjut pemeriksaan atau pemeliharaan.",
                level: 'danger',
                routeName: 'vehicle-loan-lifecycle.admin.index',
                routeParams: [],
                resourceType: VehicleLoan::class,
                resourceId: $vehicleLoan->getKey(),
            );
        }

        $recipient = $vehicleLoan->borrower;

        if ($recipient === null) {
            return;
        }

        $presentation = match ($status) {
            VehicleLoanStatus::Approved => [
                'title' => 'Peminjaman disetujui',
                'message' => "{$vehicleLoan->loan_number} telah disetujui dan kendaraan direservasi.",
                'level' => 'success',
            ],
            VehicleLoanStatus::ReadyForPickup => [
                'title' => 'Kendaraan siap diambil',
                'message' => "Kendaraan untuk {$vehicleLoan->loan_number} sudah siap diserahterimakan.",
                'level' => 'success',
            ],
            VehicleLoanStatus::Rejected => [
                'title' => 'Peminjaman ditolak',
                'message' => "{$vehicleLoan->loan_number} tidak disetujui.",
                'level' => 'danger',
            ],
            VehicleLoanStatus::Completed => [
                'title' => 'Peminjaman selesai',
                'message' => "{$vehicleLoan->loan_number} telah selesai diproses.",
                'level' => 'success',
            ],
            VehicleLoanStatus::Cancelled => [
                'title' => 'Peminjaman dibatalkan',
                'message' => "{$vehicleLoan->loan_number} telah dibatalkan.",
                'level' => 'warning',
            ],
            VehicleLoanStatus::ReturnIssue => [
                'title' => 'Pengembalian memerlukan tindak lanjut',
                'message' => "Kondisi akhir {$vehicleLoan->loan_number} memerlukan pemeriksaan lebih lanjut.",
                'level' => 'danger',
            ],
            default => null,
        };

        if ($presentation === null) {
            return;
        }

        $this->notify(
            recipient: $recipient,
            event: 'vehicle_loan_'.$status->value,
            module: 'vehicle_loan',
            title: $presentation['title'],
            message: $presentation['message'],
            level: $presentation['level'],
            routeName: 'my.vehicle-loans.show',
            routeParams: [
                'vehicle_loan' => $vehicleLoan->getRouteKey(),
            ],
            resourceType: VehicleLoan::class,
            resourceId: $vehicleLoan->getKey(),
        );
    }

    public function vehicleLoanOverdue(VehicleLoan $vehicleLoan): void
    {
        $recipient = $vehicleLoan->borrower;

        if ($recipient !== null) {
            $this->notifyOnce(
                recipient: $recipient,
                event: 'vehicle_loan_overdue',
                module: 'vehicle_loan',
                title: 'Peminjaman melewati batas pengembalian',
                message: "{$vehicleLoan->loan_number} telah melewati rencana waktu pengembalian.",
                level: 'danger',
                routeName: 'vehicle-loan-lifecycle.employee.index',
                routeParams: [],
                resourceType: VehicleLoan::class,
                resourceId: $vehicleLoan->getKey(),
            );
        }

        $this->notifyAdministratorsOnce(
            event: 'vehicle_loan_overdue',
            module: 'vehicle_loan',
            title: 'Kendaraan terlambat dikembalikan',
            message: "{$vehicleLoan->loan_number} melewati rencana waktu pengembalian.",
            level: 'danger',
            routeName: 'vehicle-loan-lifecycle.admin.index',
            routeParams: [],
            resourceType: VehicleLoan::class,
            resourceId: $vehicleLoan->getKey(),
        );
    }

    public function vehicleLoanDueSoon(VehicleLoan $vehicleLoan): void
    {
        $recipient = $vehicleLoan->borrower;

        if ($recipient === null) {
            return;
        }

        $displayTime = $vehicleLoan->planned_end_at?->copy()->timezone(
            config('simantap.display_timezone', 'Asia/Jakarta'),
        );

        $this->notifyOnce(
            recipient: $recipient,
            event: 'vehicle_loan_due_soon',
            module: 'vehicle_loan',
            title: 'Batas pengembalian kendaraan mendekat',
            message: $displayTime === null
                ? "{$vehicleLoan->loan_number} mendekati batas pengembalian."
                : "{$vehicleLoan->loan_number} dijadwalkan kembali {$displayTime->translatedFormat('d M Y H:i')} WIB.",
            level: 'warning',
            routeName: 'vehicle-loan-lifecycle.employee.index',
            routeParams: [],
            resourceType: VehicleLoan::class,
            resourceId: $vehicleLoan->getKey(),
        );
    }

    public function maintenanceChanged(MaintenanceRecord $maintenanceRecord): void
    {
        $status = $maintenanceRecord->status;

        if ($status === MaintenanceStatus::Reported) {
            $this->notifyAdministrators(
                event: 'maintenance_reported',
                module: 'maintenance',
                title: 'Laporan pemeliharaan baru',
                message: "{$maintenanceRecord->maintenance_number} perlu diperiksa.",
                level: 'warning',
                routeName: 'maintenance-records.show',
                routeParams: [
                    'maintenanceRecord' => $maintenanceRecord->getRouteKey(),
                ],
                resourceType: MaintenanceRecord::class,
                resourceId: $maintenanceRecord->getKey(),
            );
        }

        $borrower = $maintenanceRecord->sourceVehicleLoan?->borrower;

        if ($borrower === null) {
            return;
        }

        $presentation = match ($status) {
            MaintenanceStatus::InProgress => [
                'title' => 'Pemeliharaan kendaraan dimulai',
                'message' => "Tindak lanjut {$maintenanceRecord->maintenance_number} sedang dikerjakan.",
                'level' => 'info',
            ],
            MaintenanceStatus::Completed,
            MaintenanceStatus::CompletedWithNotes => [
                'title' => 'Pemeliharaan kendaraan selesai',
                'message' => "{$maintenanceRecord->maintenance_number} telah selesai diproses.",
                'level' => 'success',
            ],
            MaintenanceStatus::FurtherActionRequired => [
                'title' => 'Pemeliharaan memerlukan tindak lanjut',
                'message' => "{$maintenanceRecord->maintenance_number} memerlukan tindakan lanjutan.",
                'level' => 'warning',
            ],
            MaintenanceStatus::SeverelyDamaged,
            MaintenanceStatus::Unserviceable => [
                'title' => 'Hasil pemeliharaan kendaraan',
                'message' => "{$maintenanceRecord->maintenance_number} menghasilkan kondisi kendaraan yang memerlukan perhatian khusus.",
                'level' => 'danger',
            ],
            default => null,
        };

        if ($presentation === null) {
            return;
        }

        $sourceLoan = $maintenanceRecord->sourceVehicleLoan;

        $this->notify(
            recipient: $borrower,
            event: 'maintenance_'.$status->value,
            module: 'maintenance',
            title: $presentation['title'],
            message: $presentation['message'],
            level: $presentation['level'],
            routeName: 'my.vehicle-loans.show',
            routeParams: [
                'vehicle_loan' => $sourceLoan?->getRouteKey(),
            ],
            resourceType: MaintenanceRecord::class,
            resourceId: $maintenanceRecord->getKey(),
        );
    }

    public function lowStock(Item $item): void
    {
        $this->notifyAdministratorsOnce(
            event: 'stock_low',
            module: 'inventory',
            title: 'Stok minimum tercapai',
            message: "{$item->item_code} {$item->name} memiliki stok tersedia {$item->available_stock} dengan batas minimum {$item->minimum_stock}.",
            level: 'warning',
            routeName: 'items.show',
            routeParams: [
                'item' => $item->getRouteKey(),
            ],
            resourceType: Item::class,
            resourceId: $item->getKey(),
        );
    }

    /**
     * @param  array<string, scalar|null>  $routeParams
     */
    public function notify(
        User $recipient,
        string $event,
        string $module,
        string $title,
        string $message,
        string $level,
        string $routeName,
        array $routeParams,
        string $resourceType,
        int|string $resourceId,
    ): void {
        if (! $recipient->isActive()) {
            return;
        }

        $recipient->notify(new SystemNotification([
            'event' => $event,
            'module' => $module,
            'title' => $title,
            'message' => $message,
            'level' => $level,
            'route_name' => $routeName,
            'route_params' => $routeParams,
            'resource_type' => $resourceType,
            'resource_id' => (string) $resourceId,
            'occurred_at' => now()->toISOString(),
        ]));
    }

    /**
     * @param  array<string, scalar|null>  $routeParams
     */
    public function notifyOnce(
        User $recipient,
        string $event,
        string $module,
        string $title,
        string $message,
        string $level,
        string $routeName,
        array $routeParams,
        string $resourceType,
        int|string $resourceId,
        int $deduplicationMinutes = self::DEFAULT_DEDUPLICATION_MINUTES,
    ): bool {
        if ($this->wasSentRecently(
            $recipient,
            $event,
            $resourceType,
            $resourceId,
            $deduplicationMinutes,
        )) {
            return false;
        }

        $this->notify(
            $recipient,
            $event,
            $module,
            $title,
            $message,
            $level,
            $routeName,
            $routeParams,
            $resourceType,
            $resourceId,
        );

        return true;
    }

    /**
     * @param  array<string, scalar|null>  $routeParams
     */
    private function notifyAdministrators(
        string $event,
        string $module,
        string $title,
        string $message,
        string $level,
        string $routeName,
        array $routeParams,
        string $resourceType,
        int|string $resourceId,
    ): void {
        $this->administrators()->each(function (User $administrator) use (
            $event,
            $module,
            $title,
            $message,
            $level,
            $routeName,
            $routeParams,
            $resourceType,
            $resourceId,
        ): void {
            $this->notify(
                $administrator,
                $event,
                $module,
                $title,
                $message,
                $level,
                $routeName,
                $routeParams,
                $resourceType,
                $resourceId,
            );
        });
    }

    /**
     * @param  array<string, scalar|null>  $routeParams
     */
    private function notifyAdministratorsOnce(
        string $event,
        string $module,
        string $title,
        string $message,
        string $level,
        string $routeName,
        array $routeParams,
        string $resourceType,
        int|string $resourceId,
        int $deduplicationMinutes = self::DEFAULT_DEDUPLICATION_MINUTES,
    ): void {
        $this->administrators()->each(function (User $administrator) use (
            $event,
            $module,
            $title,
            $message,
            $level,
            $routeName,
            $routeParams,
            $resourceType,
            $resourceId,
            $deduplicationMinutes,
        ): void {
            $this->notifyOnce(
                $administrator,
                $event,
                $module,
                $title,
                $message,
                $level,
                $routeName,
                $routeParams,
                $resourceType,
                $resourceId,
                $deduplicationMinutes,
            );
        });
    }

    private function wasSentRecently(
        User $recipient,
        string $event,
        string $resourceType,
        int|string $resourceId,
        int $deduplicationMinutes,
    ): bool {
        return $recipient->notifications()
            ->where('created_at', '>=', now()->subMinutes($deduplicationMinutes))
            ->latest()
            ->limit(200)
            ->get()
            ->contains(static function ($notification) use (
                $event,
                $resourceType,
                $resourceId,
            ): bool {
                return data_get($notification->data, 'event') === $event
                    && data_get($notification->data, 'resource_type') === $resourceType
                    && (string) data_get($notification->data, 'resource_id') === (string) $resourceId;
            });
    }

    /**
     * @return Collection<int, User>
     */
    private function administrators(): Collection
    {
        return User::query()
            ->active()
            ->whereHas('roles', static function ($query): void {
                $query
                    ->where('name', RoleName::Administrator->value)
                    ->where('guard_name', 'web');
            })
            ->orderBy('id')
            ->get();
    }
}
