<?php

namespace App\Http\Controllers;

use App\Enums\PermissionName;
use App\Enums\VehicleLoanStatus;
use App\Http\Requests\ConfirmVehiclePickupRequest;
use App\Http\Requests\RequestVehicleReturnRequest;
use App\Http\Requests\StoreVehicleConditionCheckRequest;
use App\Models\Attachment;
use App\Models\VehicleLoan;
use App\Services\DocumentSignatoryService;
use App\Services\DocumentVerificationService;
use App\Services\QrCodeService;
use App\Services\VehicleLoanLifecycleService;
use App\Support\AttachmentIntegrity;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VehicleLoanLifecycleController extends Controller
{
    public function adminIndex(Request $request): View
    {
        $actor = $request->user();
        abort_if($actor === null, 401);
        abort_unless(
            $actor->can(PermissionName::VehicleLoanCheck->value),
            403,
        );

        $vehicleLoans = VehicleLoan::query()
            ->whereIn('status', [
                VehicleLoanStatus::Approved->value,
                VehicleLoanStatus::ReadyForPickup->value,
                VehicleLoanStatus::Borrowed->value,
                VehicleLoanStatus::AwaitingReturnInspection->value,
                VehicleLoanStatus::Completed->value,
                VehicleLoanStatus::ReturnIssue->value,
            ])
            ->with($this->lifecycleRelations())
            ->orderByRaw(
                'CASE status
                    WHEN ? THEN 1
                    WHEN ? THEN 2
                    WHEN ? THEN 3
                    WHEN ? THEN 4
                    WHEN ? THEN 5
                    ELSE 6
                END',
                [
                    VehicleLoanStatus::AwaitingReturnInspection->value,
                    VehicleLoanStatus::Approved->value,
                    VehicleLoanStatus::ReadyForPickup->value,
                    VehicleLoanStatus::Borrowed->value,
                    VehicleLoanStatus::ReturnIssue->value,
                ],
            )
            ->latest('planned_start_at')
            ->paginate(10)
            ->withQueryString();

        return view('vehicle-loans.lifecycle.index', [
            'vehicleLoans' => $vehicleLoans,
            'isAdminWorkspace' => true,
            'displayTimezone' => $this->displayTimezone(),
            'summary' => $this->adminSummary(),
        ]);
    }

    public function employeeIndex(Request $request): View
    {
        $actor = $request->user();
        abort_if($actor === null, 401);
        abort_unless(
            $actor->can(PermissionName::VehicleLoanReturn->value),
            403,
        );

        $vehicleLoans = VehicleLoan::query()
            ->where('borrower_id', $actor->getKey())
            ->whereIn('status', [
                VehicleLoanStatus::Approved->value,
                VehicleLoanStatus::ReadyForPickup->value,
                VehicleLoanStatus::Borrowed->value,
                VehicleLoanStatus::AwaitingReturnInspection->value,
                VehicleLoanStatus::Completed->value,
                VehicleLoanStatus::ReturnIssue->value,
            ])
            ->with($this->lifecycleRelations())
            ->latest('planned_start_at')
            ->paginate(10)
            ->withQueryString();

        return view('vehicle-loans.lifecycle.index', [
            'vehicleLoans' => $vehicleLoans,
            'isAdminWorkspace' => false,
            'displayTimezone' => $this->displayTimezone(),
            'summary' => $this->employeeSummary((int) $actor->getKey()),
        ]);
    }

    public function storeCheckout(
        StoreVehicleConditionCheckRequest $request,
        VehicleLoan $vehicleLoan,
        VehicleLoanLifecycleService $service,
    ): RedirectResponse {
        Gate::authorize('recordCheckout', $vehicleLoan);
        $actor = $request->user();
        abort_if($actor === null, 401);

        $service->recordCheckout(
            $vehicleLoan,
            $request->validated(),
            $actor,
            $request,
        );

        return redirect()
            ->route('vehicle-loan-lifecycle.admin.index')
            ->with(
                'status',
                'Pemeriksaan kondisi awal dan tanda tangan petugas tersimpan. Kendaraan sekarang siap dikonfirmasi oleh peminjam.',
            );
    }

    public function confirmPickup(
        ConfirmVehiclePickupRequest $request,
        VehicleLoan $vehicleLoan,
        VehicleLoanLifecycleService $service,
    ): RedirectResponse {
        $actor = $request->user();
        abort_if($actor === null, 401);
        abort_unless($vehicleLoan->isOwnedBy($actor), 403);
        Gate::authorize('confirmPickup', $vehicleLoan);

        $service->confirmPickup(
            $vehicleLoan,
            $request->validated(),
            $actor,
            $request,
        );

        return redirect()
            ->route('vehicle-loan-lifecycle.employee.index')
            ->with(
                'status',
                'Serah terima berhasil dikonfirmasi. Kendaraan tercatat sedang dipinjam.',
            );
    }

    public function requestReturn(
        RequestVehicleReturnRequest $request,
        VehicleLoan $vehicleLoan,
        VehicleLoanLifecycleService $service,
    ): RedirectResponse {
        $actor = $request->user();
        abort_if($actor === null, 401);
        abort_unless($vehicleLoan->isOwnedBy($actor), 403);

        if (
            in_array($vehicleLoan->status, [
                VehicleLoanStatus::AwaitingReturnInspection,
                VehicleLoanStatus::Completed,
                VehicleLoanStatus::ReturnIssue,
            ], true)
            && $vehicleLoan->returnRequestSignature() !== null
        ) {
            return redirect()
                ->route('vehicle-loan-lifecycle.employee.index')
                ->with(
                    'status',
                    'Pengembalian sudah tercatat dan tidak dikirim ulang. Kendaraan menunggu atau telah menyelesaikan pemeriksaan akhir Administrator.',
                );
        }

        Gate::authorize('requestReturn', $vehicleLoan);

        $service->requestReturn(
            $vehicleLoan,
            $request->validated(),
            $actor,
            $request,
        );

        return redirect()
            ->route('vehicle-loan-lifecycle.employee.index')
            ->with(
                'status',
                'Pengembalian dan tanda tangan peminjam tercatat. Kendaraan menunggu pemeriksaan akhir Administrator.',
            );
    }

    public function storeReturn(
        StoreVehicleConditionCheckRequest $request,
        VehicleLoan $vehicleLoan,
        VehicleLoanLifecycleService $service,
    ): RedirectResponse {
        Gate::authorize('inspectReturn', $vehicleLoan);
        $actor = $request->user();
        abort_if($actor === null, 401);

        $result = $service->inspectReturn(
            $vehicleLoan,
            $request->validated(),
            $actor,
            $request,
        );

        $message = $result->status === VehicleLoanStatus::Completed
            ? 'Pemeriksaan pengembalian dan tanda tangan pemeriksa tersimpan. Peminjaman dinyatakan selesai.'
            : 'Pemeriksaan dan tanda tangan pemeriksa tersimpan. Kendaraan masuk status Perlu Pemeriksaan.';

        return redirect()
            ->route('vehicle-loan-lifecycle.admin.index')
            ->with('status', $message);
    }

    public function evidence(
        Request $request,
        VehicleLoan $vehicleLoan,
        Attachment $attachment,
        VehicleLoanLifecycleService $service,
    ): StreamedResponse {
        Gate::authorize('view', $vehicleLoan);

        $belongsToLoan = $vehicleLoan->conditionChecks()
            ->whereHas(
                'attachments',
                static fn (Builder $query): Builder => $query->whereKey(
                    $attachment->getKey(),
                ),
            )
            ->exists();

        abort_unless($belongsToLoan, 404);

        $disk = Storage::disk($attachment->disk);
        abort_unless($disk->exists($attachment->file_path), 404);

        abort_unless(
            AttachmentIntegrity::checksumMatches(
                $attachment,
            ),
            409,
            'Integritas bukti kondisi kendaraan tidak valid.',
        );

        $actor = $request->user();
        abort_if($actor === null, 401);

        $service->auditEvidenceDownload(
            $vehicleLoan,
            $attachment,
            $actor,
            $request,
        );

        return $disk->response(
            $attachment->file_path,
            $attachment->original_name,
            [
                'Content-Type' => $attachment->mime_type,
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ],
        );
    }

    public function downloadPdf(
        Request $request,
        VehicleLoan $vehicleLoan,
        VehicleLoanLifecycleService $service,
        DocumentVerificationService $verificationService,
        QrCodeService $qrCodes,
        DocumentSignatoryService $signatories,
    ): Response {
        Gate::authorize('view', $vehicleLoan);
        $vehicleLoan->load($this->lifecycleRelations());

        $actor = $request->user();
        abort_if($actor === null, 401);

        abort_if(
            $vehicleLoan->checkoutCheck() === null,
            409,
            'Dokumen operasional kendaraan belum tersedia sebelum pemeriksaan kondisi awal.',
        );

        $evidenceData = [];

        foreach ($vehicleLoan->conditionChecks as $conditionCheck) {
            foreach ($conditionCheck->attachments as $attachment) {
                $dataUri = $service->attachmentDataUri(
                    $attachment,
                );

                abort_if(
                    $attachment->isImage()
                        && $dataUri === null,
                    409,
                    'Integritas bukti kondisi kendaraan gagal diverifikasi.',
                );

                $evidenceData[$attachment->getKey()] = $dataUri;
            }
        }

        $signatureDefinitions = [
            'checkoutOfficerSignature' => [
                'record' => $vehicleLoan->checkoutConfirmationSignature(),
                'label' => 'petugas pemeriksaan kondisi awal',
            ],
            'pickupSignature' => [
                'record' => $vehicleLoan->pickupSignature(),
                'label' => 'peminjam saat pengambilan',
            ],
            'returnBorrowerSignature' => [
                'record' => $vehicleLoan->returnRequestSignature(),
                'label' => 'peminjam saat pengembalian',
            ],
            'returnOfficerSignature' => [
                'record' => $vehicleLoan->returnConfirmationSignature(),
                'label' => 'petugas pemeriksaan kondisi akhir',
            ],
        ];
        $signatureData = [];

        foreach ($signatureDefinitions as $key => $definition) {
            $signatureData[$key] = $service->signatureDataUri(
                $definition['record'],
            );

            abort_if(
                $definition['record'] !== null
                    && $signatureData[$key] === null,
                409,
                'Integritas tanda tangan '.$definition['label'].' gagal diverifikasi.',
            );
        }

        $documentSignatories = $signatories->for(
            'vehicle_loan_lifecycle',
        );

        $documentVerification = $verificationService->issue(
            verifiable: $vehicleLoan,
            documentType: 'vehicle_loan_lifecycle',
            documentLabel: 'Form Serah Terima dan Pengembalian Kendaraan Dinas',
            documentReference: $vehicleLoan->loan_number,
            payload: [
                ...$verificationService
                    ->vehicleLoanLifecyclePayload($vehicleLoan),
                'official_signatories' => $documentSignatories,
            ],
            actor: $actor,
            httpRequest: $request,
        );

        $verificationQrDataUri = $verificationService->qrDataUri(
            $documentVerification,
            $qrCodes,
        );

        $pdf = Pdf::loadView('vehicle-loans.lifecycle.pdf', [
            'vehicleLoan' => $vehicleLoan,
            'documentVerification' => $documentVerification,
            'verificationQrDataUri' => $verificationQrDataUri,
            'checkoutOfficerSignature' => $signatureData['checkoutOfficerSignature'],
            'pickupSignature' => $signatureData['pickupSignature'],
            'returnBorrowerSignature' => $signatureData['returnBorrowerSignature'],
            'returnOfficerSignature' => $signatureData['returnOfficerSignature'],
            'evidenceData' => $evidenceData,
            'documentSignatories' => $documentSignatories,
            'institutionName' => (string) config(
                'simantap.institution.name',
                'Badan Pusat Statistik',
            ),
            'institutionShortName' => (string) config(
                'simantap.institution.short_name',
                'BPS',
            ),
            'displayTimezone' => $this->displayTimezone(),
        ])->setPaper('a4', 'portrait');

        $service->auditLifecyclePdfDownload(
            $vehicleLoan,
            $actor,
            $request,
        );

        return $pdf->download(
            str_replace('/', '-', $vehicleLoan->loan_number)
                .'-SERAH-TERIMA.pdf',
        );
    }

    /**
     * @return list<string>
     */
    private function lifecycleRelations(): array
    {
        return [
            'borrower:id,employee_number,name,phone,work_unit,position',
            'vehicle:id,public_id,vehicle_code,license_plate,brand,model,status,current_odometer,registration_expiry_date,storage_location,responsible_person',
            'conditionChecks.checker:id,name,employee_number',
            'conditionChecks.attachments',
            'statusHistories.changer:id,name',
            'signatures.signer:id,name',
        ];
    }

    /**
     * @return array<string, int>
     */
    private function adminSummary(): array
    {
        return [
            'ready_for_checkout' => VehicleLoan::query()
                ->where('status', VehicleLoanStatus::Approved->value)
                ->count(),
            'ready_for_pickup' => VehicleLoan::query()
                ->where('status', VehicleLoanStatus::ReadyForPickup->value)
                ->count(),
            'borrowed' => VehicleLoan::query()
                ->where('status', VehicleLoanStatus::Borrowed->value)
                ->count(),
            'awaiting_return' => VehicleLoan::query()
                ->where(
                    'status',
                    VehicleLoanStatus::AwaitingReturnInspection->value,
                )
                ->count(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function employeeSummary(int $borrowerId): array
    {
        $query = VehicleLoan::query()
            ->where('borrower_id', $borrowerId);

        return [
            'ready_for_checkout' => (clone $query)
                ->where('status', VehicleLoanStatus::Approved->value)
                ->count(),
            'ready_for_pickup' => (clone $query)
                ->where('status', VehicleLoanStatus::ReadyForPickup->value)
                ->count(),
            'borrowed' => (clone $query)
                ->where('status', VehicleLoanStatus::Borrowed->value)
                ->count(),
            'awaiting_return' => (clone $query)
                ->where(
                    'status',
                    VehicleLoanStatus::AwaitingReturnInspection->value,
                )
                ->count(),
        ];
    }

    private function displayTimezone(): string
    {
        return (string) config(
            'simantap.display_timezone',
            'Asia/Jakarta',
        );
    }
}
