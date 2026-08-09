<?php

namespace App\Services;

use App\Enums\InventoryRequestStatus;
use App\Enums\MaintenanceStatus;
use App\Enums\StockMovementType;
use App\Enums\VehicleLoanStatus;
use App\Models\InventoryRequest;
use App\Models\InventoryRequestItem;
use App\Models\Item;
use App\Models\MaintenanceRecord;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\VehicleLoan;
use App\Support\DisplayDateRange;
use App\Support\ReportCatalog;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ReportService
{
    /**
     * @param  array{
     *     report: string,
     *     search: string,
     *     itemId: int,
     *     movementType: string,
     *     status: string,
     *     workUnit: string,
     *     from: string,
     *     until: string
     * }  $filters
     * @return array<string, mixed>
     */
    public function build(array $filters): array
    {
        $result = match ($filters['report']) {
            'stock' => $this->stock($filters),
            'stock-in' => $this->stockMovements($filters, inbound: true),
            'stock-out' => $this->stockMovements($filters, inbound: false),
            'stock-card' => $this->stockMovements($filters),
            'inventory-requests' => $this->inventoryRequests($filters),
            'inventory-usage' => $this->inventoryUsage($filters),
            'vehicle-loans' => $this->vehicleLoans($filters),
            'vehicle-overdue' => $this->vehicleLoans($filters, overdue: true),
            'maintenance' => $this->maintenance($filters),
            default => $this->stock($filters),
        };

        $definition = ReportCatalog::definition($filters['report']);
        $rows = $result['rows'];
        $period = $this->periodLabel($filters);

        return [
            'report' => $filters['report'],
            'title' => $definition['label'],
            'description' => $definition['description'],
            'columns' => $result['columns'],
            'rows' => $rows,
            'previewRows' => array_slice($rows, 0, 25),
            'summary' => [
                [
                    'label' => 'Data ditemukan',
                    'value' => number_format(count($rows), 0, ',', '.'),
                ],
                ...($result['summary'] ?? []),
            ],
            'filters' => $filters,
            'periodLabel' => $period,
            'generatedAt' => now(
                (string) config('simantap.display_timezone', 'Asia/Jakarta'),
            ),
            'displayTimezone' => (string) config(
                'simantap.display_timezone',
                'Asia/Jakarta',
            ),
        ];
    }

    public function filename(string $report, string $extension = 'pdf'): string
    {
        return 'LAPORAN-'.Str::upper(str_replace('-', '_', $report))
            .'-'.now(
                (string) config('simantap.display_timezone', 'Asia/Jakarta'),
            )->format('Ymd-His').'.'.ltrim($extension, '.');
    }

    /**
     * @return array<string, string>
     */
    public function statusOptions(string $report): array
    {
        return match ($report) {
            'inventory-requests' => InventoryRequestStatus::options(),
            'vehicle-loans', 'vehicle-overdue' => array_reduce(
                VehicleLoanStatus::cases(),
                static function (array $options, VehicleLoanStatus $status): array {
                    $options[$status->value] = $status->label();

                    return $options;
                },
                [],
            ),
            'maintenance' => array_reduce(
                MaintenanceStatus::cases(),
                static function (array $options, MaintenanceStatus $status): array {
                    $options[$status->value] = $status->label();

                    return $options;
                },
                [],
            ),
            default => [],
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function filterOptions(string $report): array
    {
        return [
            'items' => Item::query()
                ->withTrashed()
                ->orderBy('name')
                ->get(['id', 'item_code', 'name', 'is_active', 'deleted_at']),
            'workUnits' => User::query()
                ->whereNotNull('work_unit')
                ->where('work_unit', '<>', '')
                ->distinct()
                ->orderBy('work_unit')
                ->pluck('work_unit'),
            'movementTypes' => StockMovementType::options(),
            'statuses' => $this->statusOptions($report),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{columns: list<array{key: string, label: string}>, rows: list<array<string, string>>, summary?: list<array{label: string, value: string}>}
     */
    private function stock(array $filters): array
    {
        $query = Item::query()
            ->withTrashed()
            ->with(['unit:id,symbol', 'category:id,name'])
            ->when(
                $filters['search'] !== '',
                static fn (Builder $itemQuery): Builder => $itemQuery->where(function (Builder $nested) use ($filters): void {
                    $nested
                        ->where('item_code', 'like', '%'.$filters['search'].'%')
                        ->orWhere('name', 'like', '%'.$filters['search'].'%');
                }),
            )
            ->when(
                $filters['itemId'] > 0,
                static fn (Builder $itemQuery): Builder => $itemQuery->whereKey($filters['itemId']),
            )
            ->orderBy('name');
        $items = $query->get();
        $rows = $items->map(fn (Item $item): array => [
            'kode' => $item->item_code,
            'nama' => $item->name,
            'kategori' => $item->category?->name ?: '-',
            'satuan' => $item->unit?->symbol ?: '-',
            'saldo_sistem' => $this->quantity($item->current_stock),
            'terpesan' => $this->quantity($item->reserved_stock),
            'tersedia' => $this->quantity((float) $item->current_stock - (float) $item->reserved_stock),
            'minimum' => $this->quantity($item->minimum_stock),
            'status' => $item->trashed() || ! $item->is_active ? 'Nonaktif' : ($item->is_low_stock ? 'Stok minimum' : 'Aman'),
        ])->all();

        return [
            'columns' => $this->columns([
                'kode' => 'Kode',
                'nama' => 'Nama Barang',
                'kategori' => 'Kategori',
                'satuan' => 'Satuan',
                'saldo_sistem' => 'Saldo Sistem',
                'terpesan' => 'Terpesan',
                'tersedia' => 'Tersedia',
                'minimum' => 'Minimum',
                'status' => 'Status',
            ]),
            'rows' => $rows,
            'summary' => [
                ['label' => 'Total tersedia', 'value' => $this->quantity($items->sum(fn (Item $item): float => max(0, (float) $item->current_stock - (float) $item->reserved_stock)))],
                ['label' => 'Perlu perhatian', 'value' => number_format($items->filter(fn (Item $item): bool => $item->is_low_stock)->count(), 0, ',', '.')],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{columns: list<array{key: string, label: string}>, rows: list<array<string, string>>, summary?: list<array{label: string, value: string}>}
     */
    private function stockMovements(array $filters, ?bool $inbound = null): array
    {
        $bounds = DisplayDateRange::utcBounds($filters['from'], $filters['until']);
        $query = StockMovement::query()
            ->with(['item:id,item_code,name,unit_id', 'item.unit:id,symbol'])
            ->when(
                $inbound === true,
                static fn (Builder $movementQuery): Builder => $movementQuery->where('quantity_in', '>', 0),
            )
            ->when(
                $inbound === false,
                static fn (Builder $movementQuery): Builder => $movementQuery->where('quantity_out', '>', 0),
            )
            ->when(
                $filters['itemId'] > 0,
                static fn (Builder $movementQuery): Builder => $movementQuery->where('item_id', $filters['itemId']),
            )
            ->when(
                $filters['movementType'] !== '',
                static fn (Builder $movementQuery): Builder => $movementQuery->where('movement_type', $filters['movementType']),
            )
            ->when(
                $filters['search'] !== '',
                static fn (Builder $movementQuery): Builder => $movementQuery->where(function (Builder $nested) use ($filters): void {
                    $nested
                        ->where('movement_number', 'like', '%'.$filters['search'].'%')
                        ->orWhere('reference_number', 'like', '%'.$filters['search'].'%')
                        ->orWhereHas('item', static fn (Builder $itemQuery): Builder => $itemQuery
                            ->where('item_code', 'like', '%'.$filters['search'].'%')
                            ->orWhere('name', 'like', '%'.$filters['search'].'%'));
                }),
            )
            ->when(
                $bounds['from'] !== null,
                static fn (Builder $movementQuery): Builder => $movementQuery->where('transaction_date', '>=', $bounds['from']),
            )
            ->when(
                $bounds['until'] !== null,
                static fn (Builder $movementQuery): Builder => $movementQuery->where('transaction_date', '<=', $bounds['until']),
            )
            ->orderBy('transaction_date')
            ->orderBy('id');
        $movements = $query->get();
        $rows = $movements->map(fn (StockMovement $movement): array => [
            'tanggal' => $this->date($movement->transaction_date, 'd/m/Y H:i'),
            'nomor' => $movement->movement_number ?: $movement->transaction_number,
            'referensi' => $movement->reference_number ?: '-',
            'barang' => $movement->item?->item_code.' · '.$movement->item?->name,
            'jenis' => $movement->movement_type?->label() ?: '-',
            'stok_awal' => $this->quantity($movement->stock_before),
            'masuk' => $this->quantity($movement->quantity_in),
            'keluar' => $this->quantity($movement->quantity_out),
            'stok_akhir' => $this->quantity($movement->stock_after),
        ])->all();

        $columns = [
            'tanggal' => 'Tanggal WIB',
            'nomor' => 'Nomor Transaksi',
            'referensi' => 'Referensi',
            'barang' => 'Barang',
            'jenis' => 'Jenis',
        ];
        if ($inbound === null) {
            $columns += [
                'stok_awal' => 'Stok Awal',
                'masuk' => 'Masuk',
                'keluar' => 'Keluar',
                'stok_akhir' => 'Stok Akhir',
            ];
        } else {
            $columns += [
                'jumlah' => $inbound ? 'Jumlah Masuk' : 'Jumlah Keluar',
            ];
            $rows = array_map(
                static function (array $row) use ($inbound): array {
                    $row['jumlah'] = $inbound ? $row['masuk'] : $row['keluar'];
                    unset($row['stok_awal'], $row['masuk'], $row['keluar'], $row['stok_akhir']);

                    return $row;
                },
                $rows,
            );
        }

        return [
            'columns' => $this->columns($columns),
            'rows' => $rows,
            'summary' => [
                ['label' => $inbound === null ? 'Pergerakan' : ($inbound ? 'Total masuk' : 'Total keluar'), 'value' => $this->quantity($movements->sum(fn (StockMovement $movement): float => $inbound === false ? (float) $movement->quantity_out : ($inbound === true ? (float) $movement->quantity_in : (float) $movement->quantity_in + (float) $movement->quantity_out)))],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{columns: list<array{key: string, label: string}>, rows: list<array<string, string>>, summary?: list<array{label: string, value: string}>}
     */
    private function inventoryRequests(array $filters): array
    {
        $bounds = DisplayDateRange::utcBounds($filters['from'], $filters['until']);
        $query = InventoryRequest::query()
            ->with(['requester:id,name,employee_number,work_unit'])
            ->when($filters['status'] !== '', static fn (Builder $requestQuery): Builder => $requestQuery->where('status', $filters['status']))
            ->when($filters['workUnit'] !== '', static fn (Builder $requestQuery): Builder => $requestQuery->where('work_unit_snapshot', $filters['workUnit']))
            ->when($filters['search'] !== '', static fn (Builder $requestQuery): Builder => $requestQuery->where(function (Builder $nested) use ($filters): void {
                $nested
                    ->where('request_number', 'like', '%'.$filters['search'].'%')
                    ->orWhere('requester_name_snapshot', 'like', '%'.$filters['search'].'%')
                    ->orWhere('employee_number_snapshot', 'like', '%'.$filters['search'].'%')
                    ->orWhere('purpose', 'like', '%'.$filters['search'].'%');
            }))
            ->when($bounds['from'] !== null, static fn (Builder $requestQuery): Builder => $requestQuery->where('request_date', '>=', $bounds['from']))
            ->when($bounds['until'] !== null, static fn (Builder $requestQuery): Builder => $requestQuery->where('request_date', '<=', $bounds['until']))
            ->orderBy('request_date')
            ->orderBy('id');
        $requests = $query->get();
        $rows = $requests->map(fn (InventoryRequest $request): array => [
            'tanggal' => $this->date($request->request_date, 'd/m/Y H:i'),
            'nomor' => $request->request_number,
            'pemohon' => $request->requester_name_snapshot,
            'nomor_pegawai' => $request->employee_number_snapshot ?: '-',
            'unit_kerja' => $request->work_unit_snapshot ?: '-',
            'keperluan' => Str::limit($request->purpose, 100),
            'status' => $request->status?->label() ?: '-',
        ])->all();

        return [
            'columns' => $this->columns([
                'tanggal' => 'Tanggal WIB',
                'nomor' => 'Nomor Permintaan',
                'pemohon' => 'Pemohon',
                'nomor_pegawai' => 'Nomor Pegawai',
                'unit_kerja' => 'Unit Kerja',
                'keperluan' => 'Keperluan',
                'status' => 'Status',
            ]),
            'rows' => $rows,
            'summary' => [
                ['label' => 'Selesai', 'value' => number_format($requests->filter(fn (InventoryRequest $request): bool => $request->status === InventoryRequestStatus::Completed)->count(), 0, ',', '.')],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{columns: list<array{key: string, label: string}>, rows: list<array<string, string>>, summary?: list<array{label: string, value: string}>}
     */
    private function inventoryUsage(array $filters): array
    {
        $bounds = DisplayDateRange::utcBounds($filters['from'], $filters['until']);
        $query = InventoryRequestItem::query()
            ->with(['inventoryRequest:id,request_number,request_date,requester_name_snapshot,work_unit_snapshot,status'])
            ->where('delivered_quantity', '>', 0)
            ->when($filters['itemId'] > 0, static fn (Builder $itemQuery): Builder => $itemQuery->where('item_id', $filters['itemId']))
            ->when($filters['workUnit'] !== '', static fn (Builder $itemQuery): Builder => $itemQuery->whereHas('inventoryRequest', static fn (Builder $requestQuery): Builder => $requestQuery->where('work_unit_snapshot', $filters['workUnit'])))
            ->when($filters['search'] !== '', static fn (Builder $itemQuery): Builder => $itemQuery->where(function (Builder $nested) use ($filters): void {
                $nested
                    ->where('item_code_snapshot', 'like', '%'.$filters['search'].'%')
                    ->orWhere('item_name_snapshot', 'like', '%'.$filters['search'].'%')
                    ->orWhereHas('inventoryRequest', static fn (Builder $requestQuery): Builder => $requestQuery->where('request_number', 'like', '%'.$filters['search'].'%')->orWhere('requester_name_snapshot', 'like', '%'.$filters['search'].'%'));
            }))
            ->when($bounds['from'] !== null, static fn (Builder $itemQuery): Builder => $itemQuery->whereHas('inventoryRequest', static fn (Builder $requestQuery): Builder => $requestQuery->where('request_date', '>=', $bounds['from'])))
            ->when($bounds['until'] !== null, static fn (Builder $itemQuery): Builder => $itemQuery->whereHas('inventoryRequest', static fn (Builder $requestQuery): Builder => $requestQuery->where('request_date', '<=', $bounds['until'])))
            ->orderBy('id');
        $usage = $query->get();
        $rows = $usage->map(fn (InventoryRequestItem $item): array => [
            'tanggal' => $this->date($item->inventoryRequest?->request_date, 'd/m/Y H:i'),
            'permintaan' => $item->inventoryRequest?->request_number ?: '-',
            'barang' => $item->item_code_snapshot.' · '.$item->item_name_snapshot,
            'satuan' => $item->unit_snapshot,
            'jumlah' => $this->quantity($item->delivered_quantity),
            'pemohon' => $item->inventoryRequest?->requester_name_snapshot ?: '-',
            'unit_kerja' => $item->inventoryRequest?->work_unit_snapshot ?: '-',
        ])->all();

        return [
            'columns' => $this->columns([
                'tanggal' => 'Tanggal WIB',
                'permintaan' => 'Nomor Permintaan',
                'barang' => 'Barang',
                'satuan' => 'Satuan',
                'jumlah' => 'Jumlah Diserahkan',
                'pemohon' => 'Pemohon',
                'unit_kerja' => 'Unit Kerja',
            ]),
            'rows' => $rows,
            'summary' => [
                ['label' => 'Jumlah digunakan', 'value' => $this->quantity($usage->sum(fn (InventoryRequestItem $item): float => (float) $item->delivered_quantity))],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{columns: list<array{key: string, label: string}>, rows: list<array<string, string>>, summary?: list<array{label: string, value: string}>}
     */
    private function vehicleLoans(array $filters, bool $overdue = false): array
    {
        $bounds = DisplayDateRange::utcBounds($filters['from'], $filters['until']);
        $dateColumn = $overdue ? 'overdue_at' : 'planned_start_at';
        $query = VehicleLoan::query()
            ->with(['borrower:id,name,employee_number,work_unit', 'vehicle:id,public_id,vehicle_code,license_plate,brand,model'])
            ->when($overdue, static fn (Builder $loanQuery): Builder => $loanQuery->whereNotNull('overdue_at'))
            ->when($filters['status'] !== '', static fn (Builder $loanQuery): Builder => $loanQuery->where('status', $filters['status']))
            ->when($filters['workUnit'] !== '', static fn (Builder $loanQuery): Builder => $loanQuery->where('work_unit_snapshot', $filters['workUnit']))
            ->when($filters['search'] !== '', static fn (Builder $loanQuery): Builder => $loanQuery->where(function (Builder $nested) use ($filters): void {
                $nested
                    ->where('loan_number', 'like', '%'.$filters['search'].'%')
                    ->orWhere('borrower_name_snapshot', 'like', '%'.$filters['search'].'%')
                    ->orWhere('employee_number_snapshot', 'like', '%'.$filters['search'].'%')
                    ->orWhere('vehicle_code_snapshot', 'like', '%'.$filters['search'].'%')
                    ->orWhere('license_plate_snapshot', 'like', '%'.$filters['search'].'%')
                    ->orWhere('destination', 'like', '%'.$filters['search'].'%');
            }))
            ->when($bounds['from'] !== null, static fn (Builder $loanQuery): Builder => $loanQuery->where($dateColumn, '>=', $bounds['from']))
            ->when($bounds['until'] !== null, static fn (Builder $loanQuery): Builder => $loanQuery->where($dateColumn, '<=', $bounds['until']))
            ->orderBy($dateColumn)
            ->orderBy('id');
        $loans = $query->get();
        $rows = $loans->map(fn (VehicleLoan $loan): array => [
            'nomor' => $loan->loan_number,
            'peminjam' => $loan->borrower_name_snapshot,
            'nomor_pegawai' => $loan->employee_number_snapshot ?: '-',
            'unit_kerja' => $loan->work_unit_snapshot ?: '-',
            'kendaraan' => $loan->vehicle_code_snapshot.' · '.$loan->license_plate_snapshot,
            'mulai' => $this->date($loan->planned_start_at, 'd/m/Y H:i'),
            'selesai_rencana' => $this->date($loan->planned_end_at, 'd/m/Y H:i'),
            'kembali' => $this->date($loan->actual_end_at, 'd/m/Y H:i'),
            'status' => $loan->status?->label() ?: '-',
        ])->all();

        return [
            'columns' => $this->columns([
                'nomor' => 'Nomor Peminjaman',
                'peminjam' => 'Peminjam',
                'nomor_pegawai' => 'Nomor Pegawai',
                'unit_kerja' => 'Unit Kerja',
                'kendaraan' => 'Kendaraan',
                'mulai' => 'Mulai WIB',
                'selesai_rencana' => 'Selesai Rencana WIB',
                'kembali' => 'Kembali WIB',
                'status' => 'Status',
            ]),
            'rows' => $rows,
            'summary' => $overdue
                ? [['label' => 'Belum selesai', 'value' => number_format($loans->filter(fn (VehicleLoan $loan): bool => ! $loan->isCompleted())->count(), 0, ',', '.')]]
                : [['label' => 'Selesai', 'value' => number_format($loans->filter(fn (VehicleLoan $loan): bool => $loan->isCompleted())->count(), 0, ',', '.')]],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{columns: list<array{key: string, label: string}>, rows: list<array<string, string>>, summary?: list<array{label: string, value: string}>}
     */
    private function maintenance(array $filters): array
    {
        $query = MaintenanceRecord::query()
            ->with(['vehicle:id,public_id,vehicle_code,license_plate,brand,model', 'reporter:id,name,employee_number'])
            ->when($filters['status'] !== '', static fn (Builder $maintenanceQuery): Builder => $maintenanceQuery->where('status', $filters['status']))
            ->when($filters['search'] !== '', static fn (Builder $maintenanceQuery): Builder => $maintenanceQuery->where(function (Builder $nested) use ($filters): void {
                $nested
                    ->where('maintenance_number', 'like', '%'.$filters['search'].'%')
                    ->orWhere('vehicle_snapshot', 'like', '%'.$filters['search'].'%')
                    ->orWhere('maintenance_type', 'like', '%'.$filters['search'].'%')
                    ->orWhere('complaint', 'like', '%'.$filters['search'].'%')
                    ->orWhereHas('vehicle', static fn (Builder $vehicleQuery): Builder => $vehicleQuery->where('vehicle_code', 'like', '%'.$filters['search'].'%')->orWhere('license_plate', 'like', '%'.$filters['search'].'%'));
            }))
            ->when($filters['from'] !== '', static fn (Builder $maintenanceQuery): Builder => $maintenanceQuery->whereDate('reported_date', '>=', $filters['from']))
            ->when($filters['until'] !== '', static fn (Builder $maintenanceQuery): Builder => $maintenanceQuery->whereDate('reported_date', '<=', $filters['until']))
            ->orderBy('reported_date')
            ->orderBy('id');
        $records = $query->get();
        $rows = $records->map(fn (MaintenanceRecord $record): array => [
            'tanggal' => $record->reported_date?->format('d/m/Y') ?: '-',
            'nomor' => $record->maintenance_number,
            'kendaraan' => $record->vehicle_snapshot,
            'jenis' => $record->maintenance_type,
            'pelapor' => $record->reporter?->name ?: '-',
            'penyedia' => $record->service_provider ?: '-',
            'biaya' => $record->cost === null ? '-' : 'Rp '.number_format((float) $record->cost, 0, ',', '.'),
            'status' => $record->status?->label() ?: '-',
        ])->all();

        return [
            'columns' => $this->columns([
                'tanggal' => 'Tanggal Laporan',
                'nomor' => 'Nomor Pemeliharaan',
                'kendaraan' => 'Kendaraan',
                'jenis' => 'Jenis',
                'pelapor' => 'Pelapor',
                'penyedia' => 'Penyedia Jasa',
                'biaya' => 'Biaya',
                'status' => 'Status',
            ]),
            'rows' => $rows,
            'summary' => [
                ['label' => 'Total biaya', 'value' => 'Rp '.number_format((float) $records->sum(fn (MaintenanceRecord $record): float => (float) $record->cost), 0, ',', '.')],
            ],
        ];
    }

    /**
     * @param  array<string, string>  $columns
     * @return list<array{key: string, label: string}>
     */
    private function columns(array $columns): array
    {
        return collect($columns)
            ->map(static fn (string $label, string $key): array => compact('key', 'label'))
            ->values()
            ->all();
    }

    private function quantity(mixed $value): string
    {
        return number_format((float) $value, 2, ',', '.');
    }

    private function date(?CarbonInterface $date, string $format): string
    {
        return $date === null
            ? '-'
            : $date->copy()->timezone((string) config('simantap.display_timezone', 'Asia/Jakarta'))->translatedFormat($format);
    }

    /**
     * @param  array{from: string, until: string}  $filters
     */
    private function periodLabel(array $filters): string
    {
        if ($filters['from'] === '' && $filters['until'] === '') {
            return 'Semua periode';
        }

        return ($filters['from'] ?: 'Awal')
            .' s.d. '.($filters['until'] ?: 'Hari ini');
    }
}
