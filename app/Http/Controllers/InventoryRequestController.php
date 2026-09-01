<?php

namespace App\Http\Controllers;

use App\Enums\InventoryRequestStatus;
use App\Enums\PermissionName;
use App\Http\Requests\ApproveInventoryRequestRequest;
use App\Http\Requests\AwaitInventoryRequestStockRequest;
use App\Http\Requests\CancelInventoryRequestRequest;
use App\Http\Requests\ConfirmInventoryRequestReceiptRequest;
use App\Http\Requests\DeliverInventoryRequestRequest;
use App\Http\Requests\RejectInventoryRequestRequest;
use App\Http\Requests\ReviseInventoryRequestRequest;
use App\Http\Requests\StoreInventoryRequestRequest;
use App\Http\Requests\SubmitInventoryRequestRequest;
use App\Models\InventoryRequest;
use App\Models\Item;
use App\Services\DocumentSignatoryService;
use App\Services\DocumentVerificationService;
use App\Services\InventoryRequestService;
use App\Services\QrCodeService;
use App\Support\DisplayDateRange;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class InventoryRequestController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', InventoryRequest::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => [
                'nullable',
                Rule::in(InventoryRequestStatus::values()),
            ],
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
        $actor = $request->user();

        abort_if($actor === null, 401);

        $canViewAll = $actor->can(
            PermissionName::InventoryRequestViewAll->value,
        );
        $search = trim((string) ($filters['q'] ?? ''));
        $status = (string) ($filters['status'] ?? '');
        $from = (string) ($filters['from'] ?? '');
        $until = (string) ($filters['until'] ?? '');
        $bounds = DisplayDateRange::utcBounds($from, $until);
        $baseQuery = InventoryRequest::query()
            ->when(
                ! $canViewAll,
                static fn (Builder $query): Builder => $query->where(
                    'requested_by',
                    $actor->getKey(),
                ),
            );
        $query = (clone $baseQuery)
            ->with([
                'requester:id,name,employee_number,work_unit',
                'items:id,inventory_request_id',
            ])
            ->withCount('items')
            ->when(
                $search !== '',
                static function (Builder $query) use ($search): void {
                    $query->where(function (
                        Builder $nested,
                    ) use ($search): void {
                        $nested
                            ->where(
                                'request_number',
                                'like',
                                "%{$search}%",
                            )
                            ->orWhere(
                                'requester_name_snapshot',
                                'like',
                                "%{$search}%",
                            )
                            ->orWhere(
                                'employee_number_snapshot',
                                'like',
                                "%{$search}%",
                            );
                    });
                },
            )
            ->when(
                $status !== '',
                static fn (Builder $query): Builder => $query->where(
                    'status',
                    $status,
                ),
            )
            ->when(
                $bounds['from'] !== null,
                static fn (Builder $query): Builder => $query->where(
                    'request_date',
                    '>=',
                    $bounds['from'],
                ),
            )
            ->when(
                $bounds['until'] !== null,
                static fn (Builder $query): Builder => $query->where(
                    'request_date',
                    '<=',
                    $bounds['until'],
                ),
            );

        return view('inventory-requests.index', [
            'inventoryRequests' => $query
                ->latest('request_date')
                ->latest('id')
                ->paginate(12)
                ->withQueryString(),
            'statuses' => InventoryRequestStatus::cases(),
            'filters' => compact(
                'search',
                'status',
                'from',
                'until',
            ),
            'summary' => [
                'total' => (clone $baseQuery)->count(),
                'waiting' => (clone $baseQuery)
                    ->whereIn('status', [
                        InventoryRequestStatus::Submitted->value,
                        InventoryRequestStatus::UnderReview->value,
                        InventoryRequestStatus::WaitingStock->value,
                    ])
                    ->count(),
                'approved' => (clone $baseQuery)
                    ->whereIn('status', [
                        InventoryRequestStatus::Approved->value,
                        InventoryRequestStatus::PartiallyApproved->value,
                        InventoryRequestStatus::ReadyForDelivery->value,
                    ])
                    ->count(),
                'completed' => (clone $baseQuery)
                    ->where(
                        'status',
                        InventoryRequestStatus::Completed->value,
                    )
                    ->count(),
            ],
            'canViewAll' => $canViewAll,
            'canApprove' => $actor->can(
                PermissionName::InventoryRequestApprove->value,
            ),
            'routePrefix' => $this->routePrefix($request),
            'displayTimezone' => (string) config(
                'simantap.display_timezone',
                'Asia/Jakarta',
            ),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', InventoryRequest::class);

        return view('inventory-requests.create', [
            'inventoryRequest' => null,
            'items' => $this->availableItems(),
            'displayTimezone' => (string) config(
                'simantap.display_timezone',
                'Asia/Jakarta',
            ),
        ]);
    }

    public function store(
        StoreInventoryRequestRequest $request,
        InventoryRequestService $service,
    ): RedirectResponse {
        Gate::authorize('create', InventoryRequest::class);
        $actor = $request->user();

        abort_if($actor === null, 401);

        $inventoryRequest = $service->createDraft(
            $request->validated(),
            $actor,
            $request,
        );

        return redirect()
            ->route('my.inventory-requests.show', $inventoryRequest)
            ->with(
                'status',
                'Draft permintaan berhasil dibuat. Periksa kembali lalu bubuhkan tanda tangan untuk mengajukan.',
            );
    }

    public function show(
        Request $request,
        InventoryRequest $inventoryRequest,
        InventoryRequestService $service,
    ): View {
        Gate::authorize('view', $inventoryRequest);
        $this->loadRequest($inventoryRequest);

        return view('inventory-requests.show', [
            'inventoryRequest' => $inventoryRequest,
            'routePrefix' => $this->routePrefix($request),
            'canManage' => $request->user()?->can(
                PermissionName::InventoryRequestApprove->value,
            ) === true,
            'approvalSignature' => $service->signatureDataUri(
                $inventoryRequest->approvalSignature(),
            ),
            'receiptSignature' => $service->signatureDataUri(
                $inventoryRequest->receiptSignature(),
            ),
            'displayTimezone' => (string) config(
                'simantap.display_timezone',
                'Asia/Jakarta',
            ),
        ]);
    }

    public function edit(
        InventoryRequest $inventoryRequest,
    ): View {
        Gate::authorize('update', $inventoryRequest);
        $inventoryRequest->load('items');

        return view('inventory-requests.edit', [
            'inventoryRequest' => $inventoryRequest,
            'items' => $this->availableItems(
                $inventoryRequest->items->pluck('item_id')->all(),
            ),
            'displayTimezone' => (string) config(
                'simantap.display_timezone',
                'Asia/Jakarta',
            ),
        ]);
    }

    public function update(
        StoreInventoryRequestRequest $request,
        InventoryRequest $inventoryRequest,
        InventoryRequestService $service,
    ): RedirectResponse {
        Gate::authorize('update', $inventoryRequest);
        $actor = $request->user();

        abort_if($actor === null, 401);

        $service->updateDraft(
            $inventoryRequest,
            $request->validated(),
            $actor,
            $request,
        );

        return redirect()
            ->route('my.inventory-requests.show', $inventoryRequest)
            ->with(
                'status',
                'Draft permintaan berhasil diperbarui.',
            );
    }

    public function submit(
        SubmitInventoryRequestRequest $request,
        InventoryRequest $inventoryRequest,
        InventoryRequestService $service,
    ): RedirectResponse {
        Gate::authorize('submit', $inventoryRequest);
        $actor = $request->user();

        abort_if($actor === null, 401);

        $service->submit(
            $inventoryRequest,
            $actor,
            $request,
        );

        return redirect()
            ->route('my.inventory-requests.show', $inventoryRequest)
            ->with(
                'status',
                'Permintaan berhasil diajukan dan menunggu pemeriksaan Administrator.',
            );
    }

    public function startReview(
        Request $request,
        InventoryRequest $inventoryRequest,
        InventoryRequestService $service,
    ): RedirectResponse {
        Gate::authorize('approve', $inventoryRequest);
        $actor = $request->user();

        abort_if($actor === null, 401);

        $service->startReview(
            $inventoryRequest,
            $actor,
            $request,
        );

        return redirect()
            ->route('inventory-requests.show', $inventoryRequest)
            ->with(
                'status',
                'Pemeriksaan permintaan telah dimulai.',
            );
    }

    public function approve(
        ApproveInventoryRequestRequest $request,
        InventoryRequest $inventoryRequest,
        InventoryRequestService $service,
    ): RedirectResponse {
        Gate::authorize('approve', $inventoryRequest);
        $actor = $request->user();

        abort_if($actor === null, 401);

        $service->approve(
            $inventoryRequest,
            $request->validated(),
            $actor,
            $request,
        );

        return redirect()
            ->route('inventory-requests.show', $inventoryRequest)
            ->with(
                'status',
                'Persetujuan tersimpan. Stok telah direservasi tanpa mengurangi stok fisik.',
            );
    }

    public function requestRevision(
        ReviseInventoryRequestRequest $request,
        InventoryRequest $inventoryRequest,
        InventoryRequestService $service,
    ): RedirectResponse {
        Gate::authorize('approve', $inventoryRequest);
        $actor = $request->user();

        abort_if($actor === null, 401);

        $service->requestRevision(
            $inventoryRequest,
            $request->validated('revision_note'),
            $actor,
            $request,
        );

        return back()->with(
            'status',
            'Permintaan dikembalikan kepada pegawai untuk diperbaiki.',
        );
    }

    public function reject(
        RejectInventoryRequestRequest $request,
        InventoryRequest $inventoryRequest,
        InventoryRequestService $service,
    ): RedirectResponse {
        Gate::authorize('approve', $inventoryRequest);
        $actor = $request->user();

        abort_if($actor === null, 401);

        $service->reject(
            $inventoryRequest,
            $request->validated('rejection_reason'),
            $actor,
            $request,
        );

        return back()->with(
            'status',
            'Permintaan telah ditolak.',
        );
    }

    public function awaitStock(
        AwaitInventoryRequestStockRequest $request,
        InventoryRequest $inventoryRequest,
        InventoryRequestService $service,
    ): RedirectResponse {
        Gate::authorize('approve', $inventoryRequest);
        $actor = $request->user();

        abort_if($actor === null, 401);

        $service->awaitStock(
            $inventoryRequest,
            $request->validated('admin_notes'),
            $actor,
            $request,
        );

        return back()->with(
            'status',
            'Permintaan dipindahkan ke status Menunggu Stok.',
        );
    }

    public function markReady(
        Request $request,
        InventoryRequest $inventoryRequest,
        InventoryRequestService $service,
    ): RedirectResponse {
        Gate::authorize('deliver', $inventoryRequest);
        $actor = $request->user();

        abort_if($actor === null, 401);

        $service->markReady(
            $inventoryRequest,
            $actor,
            $request,
        );

        return back()->with(
            'status',
            'Barang ditandai siap untuk diserahkan.',
        );
    }

    public function deliver(
        DeliverInventoryRequestRequest $request,
        InventoryRequest $inventoryRequest,
        InventoryRequestService $service,
    ): RedirectResponse {
        Gate::authorize('deliver', $inventoryRequest);
        $actor = $request->user();

        abort_if($actor === null, 401);

        $service->deliver(
            $inventoryRequest,
            $request->validated(),
            $actor,
            $request,
        );

        return redirect()
            ->route('inventory-requests.show', $inventoryRequest)
            ->with(
                'status',
                'Penyerahan berhasil dicatat. Stok fisik dan kartu stok telah diperbarui.',
            );
    }

    public function confirmReceipt(
        ConfirmInventoryRequestReceiptRequest $request,
        InventoryRequest $inventoryRequest,
        InventoryRequestService $service,
    ): RedirectResponse {
        Gate::authorize('receive', $inventoryRequest);
        $actor = $request->user();

        abort_if($actor === null, 401);

        $service->confirmReceipt(
            $inventoryRequest,
            $request->validated(),
            $actor,
            $request,
        );

        return redirect()
            ->route('my.inventory-requests.show', $inventoryRequest)
            ->with(
                'status',
                'Penerimaan barang berhasil dikonfirmasi. Permintaan selesai.',
            );
    }

    public function cancel(
        CancelInventoryRequestRequest $request,
        InventoryRequest $inventoryRequest,
        InventoryRequestService $service,
    ): RedirectResponse {
        Gate::authorize('cancel', $inventoryRequest);
        $actor = $request->user();

        abort_if($actor === null, 401);

        $service->cancel(
            $inventoryRequest,
            $request->validated('cancellation_reason'),
            $actor,
            $request,
        );

        return redirect()
            ->route(
                $this->routePrefix($request).'.show',
                $inventoryRequest,
            )
            ->with(
                'status',
                'Permintaan berhasil dibatalkan dan reservasi stok telah dilepas.',
            );
    }

    public function downloadPdf(
        Request $request,
        InventoryRequest $inventoryRequest,
        InventoryRequestService $service,
        DocumentVerificationService $verificationService,
        QrCodeService $qrCodes,
        DocumentSignatoryService $signatories,
    ): Response {
        Gate::authorize('view', $inventoryRequest);
        $this->loadRequest($inventoryRequest);

        $actor = $request->user();
        abort_if($actor === null, 401);

        $approvalSignatureRecord =
            $inventoryRequest->approvalSignature();

        $receiptSignatureRecord =
            $inventoryRequest->receiptSignature();

        $approvalSignature = $service->signatureDataUri(
            $approvalSignatureRecord,
        );

        $receiptSignature = $service->signatureDataUri(
            $receiptSignatureRecord,
        );

        abort_if(
            $approvalSignatureRecord !== null
                && $approvalSignature === null,
            409,
            'Integritas tanda tangan persetujuan gagal diverifikasi.',
        );

        abort_if(
            $receiptSignatureRecord !== null
                && $receiptSignature === null,
            409,
            'Integritas tanda tangan penerimaan gagal diverifikasi.',
        );

        $documentSignatories = $signatories->for(
            'inventory_request',
        );

        $documentVerification = $verificationService->issue(
            verifiable: $inventoryRequest,
            documentType: 'inventory_request',
            documentLabel: 'Form Permintaan Persediaan',
            documentReference: $inventoryRequest->request_number,
            payload: [
                ...$verificationService
                    ->inventoryRequestPayload($inventoryRequest),
                'official_signatories' => $documentSignatories,
            ],
            actor: $actor,
            httpRequest: $request,
        );

        $verificationQrDataUri = $verificationService->qrDataUri(
            $documentVerification,
            $qrCodes,
        );

        $pdf = Pdf::loadView('inventory-requests.pdf', [
            'inventoryRequest' => $inventoryRequest,
            'documentVerification' => $documentVerification,
            'verificationQrDataUri' => $verificationQrDataUri,
            'approvalSignature' => $approvalSignature,
            'receiptSignature' => $receiptSignature,
            'documentSignatories' => $documentSignatories,
            'institutionName' => (string) config(
                'simantap.institution.name',
                'Badan Pusat Statistik',
            ),
            'institutionShortName' => (string) config(
                'simantap.institution.short_name',
                'BPS',
            ),
            'displayTimezone' => (string) config(
                'simantap.display_timezone',
                'Asia/Jakarta',
            ),
        ])->setPaper('a4', 'portrait');

        return $pdf->download(
            str_replace(
                '/',
                '-',
                $inventoryRequest->request_number,
            ).'.pdf',
        );
    }

    /**
     * @param  list<int>  $includeIds
     * @return Collection<int, Item>
     */
    private function availableItems(array $includeIds = [])
    {
        return Item::query()
            ->with(['category', 'unit'])
            ->where(function (Builder $query) use ($includeIds): void {
                $query->where('is_active', true);

                if ($includeIds !== []) {
                    $query->orWhereIn('id', $includeIds);
                }
            })
            ->orderBy('name')
            ->get();
    }

    private function loadRequest(
        InventoryRequest $inventoryRequest,
    ): void {
        $inventoryRequest->load([
            'requester:id,employee_number,name,work_unit,position',
            'reviewer:id,name',
            'approver:id,name,position',
            'deliverer:id,name',
            'items.item.unit',
            'statusHistories.changer:id,name',
            'signatures.signer:id,name',
        ]);
    }

    private function routePrefix(Request $request): string
    {
        return $request->routeIs('my.inventory-requests.*')
            ? 'my.inventory-requests'
            : 'inventory-requests';
    }
}
