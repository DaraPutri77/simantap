<?php

namespace App\Http\Controllers;

use App\Enums\StockMovementType;
use App\Exports\ReportExport;
use App\Models\InventoryReceipt;
use App\Models\InventoryRequest;
use App\Models\Item;
use App\Models\StockAdjustment;
use App\Models\StockMovement;
use App\Services\DocumentSignatoryService;
use App\Support\DisplayDateRange;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StockMovementController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'item' => ['nullable', 'integer'],
            'type' => [
                'nullable',
                'in:'.implode(',', StockMovementType::values()),
            ],
            'direction' => ['nullable', 'in:inbound,outbound'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'until' => [
                'nullable',
                'date_format:Y-m-d',
                Rule::when(
                    $request->filled('from'),
                    'after_or_equal:from',
                ),
            ],
        ]);
        $search = trim((string) ($filters['q'] ?? ''));
        $itemId = (int) ($filters['item'] ?? 0);
        $type = (string) ($filters['type'] ?? '');
        $direction = (string) ($filters['direction'] ?? '');
        $from = (string) ($filters['from'] ?? '');
        $until = (string) ($filters['until'] ?? '');
        $bounds = DisplayDateRange::utcBounds($from, $until);
        $query = StockMovement::query()
            ->with([
                'item' => static function (BelongsTo $itemQuery): void {
                    $itemQuery
                        ->withTrashed()
                        ->with(['category', 'unit']);
                },
                'creator' => static function (BelongsTo $creatorQuery): void {
                    $creatorQuery
                        ->withTrashed()
                        ->select(['id', 'name', 'employee_number']);
                },
            ])
            ->when(
                $search !== '',
                static function (
                    Builder $movementQuery,
                ) use ($search): void {
                    $movementQuery->where(function (
                        Builder $nested,
                    ) use ($search): void {
                        $nested
                            ->where(
                                'transaction_number',
                                'like',
                                "%{$search}%",
                            )
                            ->orWhere(
                                'description',
                                'like',
                                "%{$search}%",
                            )
                            ->orWhere(
                                'reference_number',
                                'like',
                                "%{$search}%",
                            )
                            ->orWhereHas(
                                'item',
                                static fn (Builder $itemQuery): Builder => $itemQuery
                                    ->where(function (
                                        Builder $itemSearch,
                                    ) use ($search): void {
                                        $itemSearch
                                            ->where(
                                                'name',
                                                'like',
                                                "%{$search}%",
                                            )
                                            ->orWhere(
                                                'item_code',
                                                'like',
                                                "%{$search}%",
                                            );
                                    }),
                            )
                            ->orWhereHas(
                                'creator',
                                static fn (Builder $creatorQuery): Builder => $creatorQuery
                                    ->withTrashed()
                                    ->where('name', 'like', "%{$search}%"),
                            );
                    });
                },
            )
            ->when(
                $itemId > 0,
                static fn (Builder $movementQuery): Builder => $movementQuery
                    ->where('item_id', $itemId),
            )
            ->when(
                $type !== '',
                static fn (Builder $movementQuery): Builder => $movementQuery
                    ->where('movement_type', $type),
            )
            ->when(
                $direction === 'inbound',
                static fn (Builder $movementQuery): Builder => $movementQuery
                    ->where('quantity_in', '>', 0),
            )
            ->when(
                $direction === 'outbound',
                static fn (Builder $movementQuery): Builder => $movementQuery
                    ->where('quantity_out', '>', 0),
            )
            ->when(
                $bounds['from'] !== null,
                static fn (Builder $movementQuery): Builder => $movementQuery
                    ->where(
                        'transaction_date',
                        '>=',
                        $bounds['from'],
                    ),
            )
            ->when(
                $bounds['until'] !== null,
                static fn (Builder $movementQuery): Builder => $movementQuery
                    ->where(
                        'transaction_date',
                        '<=',
                        $bounds['until'],
                    ),
            );
        $summaryQuery = clone $query;

        $selectedItem = $itemId > 0
            ? Item::query()
                ->withTrashed()
                ->with(['category', 'unit'])
                ->find($itemId)
            : null;

        return view('inventory.stock.index', [
            'selectedItem' => $selectedItem,
            'movements' => $query
                ->latest('transaction_date')
                ->latest('id')
                ->paginate(15)
                ->withQueryString(),
            'items' => Item::query()
                ->withTrashed()
                ->whereHas('stockMovements')
                ->orderBy('name')
                ->get([
                    'id',
                    'item_code',
                    'name',
                    'is_active',
                    'deleted_at',
                ]),
            'typeOptions' => StockMovementType::cases(),
            'filters' => compact(
                'search',
                'itemId',
                'type',
                'direction',
                'from',
                'until',
            ),
            'summary' => [
                'transactions' => (clone $summaryQuery)->count(),
                'inbound' => (clone $summaryQuery)
                    ->where('quantity_in', '>', 0)
                    ->count(),
                'outbound' => (clone $summaryQuery)
                    ->where('quantity_out', '>', 0)
                    ->count(),
                'items' => (clone $summaryQuery)
                    ->distinct()
                    ->count('item_id'),
                'imbalances' => (clone $summaryQuery)
                    ->whereRaw(
                        'ABS(stock_after - (stock_before + quantity_in - quantity_out)) >= ?',
                        [0.005],
                    )
                    ->count(),
            ],
            'displayTimezone' => (string) config(
                'simantap.display_timezone',
                'Asia/Jakarta',
            ),
        ]);
    }

    public function downloadExcel(Request $request): BinaryFileResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'item' => ['nullable', 'integer'],
            'type' => [
                'nullable',
                'in:'.implode(',', StockMovementType::values()),
            ],
            'direction' => ['nullable', 'in:inbound,outbound'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'until' => [
                'nullable',
                'date_format:Y-m-d',
                Rule::when(
                    $request->filled('from'),
                    'after_or_equal:from',
                ),
            ],
        ]);

        $search = trim((string) ($validated['q'] ?? ''));
        $itemId = (int) ($validated['item'] ?? 0);
        $type = (string) ($validated['type'] ?? '');
        $direction = (string) ($validated['direction'] ?? '');
        $from = (string) ($validated['from'] ?? '');
        $until = (string) ($validated['until'] ?? '');
        $bounds = DisplayDateRange::utcBounds($from, $until);

        $movements = StockMovement::query()
            ->with([
                'item' => static function (BelongsTo $itemQuery): void {
                    $itemQuery
                        ->withTrashed()
                        ->with(['category', 'unit']);
                },
                'creator' => static function (BelongsTo $creatorQuery): void {
                    $creatorQuery
                        ->withTrashed()
                        ->select(['id', 'name', 'employee_number']);
                },
            ])
            ->when(
                $search !== '',
                static function (
                    Builder $movementQuery,
                ) use ($search): void {
                    $movementQuery->where(function (
                        Builder $nested,
                    ) use ($search): void {
                        $nested
                            ->where(
                                'transaction_number',
                                'like',
                                "%{$search}%",
                            )
                            ->orWhere(
                                'description',
                                'like',
                                "%{$search}%",
                            )
                            ->orWhere(
                                'reference_number',
                                'like',
                                "%{$search}%",
                            )
                            ->orWhereHas(
                                'item',
                                static fn (Builder $itemQuery): Builder => $itemQuery
                                    ->where(function (
                                        Builder $itemSearch,
                                    ) use ($search): void {
                                        $itemSearch
                                            ->where(
                                                'name',
                                                'like',
                                                "%{$search}%",
                                            )
                                            ->orWhere(
                                                'item_code',
                                                'like',
                                                "%{$search}%",
                                            );
                                    }),
                            )
                            ->orWhereHas(
                                'creator',
                                static fn (Builder $creatorQuery): Builder => $creatorQuery
                                    ->withTrashed()
                                    ->where('name', 'like', "%{$search}%"),
                            );
                    });
                },
            )
            ->when(
                $itemId > 0,
                static fn (Builder $movementQuery): Builder => $movementQuery
                    ->where('item_id', $itemId),
            )
            ->when(
                $type !== '',
                static fn (Builder $movementQuery): Builder => $movementQuery
                    ->where('movement_type', $type),
            )
            ->when(
                $direction === 'inbound',
                static fn (Builder $movementQuery): Builder => $movementQuery
                    ->where('quantity_in', '>', 0),
            )
            ->when(
                $direction === 'outbound',
                static fn (Builder $movementQuery): Builder => $movementQuery
                    ->where('quantity_out', '>', 0),
            )
            ->when(
                $bounds['from'] !== null,
                static fn (Builder $movementQuery): Builder => $movementQuery
                    ->where('transaction_date', '>=', $bounds['from']),
            )
            ->when(
                $bounds['until'] !== null,
                static fn (Builder $movementQuery): Builder => $movementQuery
                    ->where('transaction_date', '<=', $bounds['until']),
            )
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();

        $selectedItem = $itemId > 0
            ? Item::query()
                ->withTrashed()
                ->with(['category', 'unit'])
                ->find($itemId)
            : null;

        $displayTimezone = (string) config(
            'simantap.display_timezone',
            'Asia/Jakarta',
        );
        $typeLabel = $type !== ''
            ? StockMovementType::tryFrom($type)?->label() ?? $type
            : 'Semua jenis';
        $directionLabel = match ($direction) {
            'inbound' => 'Stok masuk',
            'outbound' => 'Stok keluar',
            default => 'Semua arah',
        };
        $periodLabel = $this->periodLabel(
            $from,
            $until,
            $displayTimezone,
        );

        $rows = $movements->map(
            static function (
                StockMovement $movement,
            ) use ($displayTimezone): array {
                return [
                    'tanggal' => $movement->transaction_date
                        ->copy()
                        ->timezone($displayTimezone)
                        ->format('d/m/Y H:i').' WIB',
                    'nomor' => $movement->transaction_number,
                    'barang' => sprintf(
                        '%s · %s',
                        $movement->item?->item_code ?: '-',
                        $movement->item?->name ?: 'Barang tidak tersedia',
                    ),
                    'jenis' => $movement->movement_type?->label() ?: '-',
                    'stok_awal' => number_format(
                        (float) $movement->stock_before,
                        2,
                        ',',
                        '.',
                    ),
                    'masuk' => number_format(
                        (float) $movement->quantity_in,
                        2,
                        ',',
                        '.',
                    ),
                    'keluar' => number_format(
                        (float) $movement->quantity_out,
                        2,
                        ',',
                        '.',
                    ),
                    'stok_akhir' => number_format(
                        (float) $movement->stock_after,
                        2,
                        ',',
                        '.',
                    ),
                    'satuan' => $movement->item?->unit?->symbol ?: '-',
                    'petugas' => $movement->creator?->name
                        ?: 'Akun tidak tersedia',
                    'referensi' => $movement->reference_number
                        ?: $movement->transaction_number,
                    'uraian' => $movement->description ?: '-',
                ];
            },
        )->all();

        $report = [
            'title' => 'Kartu Kendali Persediaan',
            'description' => 'Ekspor ledger persediaan sesuai filter pada halaman Kartu Stok.',
            'columns' => [
                ['key' => 'tanggal', 'label' => 'Tanggal WIB'],
                ['key' => 'nomor', 'label' => 'Nomor Transaksi'],
                ['key' => 'barang', 'label' => 'Barang'],
                ['key' => 'jenis', 'label' => 'Jenis Transaksi'],
                ['key' => 'stok_awal', 'label' => 'Stok Awal'],
                ['key' => 'masuk', 'label' => 'Masuk'],
                ['key' => 'keluar', 'label' => 'Keluar'],
                ['key' => 'stok_akhir', 'label' => 'Stok Akhir'],
                ['key' => 'satuan', 'label' => 'Satuan'],
                ['key' => 'petugas', 'label' => 'Petugas'],
                ['key' => 'referensi', 'label' => 'Referensi'],
                ['key' => 'uraian', 'label' => 'Uraian'],
            ],
            'rows' => $rows,
            'summary' => [
                [
                    'label' => 'Transaksi',
                    'value' => number_format(
                        $movements->count(),
                        0,
                        ',',
                        '.',
                    ),
                ],
                [
                    'label' => 'Total masuk',
                    'value' => number_format(
                        (float) $movements->sum('quantity_in'),
                        2,
                        ',',
                        '.',
                    ),
                ],
                [
                    'label' => 'Total keluar',
                    'value' => number_format(
                        (float) $movements->sum('quantity_out'),
                        2,
                        ',',
                        '.',
                    ),
                ],
                [
                    'label' => 'Jenis barang',
                    'value' => number_format(
                        $movements->pluck('item_id')->unique()->count(),
                        0,
                        ',',
                        '.',
                    ),
                ],
                [
                    'label' => 'Validasi saldo',
                    'value' => $movements->every(
                        static fn (StockMovement $movement): bool => $movement
                            ->hasConsistentBalance(),
                    )
                        ? 'Konsisten'
                        : 'Perlu audit',
                ],
            ],
            'filters' => [
                'search' => $search,
                'itemId' => $itemId,
                'movementType' => $type,
                'status' => '',
                'workUnit' => '',
                'from' => $from,
                'until' => $until,
            ],
            'filterRows' => [
                ['Pencarian', $search !== '' ? $search : 'Tidak ada'],
                [
                    'Barang',
                    $selectedItem !== null
                        ? $selectedItem->item_code.' · '.$selectedItem->name
                        : 'Semua barang',
                ],
                ['Jenis Transaksi', $typeLabel],
                ['Arah Pergerakan', $directionLabel],
            ],
            'periodLabel' => $periodLabel,
            'generatedAt' => CarbonImmutable::now($displayTimezone),
            'displayTimezone' => $displayTimezone,
        ];

        return Excel::download(
            new ReportExport($report),
            'kartu-stok-ledger-'.CarbonImmutable::now(
                $displayTimezone,
            )->format('Ymd-His').'.xlsx',
        );
    }

    // PENAMBAHAN FUNGSI DOWNLOAD PDF
    public function downloadPdf(Request $request): Response
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'item' => ['nullable', 'integer'],
            'type' => [
                'nullable',
                'in:'.implode(',', StockMovementType::values()),
            ],
            'direction' => ['nullable', 'in:inbound,outbound'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'until' => [
                'nullable',
                'date_format:Y-m-d',
                Rule::when(
                    $request->filled('from'),
                    'after_or_equal:from',
                ),
            ],
        ]);

        $search = trim((string) ($validated['q'] ?? ''));
        $itemId = (int) ($validated['item'] ?? 0);
        $type = (string) ($validated['type'] ?? '');
        $direction = (string) ($validated['direction'] ?? '');
        $from = (string) ($validated['from'] ?? '');
        $until = (string) ($validated['until'] ?? '');
        $bounds = DisplayDateRange::utcBounds($from, $until);

        $movements = StockMovement::query()
            ->with([
                'item' => static function (BelongsTo $itemQuery): void {
                    $itemQuery
                        ->withTrashed()
                        ->with(['category', 'unit']);
                },
                'creator' => static function (BelongsTo $creatorQuery): void {
                    $creatorQuery
                        ->withTrashed()
                        ->select(['id', 'name', 'employee_number']);
                },
            ])
            ->when(
                $search !== '',
                static function (
                    Builder $movementQuery,
                ) use ($search): void {
                    $movementQuery->where(function (
                        Builder $nested,
                    ) use ($search): void {
                        $nested
                            ->where(
                                'transaction_number',
                                'like',
                                "%{$search}%",
                            )
                            ->orWhere(
                                'description',
                                'like',
                                "%{$search}%",
                            )
                            ->orWhere(
                                'reference_number',
                                'like',
                                "%{$search}%",
                            )
                            ->orWhereHas(
                                'item',
                                static fn (Builder $itemQuery): Builder => $itemQuery
                                    ->where(function (
                                        Builder $itemSearch,
                                    ) use ($search): void {
                                        $itemSearch
                                            ->where(
                                                'name',
                                                'like',
                                                "%{$search}%",
                                            )
                                            ->orWhere(
                                                'item_code',
                                                'like',
                                                "%{$search}%",
                                            );
                                    }),
                            )
                            ->orWhereHas(
                                'creator',
                                static fn (Builder $creatorQuery): Builder => $creatorQuery
                                    ->withTrashed()
                                    ->where('name', 'like', "%{$search}%"),
                            );
                    });
                },
            )
            ->when(
                $itemId > 0,
                static fn (Builder $movementQuery): Builder => $movementQuery
                    ->where('item_id', $itemId),
            )
            ->when(
                $type !== '',
                static fn (Builder $movementQuery): Builder => $movementQuery
                    ->where('movement_type', $type),
            )
            ->when(
                $direction === 'inbound',
                static fn (Builder $movementQuery): Builder => $movementQuery
                    ->where('quantity_in', '>', 0),
            )
            ->when(
                $direction === 'outbound',
                static fn (Builder $movementQuery): Builder => $movementQuery
                    ->where('quantity_out', '>', 0),
            )
            ->when(
                $bounds['from'] !== null,
                static fn (Builder $movementQuery): Builder => $movementQuery
                    ->where('transaction_date', '>=', $bounds['from']),
            )
            ->when(
                $bounds['until'] !== null,
                static fn (Builder $movementQuery): Builder => $movementQuery
                    ->where('transaction_date', '<=', $bounds['until']),
            )
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();

        $selectedItem = $itemId > 0
            ? Item::query()
                ->withTrashed()
                ->with(['category', 'unit'])
                ->find($itemId)
            : null;

        $displayTimezone = (string) config(
            'simantap.display_timezone',
            'Asia/Jakarta',
        );
        
        $periodLabel = $this->periodLabel(
            $from,
            $until,
            $displayTimezone,
        );

        // Render to PDF using card-pdf view template
        // We reuse the existing stockCardData format so the blade template works
        $data = [
            'item' => $selectedItem,
            'movements' => $movements,
            'from' => $from,
            'until' => $until,
            'periodLabel' => $periodLabel,
            'displayTimezone' => $displayTimezone,
            'generatedAt' => CarbonImmutable::now($displayTimezone),
            'totalIn' => (float) $movements->sum('quantity_in'),
            'totalOut' => (float) $movements->sum('quantity_out'),
            'openingBalance' => 0,
            'closingBalance' => 0,
            'balanceConsistent' => true,
        ];

        return Pdf::loadView(
            'inventory.stock.card-pdf',
            $data,
        )
            ->setPaper('a4', 'landscape')
            ->download('kartu-stok-ledger-'.CarbonImmutable::now($displayTimezone)->format('Ymd-His').'.pdf');
    }

    public function card(Request $request, Item $item): View
    {
        return view(
            'inventory.stock.card',
            $this->stockCardData($request, $item),
        );
    }

    public function downloadCardPdf(
        Request $request,
        Item $item,
        DocumentSignatoryService $signatories,
    ): Response {
        $data = $this->stockCardData($request, $item);
        $data['documentSignatories'] = $signatories->for(
            'stock_card',
        );

        $safeCode = preg_replace(
            '/[^A-Za-z0-9_-]+/',
            '-',
            (string) $item->item_code,
        ) ?: 'barang';

        return Pdf::loadView(
            'inventory.stock.card-pdf',
            $data,
        )
            ->setPaper('a4', 'portrait')
            ->download("kartu-stok-{$safeCode}.pdf");
    }

    public function downloadCardExcel(
        Request $request,
        Item $item,
    ): BinaryFileResponse {
        $data = $this->stockCardData($request, $item);
        $safeCode = preg_replace(
            '/[^A-Za-z0-9_-]+/',
            '-',
            (string) $item->item_code,
        ) ?: 'barang';

        $rows = $data['movements']->map(
            static function (
                StockMovement $movement,
            ) use ($data): array {
                return [
                    'tanggal' => $movement->transaction_date
                        ->copy()
                        ->timezone($data['displayTimezone'])
                        ->format('d/m/Y H:i').' WIB',
                    'nomor' => $movement->reference_number
                        ?: $movement->transaction_number,
                    'jenis' => $movement->movement_type?->label() ?: '-',
                    'uraian' => $movement->description ?: '-',
                    'stok_awal' => number_format(
                        (float) $movement->stock_before,
                        2,
                        ',',
                        '.',
                    ),
                    'masuk' => number_format(
                        (float) $movement->quantity_in,
                        2,
                        ',',
                        '.',
                    ),
                    'keluar' => number_format(
                        (float) $movement->quantity_out,
                        2,
                        ',',
                        '.',
                    ),
                    'stok_akhir' => number_format(
                        (float) $movement->stock_after,
                        2,
                        ',',
                        '.',
                    ),
                    'satuan' => $data['item']->unit?->symbol ?: '-',
                ];
            },
        )->all();

        $report = [
            'title' => 'Kartu Stok '.$item->item_code.' · '.$item->name,
            'description' => 'Kartu stok per barang yang dibentuk otomatis dari ledger persediaan.',
            'columns' => [
                ['key' => 'tanggal', 'label' => 'Tanggal WIB'],
                ['key' => 'nomor', 'label' => 'Nomor Dokumen'],
                ['key' => 'jenis', 'label' => 'Jenis Transaksi'],
                ['key' => 'uraian', 'label' => 'Uraian'],
                ['key' => 'stok_awal', 'label' => 'Stok Awal'],
                ['key' => 'masuk', 'label' => 'Masuk'],
                ['key' => 'keluar', 'label' => 'Keluar'],
                ['key' => 'stok_akhir', 'label' => 'Stok Akhir'],
                ['key' => 'satuan', 'label' => 'Satuan'],
            ],
            'rows' => $rows,
            'summary' => [
                [
                    'label' => 'Saldo awal periode',
                    'value' => number_format(
                        $data['openingBalance'],
                        2,
                        ',',
                        '.',
                    ),
                ],
                [
                    'label' => 'Total masuk',
                    'value' => number_format(
                        $data['totalIn'],
                        2,
                        ',',
                        '.',
                    ),
                ],
                [
                    'label' => 'Total keluar',
                    'value' => number_format(
                        $data['totalOut'],
                        2,
                        ',',
                        '.',
                    ),
                ],
                [
                    'label' => 'Saldo akhir',
                    'value' => number_format(
                        $data['closingBalance'],
                        2,
                        ',',
                        '.',
                    ),
                ],
                [
                    'label' => 'Validasi saldo',
                    'value' => $data['balanceConsistent']
                        ? 'Konsisten'
                        : 'Perlu audit',
                ],
            ],
            'filters' => [
                'search' => '',
                'itemId' => (int) $item->getKey(),
                'movementType' => '',
                'status' => '',
                'workUnit' => '',
                'from' => $data['from'],
                'until' => $data['until'],
            ],
            'filterRows' => [
                ['Barang', $item->item_code.' · '.$item->name],
                [
                    'Kategori',
                    $item->category?->name ?: 'Tidak tersedia',
                ],
                [
                    'Satuan',
                    $item->unit?->symbol ?: 'Tidak tersedia',
                ],
            ],
            'periodLabel' => $data['periodLabel'],
            'generatedAt' => $data['generatedAt'],
            'displayTimezone' => $data['displayTimezone'],
        ];

        return Excel::download(
            new ReportExport($report),
            "kartu-stok-{$safeCode}.xlsx",
        );
    }

    /**
     * @return array{
     *     item: Item,
     *     movements: Collection<int, StockMovement>,
     *     from: string,
     *     until: string,
     *     periodLabel: string,
     *     displayTimezone: string,
     *     generatedAt: CarbonImmutable,
     *     openingBalance: float,
     *     closingBalance: float,
     *     totalIn: float,
     *     totalOut: float,
     *     balanceConsistent: bool
     * }
     */
    private function stockCardData(
        Request $request,
        Item $item,
    ): array {
        $filters = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'until' => [
                'nullable',
                'date_format:Y-m-d',
                Rule::when(
                    $request->filled('from'),
                    'after_or_equal:from',
                ),
            ],
        ]);

        $from = (string) ($filters['from'] ?? '');
        $until = (string) ($filters['until'] ?? '');
        $bounds = DisplayDateRange::utcBounds($from, $until);

        $displayTimezone = (string) config(
            'simantap.display_timezone',
            'Asia/Jakarta',
        );

        $item->loadMissing(['category', 'unit']);

        $movementQuery = StockMovement::query()
            ->where('item_id', $item->getKey())
            ->when(
                $bounds['from'] !== null,
                static fn (Builder $query): Builder => $query
                    ->where(
                        'transaction_date',
                        '>=',
                        $bounds['from'],
                    ),
            )
            ->when(
                $bounds['until'] !== null,
                static fn (Builder $query): Builder => $query
                    ->where(
                        'transaction_date',
                        '<=',
                        $bounds['until'],
                    ),
            )
            ->orderBy('transaction_date')
            ->orderBy('id');

        $movements = $movementQuery->get();

        $openingMovement = null;

        if ($bounds['from'] !== null) {
            $openingMovement = StockMovement::query()
                ->where('item_id', $item->getKey())
                ->where(
                    'transaction_date',
                    '<',
                    $bounds['from'],
                )
                ->latest('transaction_date')
                ->latest('id')
                ->first();
        }

        $firstMovement = $movements->first();

        $openingBalance = $openingMovement !== null
            ? (float) $openingMovement->stock_after
            : ($firstMovement !== null
                ? (float) $firstMovement->stock_before
                : 0.0);

        $runningBalance = $openingBalance;

        $balanceConsistent = $openingMovement?->hasConsistentBalance()
            ?? true;

        foreach ($movements as $movement) {
            if (
                abs(
                    (float) $movement->stock_before
                        - $runningBalance,
                ) >= 0.005
                || ! $movement->hasConsistentBalance()
            ) {
                $balanceConsistent = false;
            }

            $runningBalance = (float) $movement->stock_after;
        }

        $formatDate = static fn (string $value): string => CarbonImmutable::parse(
            $value,
            $displayTimezone,
        )->translatedFormat('d F Y');

        $periodLabel = match (true) {
            $from !== '' && $until !== '' => sprintf(
                '%s s.d. %s',
                $formatDate($from),
                $formatDate($until),
            ),
            $from !== '' => 'Mulai '.$formatDate($from),
            $until !== '' => 'Sampai '.$formatDate($until),
            default => 'Seluruh riwayat transaksi',
        };

        return [
            'item' => $item,
            'movements' => $movements,
            'from' => $from,
            'until' => $until,
            'periodLabel' => $periodLabel,
            'displayTimezone' => $displayTimezone,
            'generatedAt' => CarbonImmutable::now(
                $displayTimezone,
            ),
            'openingBalance' => $openingBalance,
            'closingBalance' => $runningBalance,
            'totalIn' => (float) $movements->sum('quantity_in'),
            'totalOut' => (float) $movements->sum('quantity_out'),
            'balanceConsistent' => $balanceConsistent,
        ];
    }

    private function periodLabel(
        string $from,
        string $until,
        string $displayTimezone,
    ): string {
        $formatDate = static fn (string $value): string => CarbonImmutable::parse(
            $value,
            $displayTimezone,
        )->translatedFormat('d F Y');

        return match (true) {
            $from !== '' && $until !== '' => sprintf(
                '%s s.d. %s',
                $formatDate($from),
                $formatDate($until),
            ),
            $from !== '' => 'Mulai '.$formatDate($from),
            $until !== '' => 'Sampai '.$formatDate($until),
            default => 'Seluruh riwayat transaksi',
        };
    }

    public function show(StockMovement $stockMovement): View
    {
        $stockMovement->load([
            'item.category',
            'item.unit',
            'creator:id,name,employee_number',
            'reference',
        ]);
        $stockMovement->loadMorph('reference', [
            InventoryReceipt::class => [
                'creator:id,name',
                'postedBy:id,name',
            ],
            InventoryRequest::class => [
                'requester:id,name,employee_number',
                'deliverer:id,name',
            ],
            Item::class => ['category', 'unit'],
            StockAdjustment::class => [
                'creator:id,name',
                'postedBy:id,name',
            ],
        ]);

        $previousMovement = $this->adjacentMovement(
            $stockMovement,
            previous: true,
        );
        $nextMovement = $this->adjacentMovement(
            $stockMovement,
            previous: false,
        );
        $previousIsConsistent = $previousMovement === null
            || abs(
                (float) $previousMovement->stock_after
                    - (float) $stockMovement->stock_before,
            ) < 0.005;
        $nextIsConsistent = $nextMovement === null
            || abs(
                (float) $stockMovement->stock_after
                    - (float) $nextMovement->stock_before,
            ) < 0.005;

        return view('inventory.stock.show', [
            'movement' => $stockMovement,
            'source' => $this->sourceDetails($stockMovement),
            'previousMovement' => $previousMovement,
            'nextMovement' => $nextMovement,
            'integrity' => [
                'formula' => $stockMovement->hasConsistentBalance(),
                'previous' => $previousIsConsistent,
                'next' => $nextIsConsistent,
                'overall' => $stockMovement->hasConsistentBalance()
                    && $previousIsConsistent
                    && $nextIsConsistent,
            ],
            'displayTimezone' => (string) config(
                'simantap.display_timezone',
                'Asia/Jakarta',
            ),
        ]);
    }

    private function adjacentMovement(
        StockMovement $movement,
        bool $previous,
    ): ?StockMovement {
        $comparison = $previous ? '<' : '>';
        $direction = $previous ? 'desc' : 'asc';

        return StockMovement::query()
            ->where('item_id', $movement->item_id)
            ->where(function (Builder $query) use (
                $comparison,
                $movement,
            ): void {
                $query
                    ->where(
                        'transaction_date',
                        $comparison,
                        $movement->transaction_date,
                    )
                    ->orWhere(function (Builder $sameTime) use (
                        $comparison,
                        $movement,
                    ): void {
                        $sameTime
                            ->where(
                                'transaction_date',
                                $movement->transaction_date,
                            )
                            ->where('id', $comparison, $movement->getKey());
                    });
            })
            ->orderBy('transaction_date', $direction)
            ->orderBy('id', $direction)
            ->first();
    }

    /**
     * @return array{
     *     type: string,
     *     number: string,
     *     url: string|null,
     *     employee: string|null,
     *     status: string|null
     * }
     */
    private function sourceDetails(StockMovement $movement): array
    {
        $reference = $movement->reference;

        return match (true) {
            $reference instanceof InventoryReceipt => [
                'type' => 'Barang Masuk',
                'number' => $reference->receipt_number,
                'url' => $reference->trashed()
                    ? null
                    : route('inventory-receipts.show', $reference),
                'employee' => null,
                'status' => $reference->status->label(),
            ],
            $reference instanceof InventoryRequest => [
                'type' => 'Permintaan Barang',
                'number' => $reference->request_number,
                'url' => $reference->trashed()
                    ? null
                    : route('inventory-requests.show', $reference),
                'employee' => $reference->requester_name_snapshot,
                'status' => $reference->status->label(),
            ],
            $reference instanceof StockAdjustment => [
                'type' => 'Penyesuaian Stok',
                'number' => $reference->adjustment_number,
                'url' => $reference->trashed()
                    ? null
                    : route('stock-adjustments.show', $reference),
                'employee' => null,
                'status' => $reference->status->label(),
            ],
            $reference instanceof Item => [
                'type' => 'Stok Awal',
                'number' => $reference->item_code,
                'url' => $reference->trashed()
                    ? null
                    : route('items.show', $reference),
                'employee' => null,
                'status' => $reference->is_active && ! $reference->trashed()
                    ? 'Aktif'
                    : 'Nonaktif',
            ],
            default => [
                'type' => 'Sumber Sistem',
                'number' => $movement->reference_number
                    ?: $movement->transaction_number,
                'url' => null,
                'employee' => null,
                'status' => null,
            ],
        };
    }
}