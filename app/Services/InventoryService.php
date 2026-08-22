<?php

namespace App\Services;

use App\Enums\InventoryReceiptStatus;
use App\Enums\StockAdjustmentStatus;
use App\Enums\StockMovementType;
use App\Models\InventoryReceipt;
use App\Models\InventoryReceiptItem;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class InventoryService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly DocumentNumberService $documentNumberService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function createItem(
        array $data,
        User $actor,
        ?Request $request = null,
    ): Item {
        $imagePath = $this->storeImage($data['image'] ?? null);

        try {
            return DB::transaction(function () use (
                $data,
                $actor,
                $request,
                $imagePath,
            ): Item {
                $initialStock = $this->quantity(
                    $data['initial_stock'],
                );

                $item = Item::query()->create([
                    'item_code' => $data['item_code'],
                    'category_id' => $data['category_id'],
                    'unit_id' => $data['unit_id'],
                    'name' => $data['name'],
                    'description' => $data['description'] ?? null,
                    'current_stock' => $initialStock,
                    'reserved_stock' => 0,
                    'minimum_stock' => $this->quantity(
                        $data['minimum_stock'],
                    ),
                    'storage_location' => $data['storage_location']
                        ?? null,
                    'image_path' => $imagePath,
                    'is_active' => $data['is_active'],
                ]);

                if ($initialStock > 0) {
                    $displayNow = CarbonImmutable::now(
                        $this->displayTimezone(),
                    );

                    $this->createStockMovement(
                        item: $item,
                        type: StockMovementType::InitialStock,
                        transactionNumber: $this
                            ->documentNumberService
                            ->next('initial_stock', $displayNow),
                        quantityIn: $initialStock,
                        quantityOut: 0,
                        stockBefore: 0,
                        stockAfter: $initialStock,
                        transactionDate: $displayNow->utc(),
                        description: 'Stok awal saat master barang dibuat.',
                        actor: $actor,
                        reference: $item,
                    );
                }

                $this->auditLogger->log(
                    event: 'item_created',
                    module: 'inventory',
                    auditable: $item,
                    newValues: [
                        ...$item->only([
                            'item_code',
                            'category_id',
                            'unit_id',
                            'name',
                            'minimum_stock',
                            'storage_location',
                            'is_active',
                        ]),
                        'initial_stock' => $initialStock,
                    ],
                    request: $request,
                    actorId: $actor->getKey(),
                );

                return $item->load(['category', 'unit']);
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
    public function updateItem(
        Item $item,
        array $data,
        User $actor,
        ?Request $request = null,
    ): Item {
        $newImagePath = $this->storeImage($data['image'] ?? null);
        $oldImagePath = $item->image_path;

        try {
            $updatedItem = DB::transaction(function () use (
                $item,
                $data,
                $actor,
                $request,
                $newImagePath,
            ): Item {
                $lockedItem = Item::query()
                    ->whereKey($item->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();
                $trackedAttributes = [
                    'item_code',
                    'category_id',
                    'unit_id',
                    'name',
                    'description',
                    'minimum_stock',
                    'storage_location',
                    'image_path',
                    'is_active',
                ];
                $oldValues = $lockedItem->only($trackedAttributes);

                $lockedItem->fill([
                    'item_code' => $data['item_code'],
                    'category_id' => $data['category_id'],
                    'unit_id' => $data['unit_id'],
                    'name' => $data['name'],
                    'description' => $data['description'] ?? null,
                    'minimum_stock' => $this->quantity(
                        $data['minimum_stock'],
                    ),
                    'storage_location' => $data['storage_location']
                        ?? null,
                    'is_active' => $data['is_active'],
                ]);

                if ($newImagePath !== null) {
                    $lockedItem->image_path = $newImagePath;
                } elseif (($data['remove_image'] ?? false) === true) {
                    $lockedItem->image_path = null;
                }

                $lockedItem->save();

                $changed = array_keys($lockedItem->getChanges());
                $newValues = $lockedItem->only($changed);

                if ($newValues !== []) {
                    $this->auditLogger->log(
                        event: 'item_updated',
                        module: 'inventory',
                        auditable: $lockedItem,
                        oldValues: collect($oldValues)
                            ->only(array_keys($newValues))
                            ->all(),
                        newValues: $newValues,
                        request: $request,
                        actorId: $actor->getKey(),
                    );
                }

                return $lockedItem->load(['category', 'unit']);
            }, 3);

            if (
                $oldImagePath !== null
                && $oldImagePath !== $updatedItem->image_path
            ) {
                Storage::disk('public')->delete($oldImagePath);
            }

            return $updatedItem;
        } catch (Throwable $throwable) {
            if ($newImagePath !== null) {
                Storage::disk('public')->delete($newImagePath);
            }

            throw $throwable;
        }
    }

    public function setItemActive(
        Item $item,
        bool $isActive,
        User $actor,
        ?Request $request = null,
    ): Item {
        return DB::transaction(function () use (
            $item,
            $isActive,
            $actor,
            $request,
        ): Item {
            $lockedItem = Item::query()
                ->whereKey($item->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (
                ! $isActive
                && (float) $lockedItem->reserved_stock > 0
            ) {
                throw ValidationException::withMessages([
                    'item' => 'Barang dengan stok yang masih direservasi tidak dapat dinonaktifkan.',
                ]);
            }

            $oldStatus = $lockedItem->is_active;
            $lockedItem->is_active = $isActive;
            $lockedItem->save();

            if ($oldStatus !== $isActive) {
                $this->auditLogger->log(
                    event: $isActive
                        ? 'item_activated'
                        : 'item_deactivated',
                    module: 'inventory',
                    auditable: $lockedItem,
                    oldValues: ['is_active' => $oldStatus],
                    newValues: ['is_active' => $isActive],
                    request: $request,
                    actorId: $actor->getKey(),
                );
            }

            return $lockedItem;
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createCategory(
        array $data,
        User $actor,
        ?Request $request = null,
    ): ItemCategory {
        return DB::transaction(function () use (
            $data,
            $actor,
            $request,
        ): ItemCategory {
            $category = ItemCategory::query()->create($data);

            $this->auditLogger->log(
                event: 'item_category_created',
                module: 'inventory',
                auditable: $category,
                newValues: $category->only([
                    'name',
                    'description',
                    'is_active',
                ]),
                request: $request,
                actorId: $actor->getKey(),
            );

            return $category;
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateCategory(
        ItemCategory $category,
        array $data,
        User $actor,
        ?Request $request = null,
    ): ItemCategory {
        return $this->updateReferenceMaster(
            model: $category,
            data: $data,
            actor: $actor,
            event: 'item_category_updated',
            request: $request,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createUnit(
        array $data,
        User $actor,
        ?Request $request = null,
    ): Unit {
        return DB::transaction(function () use (
            $data,
            $actor,
            $request,
        ): Unit {
            $unit = Unit::query()->create($data);

            $this->auditLogger->log(
                event: 'unit_created',
                module: 'inventory',
                auditable: $unit,
                newValues: $unit->only([
                    'name',
                    'symbol',
                    'is_active',
                ]),
                request: $request,
                actorId: $actor->getKey(),
            );

            return $unit;
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateUnit(
        Unit $unit,
        array $data,
        User $actor,
        ?Request $request = null,
    ): Unit {
        return $this->updateReferenceMaster(
            model: $unit,
            data: $data,
            actor: $actor,
            event: 'unit_updated',
            request: $request,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createReceipt(
        array $data,
        User $actor,
        ?Request $request = null,
    ): InventoryReceipt {
        return DB::transaction(function () use (
            $data,
            $actor,
            $request,
        ): InventoryReceipt {
            [$displayDate, $storedDate] = $this->transactionDates(
                (string) $data['receipt_date'],
            );

            $receiptNumber = trim((string) ($data['receipt_number'] ?? ''));

            if ($receiptNumber === '') {
                $receiptNumber = $this
                    ->documentNumberService
                    ->next('stock_in', $displayDate);
            }

            $receipt = InventoryReceipt::query()->create([
                'receipt_number' => $receiptNumber,
                'receipt_date' => $storedDate,
                'source' => $data['source'],
                'reference_number' => $data['reference_number'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => InventoryReceiptStatus::Draft,
                'created_by' => $actor->getKey(),
            ]);

            $this->replaceReceiptItems(
                $receipt,
                $data['items'],
            );

            $this->auditLogger->log(
                event: 'inventory_receipt_created',
                module: 'inventory',
                auditable: $receipt,
                newValues: [
                    'receipt_number' => $receipt->receipt_number,
                    'receipt_date' => $storedDate->toIso8601String(),
                    'source' => $receipt->source,
                    'status' => $receipt->status->value,
                    'item_count' => count($data['items']),
                ],
                request: $request,
                actorId: $actor->getKey(),
            );

            return $receipt->load(['items.item.unit', 'creator']);
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateReceipt(
        InventoryReceipt $receipt,
        array $data,
        User $actor,
        ?Request $request = null,
    ): InventoryReceipt {
        return DB::transaction(function () use (
            $receipt,
            $data,
            $actor,
            $request,
        ): InventoryReceipt {
            $lockedReceipt = InventoryReceipt::query()
                ->whereKey($receipt->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $this->ensureDraft(
                $lockedReceipt->status,
                'Barang masuk',
            );
            [$displayDate, $storedDate] = $this->transactionDates(
                (string) $data['receipt_date'],
            );
            $receiptNumber = trim((string) ($data['receipt_number'] ?? ''));

            if ($receiptNumber === '') {
                $receiptNumber = $this
                    ->documentNumberService
                    ->next('stock_in', $displayDate);
            }

            $oldValues = [
                ...$lockedReceipt->only([
                    'receipt_number',
                    'receipt_date',
                    'source',
                    'reference_number',
                    'notes',
                ]),
                'item_count' => $lockedReceipt->items()->count(),
            ];

            $lockedReceipt->update([
                'receipt_number' => $receiptNumber,
                'receipt_date' => $storedDate,
                'source' => $data['source'],
                'reference_number' => $data['reference_number'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
            $this->replaceReceiptItems(
                $lockedReceipt,
                $data['items'],
            );

            $this->auditLogger->log(
                event: 'inventory_receipt_updated',
                module: 'inventory',
                auditable: $lockedReceipt,
                oldValues: $oldValues,
                newValues: [
                    ...$lockedReceipt->only([
                        'receipt_number',
                        'receipt_date',
                        'source',
                        'reference_number',
                        'notes',
                    ]),
                    'item_count' => count($data['items']),
                ],
                request: $request,
                actorId: $actor->getKey(),
            );

            return $lockedReceipt->load([
                'items.item.unit',
                'creator',
            ]);
        }, 3);
    }

    public function postReceipt(
        InventoryReceipt $receipt,
        User $actor,
        ?Request $request = null,
    ): InventoryReceipt {
        return DB::transaction(function () use (
            $receipt,
            $actor,
            $request,
        ): InventoryReceipt {
            $lockedReceipt = InventoryReceipt::query()
                ->whereKey($receipt->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $this->ensureDraft(
                $lockedReceipt->status,
                'Barang masuk',
            );
            $lines = InventoryReceiptItem::query()
                ->where('inventory_receipt_id', $lockedReceipt->id)
                ->orderBy('item_id')
                ->lockForUpdate()
                ->get();

            if ($lines->isEmpty()) {
                throw ValidationException::withMessages([
                    'receipt' => 'Barang masuk tidak memiliki detail barang.',
                ]);
            }

            foreach ($lines as $index => $line) {
                $item = Item::query()
                    ->whereKey($line->item_id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $stockBefore = $this->quantity($item->current_stock);
                $quantity = $this->quantity($line->quantity);
                $stockAfter = $this->quantity(
                    $stockBefore + $quantity,
                );

                $item->current_stock = $stockAfter;
                $item->save();

                $this->createStockMovement(
                    item: $item,
                    type: StockMovementType::StockIn,
                    transactionNumber: $this->lineNumber(
                        $lockedReceipt->receipt_number,
                        $index + 1,
                    ),
                    quantityIn: $quantity,
                    quantityOut: 0,
                    stockBefore: $stockBefore,
                    stockAfter: $stockAfter,
                    transactionDate: $lockedReceipt->receipt_date,
                    description: "Barang masuk dari {$lockedReceipt->source}.",
                    actor: $actor,
                    reference: $lockedReceipt,
                );
            }

            $lockedReceipt->update([
                'status' => InventoryReceiptStatus::Posted,
                'posted_by' => $actor->getKey(),
                'posted_at' => now(),
            ]);

            $this->auditLogger->log(
                event: 'inventory_receipt_posted',
                module: 'inventory',
                auditable: $lockedReceipt,
                oldValues: [
                    'status' => InventoryReceiptStatus::Draft->value,
                ],
                newValues: [
                    'status' => InventoryReceiptStatus::Posted->value,
                    'posted_by' => $actor->getKey(),
                    'item_count' => $lines->count(),
                    'total_quantity' => $this->sum(
                        $lines,
                        'quantity',
                    ),
                ],
                request: $request,
                actorId: $actor->getKey(),
            );

            return $lockedReceipt->load([
                'items.item.unit',
                'creator',
                'postedBy',
            ]);
        }, 3);
    }

    public function cancelReceipt(
        InventoryReceipt $receipt,
        string $reason,
        User $actor,
        ?Request $request = null,
    ): InventoryReceipt {
        return $this->cancelDocument(
            document: $receipt,
            reason: $reason,
            actor: $actor,
            event: 'inventory_receipt_cancelled',
            request: $request,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createAdjustment(
        array $data,
        User $actor,
        ?Request $request = null,
    ): StockAdjustment {
        return DB::transaction(function () use (
            $data,
            $actor,
            $request,
        ): StockAdjustment {
            [$displayDate, $storedDate] = $this->transactionDates(
                (string) $data['adjustment_date'],
            );

            $adjustment = StockAdjustment::query()->create([
                'adjustment_number' => $this
                    ->documentNumberService
                    ->next('stock_adjustment', $displayDate),
                'adjustment_date' => $storedDate,
                'reason' => $data['reason'],
                'notes' => $data['notes'] ?? null,
                'status' => StockAdjustmentStatus::Draft,
                'created_by' => $actor->getKey(),
            ]);

            $this->replaceAdjustmentItems(
                $adjustment,
                $data['items'],
            );

            $this->auditLogger->log(
                event: 'stock_adjustment_created',
                module: 'inventory',
                auditable: $adjustment,
                newValues: [
                    'adjustment_number' => $adjustment
                        ->adjustment_number,
                    'adjustment_date' => $storedDate
                        ->toIso8601String(),
                    'status' => $adjustment->status->value,
                    'item_count' => count($data['items']),
                ],
                request: $request,
                actorId: $actor->getKey(),
            );

            return $adjustment->load([
                'items.item.unit',
                'creator',
            ]);
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateAdjustment(
        StockAdjustment $adjustment,
        array $data,
        User $actor,
        ?Request $request = null,
    ): StockAdjustment {
        return DB::transaction(function () use (
            $adjustment,
            $data,
            $actor,
            $request,
        ): StockAdjustment {
            $lockedAdjustment = StockAdjustment::query()
                ->whereKey($adjustment->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $this->ensureDraft(
                $lockedAdjustment->status,
                'Penyesuaian stok',
            );
            [, $storedDate] = $this->transactionDates(
                (string) $data['adjustment_date'],
            );
            $oldValues = [
                ...$lockedAdjustment->only([
                    'adjustment_date',
                    'reason',
                    'notes',
                ]),
                'item_count' => $lockedAdjustment->items()->count(),
            ];

            $lockedAdjustment->update([
                'adjustment_date' => $storedDate,
                'reason' => $data['reason'],
                'notes' => $data['notes'] ?? null,
            ]);
            $this->replaceAdjustmentItems(
                $lockedAdjustment,
                $data['items'],
            );

            $this->auditLogger->log(
                event: 'stock_adjustment_updated',
                module: 'inventory',
                auditable: $lockedAdjustment,
                oldValues: $oldValues,
                newValues: [
                    ...$lockedAdjustment->only([
                        'adjustment_date',
                        'reason',
                        'notes',
                    ]),
                    'item_count' => count($data['items']),
                ],
                request: $request,
                actorId: $actor->getKey(),
            );

            return $lockedAdjustment->load([
                'items.item.unit',
                'creator',
            ]);
        }, 3);
    }

    public function postAdjustment(
        StockAdjustment $adjustment,
        User $actor,
        ?Request $request = null,
    ): StockAdjustment {
        return DB::transaction(function () use (
            $adjustment,
            $actor,
            $request,
        ): StockAdjustment {
            $lockedAdjustment = StockAdjustment::query()
                ->whereKey($adjustment->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $this->ensureDraft(
                $lockedAdjustment->status,
                'Penyesuaian stok',
            );
            $lines = StockAdjustmentItem::query()
                ->where(
                    'stock_adjustment_id',
                    $lockedAdjustment->id,
                )
                ->orderBy('item_id')
                ->lockForUpdate()
                ->get();

            if ($lines->isEmpty()) {
                throw ValidationException::withMessages([
                    'adjustment' => 'Penyesuaian stok tidak memiliki detail barang.',
                ]);
            }

            $items = Item::query()
                ->whereIn('id', $lines->pluck('item_id'))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($lines as $line) {
                $item = $items->get($line->item_id);

                if (
                    ! $item instanceof Item
                    || $this->quantity($item->current_stock)
                        !== $this->quantity($line->system_quantity)
                ) {
                    throw ValidationException::withMessages([
                        'adjustment' => "Stok {$line->item_name_snapshot} telah berubah sejak draft dibuat. Perbarui draft sebelum posting.",
                    ]);
                }
            }

            foreach ($lines as $index => $line) {
                /** @var Item $item */
                $item = $items->get($line->item_id);
                $stockBefore = $this->quantity($item->current_stock);
                $stockAfter = $this->quantity(
                    $line->physical_quantity,
                );
                $difference = $this->quantity(
                    $stockAfter - $stockBefore,
                );

                if ($difference === 0.0) {
                    continue;
                }

                $item->current_stock = $stockAfter;
                $item->save();
                $isInbound = $difference > 0;

                $this->createStockMovement(
                    item: $item,
                    type: $isInbound
                        ? StockMovementType::AdjustmentIn
                        : StockMovementType::AdjustmentOut,
                    transactionNumber: $this->lineNumber(
                        $lockedAdjustment->adjustment_number,
                        $index + 1,
                    ),
                    quantityIn: $isInbound ? $difference : 0,
                    quantityOut: $isInbound ? 0 : abs($difference),
                    stockBefore: $stockBefore,
                    stockAfter: $stockAfter,
                    transactionDate: $lockedAdjustment->adjustment_date,
                    description: $lockedAdjustment->reason,
                    actor: $actor,
                    reference: $lockedAdjustment,
                );
            }

            $lockedAdjustment->update([
                'status' => StockAdjustmentStatus::Posted,
                'posted_by' => $actor->getKey(),
                'posted_at' => now(),
            ]);

            $this->auditLogger->log(
                event: 'stock_adjustment_posted',
                module: 'inventory',
                auditable: $lockedAdjustment,
                oldValues: [
                    'status' => StockAdjustmentStatus::Draft->value,
                ],
                newValues: [
                    'status' => StockAdjustmentStatus::Posted->value,
                    'posted_by' => $actor->getKey(),
                    'item_count' => $lines->count(),
                    'net_difference' => $this->sum(
                        $lines,
                        'difference_quantity',
                    ),
                ],
                request: $request,
                actorId: $actor->getKey(),
            );

            return $lockedAdjustment->load([
                'items.item.unit',
                'creator',
                'postedBy',
            ]);
        }, 3);
    }

    public function cancelAdjustment(
        StockAdjustment $adjustment,
        string $reason,
        User $actor,
        ?Request $request = null,
    ): StockAdjustment {
        return $this->cancelDocument(
            document: $adjustment,
            reason: $reason,
            actor: $actor,
            event: 'stock_adjustment_cancelled',
            request: $request,
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function replaceReceiptItems(
        InventoryReceipt $receipt,
        array $items,
    ): void {
        $receipt->items()->delete();
        $masterItems = $this->lockedActiveItems($items);

        foreach ($items as $line) {
            /** @var Item $item */
            $item = $masterItems->get((int) $line['item_id']);

            $receipt->items()->create([
                'item_id' => $item->getKey(),
                'item_code_snapshot' => $item->item_code,
                'item_name_snapshot' => $item->name,
                'unit_snapshot' => $item->unit->symbol,
                'quantity' => $this->quantity($line['quantity']),
                'unit_cost' => null,
                'notes' => $line['notes'] ?? null,
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function replaceAdjustmentItems(
        StockAdjustment $adjustment,
        array $items,
    ): void {
        $adjustment->items()->delete();
        $masterItems = $this->lockedActiveItems($items);

        foreach ($items as $line) {
            /** @var Item $item */
            $item = $masterItems->get((int) $line['item_id']);
            $systemQuantity = $this->quantity(
                $item->current_stock,
            );
            $physicalQuantity = $this->quantity(
                $line['physical_quantity'],
            );

            $adjustment->items()->create([
                'item_id' => $item->getKey(),
                'item_code_snapshot' => $item->item_code,
                'item_name_snapshot' => $item->name,
                'unit_snapshot' => $item->unit->symbol,
                'system_quantity' => $systemQuantity,
                'physical_quantity' => $physicalQuantity,
                'difference_quantity' => $this->quantity(
                    $physicalQuantity - $systemQuantity,
                ),
                'notes' => $line['notes'] ?? null,
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return Collection<int, Item>
     */
    private function lockedActiveItems(array $items): Collection
    {
        $ids = collect($items)
            ->pluck('item_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->sort()
            ->values();
        $masterItems = Item::query()
            ->with('unit')
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        if ($masterItems->count() !== $ids->unique()->count()) {
            throw ValidationException::withMessages([
                'items' => 'Salah satu barang tidak tersedia atau sudah dinonaktifkan.',
            ]);
        }

        return $masterItems;
    }

    private function createStockMovement(
        Item $item,
        StockMovementType $type,
        string $transactionNumber,
        float $quantityIn,
        float $quantityOut,
        float $stockBefore,
        float $stockAfter,
        mixed $transactionDate,
        string $description,
        User $actor,
        Item|InventoryReceipt|StockAdjustment $reference,
    ): StockMovement {
        return StockMovement::query()->create([
            'transaction_number' => $transactionNumber,
            'movement_number' => $transactionNumber,
            'reference_number' => $this->stockReferenceNumber(
                $reference,
            ),
            'item_id' => $item->getKey(),
            'movement_type' => $type,
            'reference_type' => $reference->getMorphClass(),
            'reference_id' => $reference->getKey(),
            'quantity_in' => $quantityIn,
            'quantity_out' => $quantityOut,
            'stock_before' => $stockBefore,
            'stock_after' => $stockAfter,
            'transaction_date' => $transactionDate,
            'description' => $description,
            'created_by' => $actor->getKey(),
        ]);
    }

    private function stockReferenceNumber(
        Item|InventoryReceipt|StockAdjustment $reference,
    ): string {
        return match (true) {
            $reference instanceof InventoryReceipt => (string) $reference->receipt_number,
            $reference instanceof StockAdjustment => (string) $reference->adjustment_number,
            default => (string) $reference->item_code,
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function updateReferenceMaster(
        ItemCategory|Unit $model,
        array $data,
        User $actor,
        string $event,
        ?Request $request = null,
    ): ItemCategory|Unit {
        return DB::transaction(function () use (
            $model,
            $data,
            $actor,
            $event,
            $request,
        ): ItemCategory|Unit {
            $lockedModel = $model::query()
                ->whereKey($model->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $oldValues = $lockedModel->only(array_keys($data));
            $lockedModel->fill($data);
            $lockedModel->save();

            $this->auditLogger->log(
                event: $event,
                module: 'inventory',
                auditable: $lockedModel,
                oldValues: $oldValues,
                newValues: $lockedModel->only(array_keys($data)),
                request: $request,
                actorId: $actor->getKey(),
            );

            return $lockedModel;
        }, 3);
    }

    /**
     * @template TDocument of InventoryReceipt|StockAdjustment
     *
     * @param  TDocument  $document
     * @return TDocument
     */
    private function cancelDocument(
        InventoryReceipt|StockAdjustment $document,
        string $reason,
        User $actor,
        string $event,
        ?Request $request = null,
    ): InventoryReceipt|StockAdjustment {
        return DB::transaction(function () use (
            $document,
            $reason,
            $actor,
            $event,
            $request,
        ): InventoryReceipt|StockAdjustment {
            $lockedDocument = $document::query()
                ->whereKey($document->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $this->ensureDraft(
                $lockedDocument->status,
                'Transaksi',
            );
            $draftStatus = $lockedDocument instanceof InventoryReceipt
                ? InventoryReceiptStatus::Draft
                : StockAdjustmentStatus::Draft;
            $cancelledStatus = $lockedDocument instanceof InventoryReceipt
                ? InventoryReceiptStatus::Cancelled
                : StockAdjustmentStatus::Cancelled;

            $lockedDocument->update([
                'status' => $cancelledStatus,
                'cancelled_by' => $actor->getKey(),
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);

            $this->auditLogger->log(
                event: $event,
                module: 'inventory',
                auditable: $lockedDocument,
                oldValues: [
                    'status' => $draftStatus->value,
                ],
                newValues: [
                    'status' => $cancelledStatus->value,
                    'cancellation_reason' => $reason,
                ],
                request: $request,
                actorId: $actor->getKey(),
            );

            return $lockedDocument;
        }, 3);
    }

    private function ensureDraft(
        InventoryReceiptStatus|StockAdjustmentStatus $status,
        string $documentLabel,
    ): void {
        if ($status->value !== InventoryReceiptStatus::Draft->value) {
            throw ValidationException::withMessages([
                'transaction' => "{$documentLabel} hanya dapat diubah saat berstatus Draft.",
            ]);
        }
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function transactionDates(string $date): array
    {
        $displayDate = CarbonImmutable::parse(
            $date,
            $this->displayTimezone(),
        );

        return [$displayDate, $displayDate->utc()];
    }

    private function displayTimezone(): string
    {
        return (string) config(
            'simantap.display_timezone',
            'Asia/Jakarta',
        );
    }

    private function lineNumber(
        string $documentNumber,
        int $position,
    ): string {
        return sprintf('%s-%03d', $documentNumber, $position);
    }

    private function quantity(mixed $value): float
    {
        return round((float) $value, 2);
    }

    /**
     * @param  Collection<int, mixed>  $lines
     */
    private function sum(Collection $lines, string $field): float
    {
        return $this->quantity(
            $lines->sum(
                static fn (mixed $line): float => (float) $line->{$field},
            ),
        );
    }

    private function storeImage(mixed $image): ?string
    {
        if (! $image instanceof UploadedFile) {
            return null;
        }

        $path = $image->store('items', 'public');

        if ($path === false) {
            throw new RuntimeException('Foto barang gagal disimpan.');
        }

        return $path;
    }
}
