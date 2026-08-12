<?php

namespace App\Services;

use App\Enums\DigitalSignaturePurpose;
use App\Enums\DocumentType;
use App\Enums\InventoryRequestStatus;
use App\Enums\StockMovementType;
use App\Models\DigitalSignature;
use App\Models\InventoryRequest;
use App\Models\InventoryRequestItem;
use App\Models\InventoryRequestStatusHistory;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\SignaturePayload;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class InventoryRequestService
{
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
    ): InventoryRequest {
        return DB::transaction(function () use (
            $data,
            $actor,
            $httpRequest,
        ): InventoryRequest {
            $requestDate = $this->requestDate($data['request_date']);
            $inventoryRequest = InventoryRequest::query()->create([
                'request_number' => $this->documentNumberService->next(
                    DocumentType::InventoryRequest,
                    $requestDate,
                ),
                'requested_by' => $actor->getKey(),
                'employee_number_snapshot' => $actor->employee_number,
                'requester_name_snapshot' => $actor->name,
                'work_unit_snapshot' => $actor->work_unit,
                'request_date' => $requestDate->utc(),
                'purpose' => $data['purpose'],
                'notes' => $data['notes'] ?? null,
                'status' => InventoryRequestStatus::Draft,
            ]);

            $this->replaceRequestItems(
                $inventoryRequest,
                $data['items'],
            );
            $this->recordStatus(
                $inventoryRequest,
                null,
                InventoryRequestStatus::Draft,
                'Draft permintaan dibuat.',
                $actor,
            );
            $this->auditLogger->log(
                event: 'inventory_request_created',
                module: 'inventory_request',
                auditable: $inventoryRequest,
                newValues: [
                    'request_number' => $inventoryRequest->request_number,
                    'status' => $inventoryRequest->status->value,
                    'item_count' => count($data['items']),
                ],
                request: $httpRequest,
                actorId: $actor->getKey(),
            );

            return $this->loadRequest($inventoryRequest);
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateDraft(
        InventoryRequest $inventoryRequest,
        array $data,
        User $actor,
        ?Request $httpRequest = null,
    ): InventoryRequest {
        return DB::transaction(function () use (
            $inventoryRequest,
            $data,
            $actor,
            $httpRequest,
        ): InventoryRequest {
            $locked = $this->lockRequest($inventoryRequest);
            $this->requireStatus(
                $locked,
                [
                    InventoryRequestStatus::Draft,
                    InventoryRequestStatus::RevisionRequired,
                ],
                'Hanya draft atau permintaan yang perlu diperbaiki yang dapat diubah.',
            );
            $oldValues = $locked->only([
                'request_date',
                'purpose',
                'notes',
            ]);

            $locked->forceFill([
                'request_date' => $this
                    ->requestDate($data['request_date'])
                    ->utc(),
                'purpose' => $data['purpose'],
                'notes' => $data['notes'] ?? null,
            ])->save();

            $this->replaceRequestItems($locked, $data['items']);
            $this->auditLogger->log(
                event: 'inventory_request_updated',
                module: 'inventory_request',
                auditable: $locked,
                oldValues: $oldValues,
                newValues: [
                    ...$locked->only([
                        'request_date',
                        'purpose',
                        'notes',
                    ]),
                    'item_count' => count($data['items']),
                ],
                request: $httpRequest,
                actorId: $actor->getKey(),
            );

            return $this->loadRequest($locked);
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function submit(
        InventoryRequest $inventoryRequest,
        array $data,
        User $actor,
        ?Request $httpRequest = null,
    ): InventoryRequest {
        $signatureFile = $this->storeSignatureFile(
            $inventoryRequest,
            (string) $data['signature_data'],
        );

        try {
            return DB::transaction(function () use (
                $inventoryRequest,
                $actor,
                $httpRequest,
                $signatureFile,
            ): InventoryRequest {
                $locked = $this->lockRequest($inventoryRequest);
                $this->requireStatus(
                    $locked,
                    [
                        InventoryRequestStatus::Draft,
                        InventoryRequestStatus::RevisionRequired,
                    ],
                    'Permintaan pada status ini tidak dapat diajukan.',
                );

                if (! $locked->items()->exists()) {
                    throw ValidationException::withMessages([
                        'items' => 'Permintaan harus memiliki minimal satu barang.',
                    ]);
                }

                $previousStatus = $locked->status;
                $locked->forceFill([
                    'status' => InventoryRequestStatus::Submitted,
                    'submitted_at' => now(),
                    'revision_note' => null,
                    'rejection_reason' => null,
                    'rejected_at' => null,
                ])->save();
                $this->recordStatus(
                    $locked,
                    $previousStatus,
                    InventoryRequestStatus::Submitted,
                    'Permintaan diajukan oleh pegawai.',
                    $actor,
                );
                $this->auditTransition(
                    $locked,
                    $previousStatus,
                    InventoryRequestStatus::Submitted,
                    'inventory_request_submitted',
                    $actor,
                    $httpRequest,
                );
                $this->appendSignature(
                    $locked,
                    $actor,
                    DigitalSignaturePurpose::InventoryRequestSubmission,
                    $signatureFile,
                    $httpRequest,
                );

                return $this->loadRequest($locked);
            }, 3);
        } catch (Throwable $exception) {
            Storage::disk($this->signatureDisk())->delete(
                $signatureFile['path'],
            );

            throw $exception;
        }
    }

    public function startReview(
        InventoryRequest $inventoryRequest,
        User $actor,
        ?Request $httpRequest = null,
    ): InventoryRequest {
        return DB::transaction(function () use (
            $inventoryRequest,
            $actor,
            $httpRequest,
        ): InventoryRequest {
            $locked = $this->lockRequest($inventoryRequest);
            $this->requireStatus(
                $locked,
                [
                    InventoryRequestStatus::Submitted,
                    InventoryRequestStatus::WaitingStock,
                ],
                'Permintaan ini tidak dapat mulai diperiksa.',
            );
            $previousStatus = $locked->status;

            $locked->forceFill([
                'status' => InventoryRequestStatus::UnderReview,
                'reviewed_by' => $actor->getKey(),
                'reviewed_at' => now(),
            ])->save();
            $this->recordStatus(
                $locked,
                $previousStatus,
                InventoryRequestStatus::UnderReview,
                'Pemeriksaan dimulai oleh Administrator.',
                $actor,
            );
            $this->auditTransition(
                $locked,
                $previousStatus,
                InventoryRequestStatus::UnderReview,
                'inventory_request_review_started',
                $actor,
                $httpRequest,
            );

            return $this->loadRequest($locked);
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function approve(
        InventoryRequest $inventoryRequest,
        array $data,
        User $actor,
        ?Request $httpRequest = null,
    ): InventoryRequest {
        $signatureFile = $this->storeSignatureFile(
            $inventoryRequest,
            (string) $data['signature_data'],
        );

        try {
            return DB::transaction(function () use (
                $inventoryRequest,
                $data,
                $actor,
                $httpRequest,
                $signatureFile,
            ): InventoryRequest {
                $locked = $this->lockRequest($inventoryRequest);
                $this->requireStatus(
                    $locked,
                    [InventoryRequestStatus::UnderReview],
                    'Permintaan harus berada pada tahap pemeriksaan sebelum disetujui.',
                );
                $requestItems = $this->lockRequestItems($locked);
                $masterItems = $this->lockMasterItems($requestItems);
                $allApproved = true;

                foreach ($requestItems as $line) {
                    $input = $data['items'][$line->getKey()];
                    $approved = $this->quantity(
                        $input['approved_quantity'],
                    );
                    $requested = $this->quantity(
                        $line->requested_quantity,
                    );
                    $item = $masterItems->get($line->item_id);

                    if (! $item instanceof Item) {
                        throw ValidationException::withMessages([
                            'items' => 'Salah satu barang tidak ditemukan.',
                        ]);
                    }

                    $available = $this->quantity(
                        (float) $item->current_stock
                        - (float) $item->reserved_stock,
                    );

                    if ($approved > $available) {
                        throw ValidationException::withMessages([
                            "items.{$line->getKey()}.approved_quantity" => sprintf(
                                'Stok tersedia hanya %s %s.',
                                $this->formatQuantity($available),
                                $line->unit_snapshot,
                            ),
                        ]);
                    }

                    $item->reserved_stock = $this->quantity(
                        (float) $item->reserved_stock + $approved,
                    );
                    $item->save();

                    $line->forceFill([
                        'approved_quantity' => $approved,
                        'reserved_quantity' => $approved,
                        'delivered_quantity' => null,
                        'admin_notes' => $input['admin_notes'] ?? null,
                    ])->save();
                    $allApproved = $allApproved && $approved === $requested;
                }

                $newStatus = $allApproved
                    ? InventoryRequestStatus::Approved
                    : InventoryRequestStatus::PartiallyApproved;
                $previousStatus = $locked->status;

                $locked->forceFill([
                    'status' => $newStatus,
                    'approved_by' => $actor->getKey(),
                    'approved_at' => now(),
                    'admin_notes' => $data['admin_notes'] ?? null,
                ])->save();
                $this->recordStatus(
                    $locked,
                    $previousStatus,
                    $newStatus,
                    $data['admin_notes'] ?? 'Permintaan disetujui.',
                    $actor,
                );
                $this->auditTransition(
                    $locked,
                    $previousStatus,
                    $newStatus,
                    'inventory_request_approved',
                    $actor,
                    $httpRequest,
                );
                $this->appendSignature(
                    $locked,
                    $actor,
                    DigitalSignaturePurpose::InventoryRequestApproval,
                    $signatureFile,
                    $httpRequest,
                );

                return $this->loadRequest($locked);
            }, 3);
        } catch (Throwable $exception) {
            Storage::disk($this->signatureDisk())->delete(
                $signatureFile['path'],
            );

            throw $exception;
        }
    }

    public function requestRevision(
        InventoryRequest $inventoryRequest,
        string $note,
        User $actor,
        ?Request $httpRequest = null,
    ): InventoryRequest {
        return DB::transaction(function () use (
            $inventoryRequest,
            $note,
            $actor,
            $httpRequest,
        ): InventoryRequest {
            $locked = $this->lockRequest($inventoryRequest);
            $this->requireStatus(
                $locked,
                [InventoryRequestStatus::UnderReview],
                'Permintaan ini tidak dapat dikembalikan untuk perbaikan.',
            );
            $previousStatus = $locked->status;

            $locked->forceFill([
                'status' => InventoryRequestStatus::RevisionRequired,
                'revision_note' => $note,
                'submitted_at' => null,
            ])->save();
            $this->recordStatus(
                $locked,
                $previousStatus,
                InventoryRequestStatus::RevisionRequired,
                $note,
                $actor,
            );
            $this->auditTransition(
                $locked,
                $previousStatus,
                InventoryRequestStatus::RevisionRequired,
                'inventory_request_revision_requested',
                $actor,
                $httpRequest,
            );

            return $this->loadRequest($locked);
        }, 3);
    }

    public function reject(
        InventoryRequest $inventoryRequest,
        string $reason,
        User $actor,
        ?Request $httpRequest = null,
    ): InventoryRequest {
        return DB::transaction(function () use (
            $inventoryRequest,
            $reason,
            $actor,
            $httpRequest,
        ): InventoryRequest {
            $locked = $this->lockRequest($inventoryRequest);
            $this->requireStatus(
                $locked,
                [InventoryRequestStatus::UnderReview],
                'Permintaan ini tidak dapat ditolak.',
            );
            $previousStatus = $locked->status;

            $locked->forceFill([
                'status' => InventoryRequestStatus::Rejected,
                'rejection_reason' => $reason,
                'rejected_at' => now(),
            ])->save();
            $this->recordStatus(
                $locked,
                $previousStatus,
                InventoryRequestStatus::Rejected,
                $reason,
                $actor,
            );
            $this->auditTransition(
                $locked,
                $previousStatus,
                InventoryRequestStatus::Rejected,
                'inventory_request_rejected',
                $actor,
                $httpRequest,
            );

            return $this->loadRequest($locked);
        }, 3);
    }

    public function awaitStock(
        InventoryRequest $inventoryRequest,
        string $note,
        User $actor,
        ?Request $httpRequest = null,
    ): InventoryRequest {
        return DB::transaction(function () use (
            $inventoryRequest,
            $note,
            $actor,
            $httpRequest,
        ): InventoryRequest {
            $locked = $this->lockRequest($inventoryRequest);
            $this->requireStatus(
                $locked,
                [InventoryRequestStatus::UnderReview],
                'Permintaan ini tidak dapat dipindahkan ke status menunggu stok.',
            );
            $previousStatus = $locked->status;

            $locked->forceFill([
                'status' => InventoryRequestStatus::WaitingStock,
                'admin_notes' => $note,
            ])->save();
            $this->recordStatus(
                $locked,
                $previousStatus,
                InventoryRequestStatus::WaitingStock,
                $note,
                $actor,
            );
            $this->auditTransition(
                $locked,
                $previousStatus,
                InventoryRequestStatus::WaitingStock,
                'inventory_request_waiting_stock',
                $actor,
                $httpRequest,
            );

            return $this->loadRequest($locked);
        }, 3);
    }

    public function markReady(
        InventoryRequest $inventoryRequest,
        User $actor,
        ?Request $httpRequest = null,
    ): InventoryRequest {
        return DB::transaction(function () use (
            $inventoryRequest,
            $actor,
            $httpRequest,
        ): InventoryRequest {
            $locked = $this->lockRequest($inventoryRequest);
            $this->requireStatus(
                $locked,
                [
                    InventoryRequestStatus::Approved,
                    InventoryRequestStatus::PartiallyApproved,
                ],
                'Hanya permintaan yang telah disetujui yang dapat disiapkan.',
            );
            $previousStatus = $locked->status;

            $locked->forceFill([
                'status' => InventoryRequestStatus::ReadyForDelivery,
            ])->save();
            $this->recordStatus(
                $locked,
                $previousStatus,
                InventoryRequestStatus::ReadyForDelivery,
                'Barang telah disiapkan untuk diserahkan.',
                $actor,
            );
            $this->auditTransition(
                $locked,
                $previousStatus,
                InventoryRequestStatus::ReadyForDelivery,
                'inventory_request_ready_for_delivery',
                $actor,
                $httpRequest,
            );

            return $this->loadRequest($locked);
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function deliver(
        InventoryRequest $inventoryRequest,
        array $data,
        User $actor,
        ?Request $httpRequest = null,
    ): InventoryRequest {
        return DB::transaction(function () use (
            $inventoryRequest,
            $data,
            $actor,
            $httpRequest,
        ): InventoryRequest {
            $locked = $this->lockRequest($inventoryRequest);
            $this->requireStatus(
                $locked,
                [InventoryRequestStatus::ReadyForDelivery],
                'Barang harus berstatus siap diserahkan.',
            );
            $requestItems = $this->lockRequestItems($locked);
            $masterItems = $this->lockMasterItems($requestItems);
            $transactionDate = now();

            foreach ($requestItems->values() as $index => $line) {
                $input = $data['items'][$line->getKey()];
                $delivered = $this->quantity(
                    $input['delivered_quantity'],
                );
                $reserved = $this->quantity(
                    $line->reserved_quantity,
                );
                $approved = $this->quantity(
                    $line->approved_quantity,
                );
                $item = $masterItems->get($line->item_id);

                if (! $item instanceof Item) {
                    throw ValidationException::withMessages([
                        'items' => 'Salah satu barang tidak ditemukan.',
                    ]);
                }

                if ($delivered > $approved || $delivered > $reserved) {
                    throw ValidationException::withMessages([
                        "items.{$line->getKey()}.delivered_quantity" => 'Jumlah penyerahan melebihi jumlah yang direservasi.',
                    ]);
                }

                $stockBefore = $this->quantity($item->current_stock);
                $itemReserved = $this->quantity(
                    $item->reserved_stock,
                );

                if ($delivered > $stockBefore || $reserved > $itemReserved) {
                    throw ValidationException::withMessages([
                        "items.{$line->getKey()}.delivered_quantity" => 'Stok berubah dan tidak lagi mencukupi. Periksa kartu stok sebelum menyerahkan barang.',
                    ]);
                }

                $stockAfter = $this->quantity(
                    $stockBefore - $delivered,
                );
                $item->forceFill([
                    'current_stock' => $stockAfter,
                    'reserved_stock' => $this->quantity(
                        $itemReserved - $reserved,
                    ),
                ])->save();
                $line->forceFill([
                    'delivered_quantity' => $delivered,
                    'reserved_quantity' => 0,
                ])->save();

                if ($delivered <= 0) {
                    continue;
                }

                $movementNumber = sprintf(
                    '%s-%03d',
                    $locked->request_number,
                    $index + 1,
                );

                StockMovement::query()->create([
                    'transaction_number' => $movementNumber,
                    'movement_number' => $movementNumber,
                    'reference_number' => $locked->request_number,
                    'item_id' => $item->getKey(),
                    'movement_type' => StockMovementType::RequestOut,
                    'reference_type' => $locked->getMorphClass(),
                    'reference_id' => $locked->getKey(),
                    'quantity_in' => 0,
                    'quantity_out' => $delivered,
                    'stock_before' => $stockBefore,
                    'stock_after' => $stockAfter,
                    'transaction_date' => $transactionDate,
                    'description' => sprintf(
                        'Penyerahan %s kepada %s.',
                        $locked->request_number,
                        $locked->requester_name_snapshot,
                    ),
                    'created_by' => $actor->getKey(),
                ]);
            }

            $previousStatus = $locked->status;
            $deliveryNotes = filled($data['delivery_notes'] ?? null)
                ? trim((string) $data['delivery_notes'])
                : null;

            $locked->forceFill([
                'status' => InventoryRequestStatus::Delivered,
                'delivered_by' => $actor->getKey(),
                'delivered_at' => $transactionDate,
                'admin_notes' => $this->mergeNotes(
                    $locked->admin_notes,
                    $deliveryNotes,
                ),
            ])->save();
            $this->recordStatus(
                $locked,
                $previousStatus,
                InventoryRequestStatus::Delivered,
                $deliveryNotes ?? 'Barang telah diserahkan.',
                $actor,
            );
            $this->auditTransition(
                $locked,
                $previousStatus,
                InventoryRequestStatus::Delivered,
                'inventory_request_delivered',
                $actor,
                $httpRequest,
            );

            return $this->loadRequest($locked);
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function confirmReceipt(
        InventoryRequest $inventoryRequest,
        array $data,
        User $actor,
        ?Request $httpRequest = null,
    ): InventoryRequest {
        $signatureFile = $this->storeSignatureFile(
            $inventoryRequest,
            (string) $data['signature_data'],
        );

        try {
            return DB::transaction(function () use (
                $inventoryRequest,
                $actor,
                $httpRequest,
                $signatureFile,
            ): InventoryRequest {
                $locked = $this->lockRequest($inventoryRequest);
                $this->requireStatus(
                    $locked,
                    [InventoryRequestStatus::Delivered],
                    'Penerimaan hanya dapat dikonfirmasi setelah barang diserahkan.',
                );

                if ($locked->requested_by !== $actor->getKey()) {
                    abort(403);
                }

                $previousStatus = $locked->status;
                $locked->forceFill([
                    'status' => InventoryRequestStatus::Completed,
                    'received_at' => now(),
                    'completed_at' => now(),
                ])->save();
                $this->recordStatus(
                    $locked,
                    $previousStatus,
                    InventoryRequestStatus::Completed,
                    'Penerimaan barang dikonfirmasi oleh pegawai.',
                    $actor,
                );
                $this->auditTransition(
                    $locked,
                    $previousStatus,
                    InventoryRequestStatus::Completed,
                    'inventory_request_completed',
                    $actor,
                    $httpRequest,
                );
                $this->appendSignature(
                    $locked,
                    $actor,
                    DigitalSignaturePurpose::InventoryReceiptConfirmation,
                    $signatureFile,
                    $httpRequest,
                );

                return $this->loadRequest($locked);
            }, 3);
        } catch (Throwable $exception) {
            Storage::disk($this->signatureDisk())->delete(
                $signatureFile['path'],
            );

            throw $exception;
        }
    }

    public function cancel(
        InventoryRequest $inventoryRequest,
        string $reason,
        User $actor,
        ?Request $httpRequest = null,
    ): InventoryRequest {
        return DB::transaction(function () use (
            $inventoryRequest,
            $reason,
            $actor,
            $httpRequest,
        ): InventoryRequest {
            $locked = $this->lockRequest($inventoryRequest);

            if (
                $locked->status->isFinal()
                || $locked->status === InventoryRequestStatus::Delivered
            ) {
                throw ValidationException::withMessages([
                    'request' => 'Permintaan pada status ini tidak dapat dibatalkan.',
                ]);
            }

            $this->releaseReservations($locked);
            $previousStatus = $locked->status;
            $locked->forceFill([
                'status' => InventoryRequestStatus::Cancelled,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ])->save();
            $this->recordStatus(
                $locked,
                $previousStatus,
                InventoryRequestStatus::Cancelled,
                $reason,
                $actor,
            );
            $this->auditTransition(
                $locked,
                $previousStatus,
                InventoryRequestStatus::Cancelled,
                'inventory_request_cancelled',
                $actor,
                $httpRequest,
            );

            return $this->loadRequest($locked);
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

        $binary = $disk->get($signature->image_path);

        if (! SignaturePayload::checksumMatches(
            $binary,
            (string) $signature->image_checksum,
        )) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode($binary);
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function replaceRequestItems(
        InventoryRequest $inventoryRequest,
        array $lines,
    ): void {
        $itemIds = collect($lines)
            ->pluck('item_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->values();
        $masterItems = Item::query()
            ->with('unit')
            ->whereIn('id', $itemIds)
            ->where('is_active', true)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        if ($masterItems->count() !== $itemIds->unique()->count()) {
            throw ValidationException::withMessages([
                'items' => 'Salah satu barang tidak tersedia atau sudah dinonaktifkan.',
            ]);
        }

        $inventoryRequest->items()->delete();

        foreach ($lines as $line) {
            $item = $masterItems->get((int) $line['item_id']);

            if (! $item instanceof Item) {
                continue;
            }

            $inventoryRequest->items()->create([
                'item_id' => $item->getKey(),
                'item_code_snapshot' => $item->item_code,
                'item_name_snapshot' => $item->name,
                'unit_snapshot' => $item->unit->symbol,
                'requested_quantity' => $this->quantity(
                    $line['requested_quantity'],
                ),
                'approved_quantity' => null,
                'reserved_quantity' => 0,
                'delivered_quantity' => null,
                'notes' => $line['notes'] ?? null,
                'admin_notes' => null,
            ]);
        }
    }

    private function releaseReservations(
        InventoryRequest $inventoryRequest,
    ): void {
        $requestItems = $this->lockRequestItems($inventoryRequest);
        $masterItems = $this->lockMasterItems($requestItems);

        foreach ($requestItems as $line) {
            $reserved = $this->quantity($line->reserved_quantity);

            if ($reserved <= 0) {
                continue;
            }

            $item = $masterItems->get($line->item_id);

            if (! $item instanceof Item) {
                continue;
            }

            $item->reserved_stock = $this->quantity(
                max(0, (float) $item->reserved_stock - $reserved),
            );
            $item->save();
            $line->reserved_quantity = 0;
            $line->save();
        }
    }

    /**
     * @return array{path: string, checksum: string}
     */
    private function storeSignatureFile(
        InventoryRequest $inventoryRequest,
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
            'signatures/inventory-requests/%d/%s.png',
            $inventoryRequest->getKey(),
            Str::uuid(),
        );

        $stored = Storage::disk($this->signatureDisk())
            ->put($path, $binary);

        if (! $stored) {
            throw new RuntimeException(
                'Tanda tangan digital tidak dapat disimpan.',
            );
        }

        return [
            'path' => $path,
            'checksum' => $checksum,
        ];
    }

    /**
     * @param  array{path: string, checksum: string}  $signatureFile
     */
    private function appendSignature(
        InventoryRequest $inventoryRequest,
        User $actor,
        DigitalSignaturePurpose $purpose,
        array $signatureFile,
        ?Request $httpRequest,
    ): DigitalSignature {
        $version = 1 + (int) $inventoryRequest->signatures()
            ->where('purpose', $purpose->value)
            ->max('version');
        $signedAt = now();

        return $inventoryRequest->signatures()->create([
            'signer_id' => $actor->getKey(),
            'signer_name_snapshot' => $actor->name,
            'employee_number_snapshot' => $actor->employee_number,
            'purpose' => $purpose,
            'version' => $version,
            'image_path' => $signatureFile['path'],
            'transaction_hash' => hash(
                'sha256',
                implode('|', [
                    $inventoryRequest->request_number,
                    $purpose->value,
                    $version,
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
    }

    private function signatureBinary(string $dataUrl): string
    {
        return SignaturePayload::decode($dataUrl);
    }

    private function signatureDisk(): string
    {
        return (string) config(
            'simantap.uploads.disk',
            'local',
        );
    }

    private function recordStatus(
        InventoryRequest $inventoryRequest,
        ?InventoryRequestStatus $previous,
        InventoryRequestStatus $new,
        ?string $notes,
        User $actor,
    ): InventoryRequestStatusHistory {
        return $inventoryRequest->statusHistories()->create([
            'previous_status' => $previous,
            'new_status' => $new,
            'notes' => $notes,
            'changed_by' => $actor->getKey(),
            'changed_at' => now(),
        ]);
    }

    private function auditTransition(
        InventoryRequest $inventoryRequest,
        InventoryRequestStatus $previous,
        InventoryRequestStatus $new,
        string $event,
        User $actor,
        ?Request $httpRequest,
    ): void {
        $this->auditLogger->log(
            event: $event,
            module: 'inventory_request',
            auditable: $inventoryRequest,
            oldValues: ['status' => $previous->value],
            newValues: ['status' => $new->value],
            request: $httpRequest,
            actorId: $actor->getKey(),
        );
    }

    private function lockRequest(
        InventoryRequest $inventoryRequest,
    ): InventoryRequest {
        return InventoryRequest::query()
            ->whereKey($inventoryRequest->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * @return Collection<int, InventoryRequestItem>
     */
    private function lockRequestItems(
        InventoryRequest $inventoryRequest,
    ): Collection {
        return InventoryRequestItem::query()
            ->where('inventory_request_id', $inventoryRequest->getKey())
            ->orderBy('item_id')
            ->lockForUpdate()
            ->get();
    }

    /**
     * @param  Collection<int, InventoryRequestItem>  $requestItems
     * @return Collection<int, Item>
     */
    private function lockMasterItems(
        Collection $requestItems,
    ): Collection {
        return Item::query()
            ->whereIn('id', $requestItems->pluck('item_id'))
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
    }

    /**
     * @param  list<InventoryRequestStatus>  $allowed
     */
    private function requireStatus(
        InventoryRequest $inventoryRequest,
        array $allowed,
        string $message,
    ): void {
        if (! in_array($inventoryRequest->status, $allowed, true)) {
            throw ValidationException::withMessages([
                'request' => $message,
            ]);
        }
    }

    private function requestDate(mixed $value): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat(
            '!Y-m-d',
            (string) $value,
            (string) config(
                'simantap.display_timezone',
                'Asia/Jakarta',
            ),
        )->startOfDay();
    }

    private function loadRequest(
        InventoryRequest $inventoryRequest,
    ): InventoryRequest {
        return $inventoryRequest->load([
            'requester:id,employee_number,name,work_unit,position',
            'reviewer:id,name',
            'approver:id,name,position',
            'deliverer:id,name',
            'items.item.unit',
            'statusHistories.changer:id,name',
            'signatures.signer:id,name',
        ]);
    }

    private function quantity(mixed $value): float
    {
        return round((float) $value, 2);
    }

    private function formatQuantity(float $value): string
    {
        return number_format($value, 2, ',', '.');
    }

    private function mergeNotes(
        ?string $current,
        ?string $addition,
    ): ?string {
        if (blank($addition)) {
            return $current;
        }

        if (blank($current)) {
            return $addition;
        }

        return $current."\n\nCatatan penyerahan: ".$addition;
    }
}
