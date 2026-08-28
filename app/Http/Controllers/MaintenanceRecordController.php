<?php

namespace App\Http\Controllers;

use App\Enums\MaintenanceStatus;
use App\Enums\MaintenanceSubjectType;
use App\Enums\OperationalAssetStatus;
use App\Enums\VehicleLoanStatus;
use App\Enums\VehicleStatus;
use App\Http\Requests\ApproveMaintenanceRecordRequest;
use App\Http\Requests\CancelMaintenanceRecordRequest;
use App\Http\Requests\CompleteMaintenanceRecordRequest;
use App\Http\Requests\StartMaintenanceRecordRequest;
use App\Http\Requests\StoreMaintenanceRecordRequest;
use App\Models\Attachment;
use App\Models\MaintenanceRecord;
use App\Models\OperationalAsset;
use App\Models\Vehicle;
use App\Models\VehicleLoan;
use App\Services\DocumentSignatoryService;
use App\Services\DocumentVerificationService;
use App\Services\MaintenanceService;
use App\Services\QrCodeService;
use App\Support\AttachmentIntegrity;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MaintenanceRecordController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', MaintenanceRecord::class);

        $query = MaintenanceRecord::query()
            ->with([
                'vehicle:id,public_id,vehicle_code,license_plate,brand,model,status,is_active',
                'operationalAsset:id,public_id,asset_code,type,brand,model,status,is_active',
                'reporter:id,name',
                'handler:id,name',
                'sourceVehicleLoan:id,public_id,loan_number,status',
            ]);

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('maintenance_number', 'like', "%{$search}%")
                    ->orWhere('maintenance_type', 'like', "%{$search}%")
                    ->orWhere('complaint', 'like', "%{$search}%")
                    ->orWhereHas('vehicle', static function (Builder $vehicleQuery) use ($search): void {
                        $vehicleQuery
                            ->where('vehicle_code', 'like', "%{$search}%")
                            ->orWhere('license_plate', 'like', "%{$search}%")
                            ->orWhere('brand', 'like', "%{$search}%")
                            ->orWhere('model', 'like', "%{$search}%");
                    })
                    ->orWhereHas('operationalAsset', static function (Builder $assetQuery) use ($search): void {
                        $assetQuery
                            ->where('asset_code', 'like', "%{$search}%")
                            ->orWhere('bmn_code', 'like', "%{$search}%")
                            ->orWhere('nup', 'like', "%{$search}%")
                            ->orWhere('register_code', 'like', "%{$search}%")
                            ->orWhere('brand', 'like', "%{$search}%")
                            ->orWhere('model', 'like', "%{$search}%");
                    });
            });
        }

        $status = (string) $request->query('status', '');
        if ($status !== '' && in_array($status, MaintenanceStatus::values(), true)) {
            $query->where('status', $status);
        }

        $year = (string) $request->query('year', '');
        if (preg_match('/^\d{4}$/', $year) === 1) {
            $query->whereYear('reported_date', (int) $year);
        }

        $records = $query
            ->latest('reported_date')
            ->latest('id')
            ->paginate((int) config('simantap.pagination.per_page', 15))
            ->withQueryString();

        return view('maintenance-records.index', [
            'records' => $records,
            'summary' => $this->summary(),
            'statusOptions' => MaintenanceStatus::options(),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', MaintenanceRecord::class);

        $operationalAssets = $this->availableOperationalAssets();
        $selectedAsset = $operationalAssets->firstWhere(
            'public_id',
            (string) $request->query('operational_asset', ''),
        );

        return view('maintenance-records.create', [
            'vehicles' => $this->availableVehicles(),
            'operationalAssets' => $operationalAssets,
            'returnIssues' => $this->eligibleReturnIssues(),
            'selectedLoan' => null,
            'selectedAsset' => $selectedAsset,
            'selectedSubjectType' => $selectedAsset === null
                ? MaintenanceSubjectType::Vehicle
                : MaintenanceSubjectType::OperationalAsset,
            'displayTimezone' => $this->displayTimezone(),
        ]);
    }

    public function createFromLoan(
        Request $request,
        VehicleLoan $vehicleLoan,
    ): View {
        Gate::authorize('create', MaintenanceRecord::class);

        abort_unless($vehicleLoan->status === VehicleLoanStatus::ReturnIssue, 404);
        abort_if(
            MaintenanceRecord::query()
                ->where('source_vehicle_loan_id', $vehicleLoan->getKey())
                ->exists(),
            409,
        );

        $vehicleLoan->load([
            'vehicle:id,public_id,vehicle_code,license_plate,brand,model,status,is_active',
            'borrower:id,name,employee_number',
            'conditionChecks.attachments',
        ]);

        return view('maintenance-records.create', [
            'vehicles' => collect([$vehicleLoan->vehicle]),
            'operationalAssets' => collect(),
            'returnIssues' => collect([$vehicleLoan]),
            'selectedLoan' => $vehicleLoan,
            'selectedAsset' => null,
            'selectedSubjectType' => MaintenanceSubjectType::Vehicle,
            'displayTimezone' => $this->displayTimezone(),
        ]);
    }

    public function store(
        StoreMaintenanceRecordRequest $request,
        MaintenanceService $service,
    ): RedirectResponse {
        $actor = $request->user();
        abort_if($actor === null, 401);

        $data = $request->validated();
        $subjectType = MaintenanceSubjectType::from(
            (string) $data['subject_type'],
        );

        if ($subjectType === MaintenanceSubjectType::OperationalAsset) {
            $asset = OperationalAsset::query()
                ->where('public_id', $data['operational_asset_public_id'])
                ->firstOrFail();

            $record = $service->reportOperationalAsset(
                $asset,
                $data,
                $actor,
                $request,
            );
        } else {
            $vehicle = Vehicle::query()
                ->where('public_id', $data['vehicle_public_id'])
                ->firstOrFail();

            $sourceLoan = null;
            if (! empty($data['source_vehicle_loan_public_id'])) {
                $sourceLoan = VehicleLoan::query()
                    ->where('public_id', $data['source_vehicle_loan_public_id'])
                    ->firstOrFail();
            }

            $record = $service->report(
                $vehicle,
                $sourceLoan,
                $data,
                $actor,
                $request,
            );
        }

        return redirect()
            ->route('maintenance-records.show', $record)
            ->with('status', 'Laporan pemeliharaan berhasil dibuat.');
    }

    public function show(MaintenanceRecord $maintenanceRecord): View
    {
        Gate::authorize('view', $maintenanceRecord);

        $maintenanceRecord->load([
            'vehicle:id,public_id,vehicle_code,license_plate,brand,model,status,current_odometer,is_active',
            'operationalAsset:id,public_id,asset_code,bmn_code,nup,register_code,type,brand,model,serial_number,acquisition_year,location,responsible_person,status,is_active',
            'sourceVehicleLoan.borrower:id,name,employee_number',
            'reporter:id,name,employee_number',
            'handler:id,name,employee_number',
            'approver:id,name,employee_number',
            'canceller:id,name,employee_number',
            'attachments.uploader:id,name',
            'statusHistories.changer:id,name',
        ]);

        return view('maintenance-records.show', [
            'maintenanceRecord' => $maintenanceRecord,
            'completionStatuses' => [
                MaintenanceStatus::Completed,
                MaintenanceStatus::CompletedWithNotes,
                MaintenanceStatus::FurtherActionRequired,
                MaintenanceStatus::SeverelyDamaged,
                MaintenanceStatus::Unserviceable,
            ],
            'displayTimezone' => $this->displayTimezone(),
        ]);
    }

    public function approve(
        ApproveMaintenanceRecordRequest $request,
        MaintenanceRecord $maintenanceRecord,
        MaintenanceService $service,
    ): RedirectResponse {
        Gate::authorize('approve', $maintenanceRecord);
        $actor = $request->user();
        abort_if($actor === null, 401);

        $service->approve(
            $maintenanceRecord,
            $request->validated(),
            $actor,
            $request,
        );

        return $this->redirectToShow(
            $maintenanceRecord,
            'Pemeliharaan disetujui dan siap dimulai.',
        );
    }

    public function start(
        StartMaintenanceRecordRequest $request,
        MaintenanceRecord $maintenanceRecord,
        MaintenanceService $service,
    ): RedirectResponse {
        Gate::authorize('start', $maintenanceRecord);
        $actor = $request->user();
        abort_if($actor === null, 401);

        $service->start(
            $maintenanceRecord,
            $request->validated(),
            $actor,
            $request,
        );

        return $this->redirectToShow(
            $maintenanceRecord,
            'Pengerjaan pemeliharaan telah dimulai.',
        );
    }

    public function complete(
        CompleteMaintenanceRecordRequest $request,
        MaintenanceRecord $maintenanceRecord,
        MaintenanceService $service,
    ): RedirectResponse {
        Gate::authorize('complete', $maintenanceRecord);
        $actor = $request->user();
        abort_if($actor === null, 401);

        $result = $service->complete(
            $maintenanceRecord,
            $request->validated(),
            $actor,
            $request,
        );

        return $this->redirectToShow(
            $result,
            'Hasil pemeliharaan berhasil disimpan dan status subjek telah diselaraskan.',
        );
    }

    public function cancel(
        CancelMaintenanceRecordRequest $request,
        MaintenanceRecord $maintenanceRecord,
        MaintenanceService $service,
    ): RedirectResponse {
        Gate::authorize('cancel', $maintenanceRecord);
        $actor = $request->user();
        abort_if($actor === null, 401);

        $service->cancel(
            $maintenanceRecord,
            $request->validated(),
            $actor,
            $request,
        );

        return $this->redirectToShow(
            $maintenanceRecord,
            'Pemeliharaan dibatalkan dan status subjek telah diselaraskan.',
        );
    }

    public function evidence(
        MaintenanceRecord $maintenanceRecord,
        Attachment $attachment,
    ): StreamedResponse {
        Gate::authorize('view', $maintenanceRecord);

        $belongsToRecord = $maintenanceRecord->attachments()
            ->whereKey($attachment->getKey())
            ->exists();
        abort_unless($belongsToRecord, 404);

        $disk = Storage::disk($attachment->disk);
        abort_unless($disk->exists($attachment->file_path), 404);

        abort_unless(
            AttachmentIntegrity::checksumMatches(
                $attachment,
            ),
            409,
            'Integritas bukti pemeliharaan tidak valid.',
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
        MaintenanceRecord $maintenanceRecord,
        MaintenanceService $service,
        DocumentVerificationService $verificationService,
        QrCodeService $qrCodes,
        DocumentSignatoryService $signatories,
    ): Response {
        Gate::authorize('view', $maintenanceRecord);

        $actor = $request->user();
        abort_if($actor === null, 401);

        $maintenanceRecord->load([
            'vehicle:id,public_id,vehicle_code,license_plate,brand,model,status,current_odometer,is_active',
            'operationalAsset:id,public_id,asset_code,bmn_code,nup,register_code,type,brand,model,serial_number,acquisition_year,location,responsible_person,status,is_active',
            'sourceVehicleLoan:id,public_id,loan_number',
            'reporter:id,name,employee_number,position',
            'handler:id,name,employee_number,position',
            'approver:id,name,employee_number,position',
            'canceller:id,name,employee_number,position',
            'attachments.uploader:id,name,employee_number',
            'statusHistories.changer:id,name,employee_number,position',
        ]);

        $evidenceData = [];

        foreach ($maintenanceRecord->attachments as $attachment) {
            abort_unless(
                AttachmentIntegrity::checksumMatches($attachment),
                409,
                'Integritas bukti pemeliharaan gagal diverifikasi.',
            );

            $evidenceData[$attachment->getKey()] =
                AttachmentIntegrity::dataUri($attachment);
        }

        $documentSignatories = $signatories->for(
            'maintenance_record',
        );

        $documentVerification = $verificationService->issue(
            verifiable: $maintenanceRecord,
            documentType: 'maintenance_record',
            documentLabel: 'Laporan Pemeliharaan Aset dan Kendaraan',
            documentReference: $maintenanceRecord
                ->maintenance_number,
            payload: [
                ...$verificationService
                    ->maintenanceRecordPayload($maintenanceRecord),
                'official_signatories' => $documentSignatories,
            ],
            actor: $actor,
            httpRequest: $request,
        );

        $verificationQrDataUri = $verificationService->qrDataUri(
            $documentVerification,
            $qrCodes,
        );

        $pdf = Pdf::loadView('maintenance-records.pdf', [
            'maintenanceRecord' => $maintenanceRecord,
            'documentVerification' => $documentVerification,
            'verificationQrDataUri' => $verificationQrDataUri,
            'evidenceData' => $evidenceData,
            'documentSignatories' => $documentSignatories,
            'institutionName' => (string) config(
                'simantap.institution.name',
                'Badan Pusat Statistik Kabupaten Jombang',
            ),
            'institutionShortName' => (string) config(
                'simantap.institution.short_name',
                'BPS Kabupaten Jombang',
            ),
            'displayTimezone' => $this->displayTimezone(),
        ])->setPaper('a4', 'portrait');

        $service->auditPdfDownload(
            $maintenanceRecord,
            $actor,
            $request,
        );

        $safeNumber = preg_replace(
            '/[^A-Za-z0-9_-]+/',
            '-',
            $maintenanceRecord->maintenance_number,
        ) ?: 'PEMELIHARAAN';

        return $pdf->download(
            $safeNumber.'-PEMELIHARAAN.pdf',
        );
    }

    /**
     * @return Collection<int, Vehicle>
     */
    private function availableVehicles()
    {
        return Vehicle::query()
            ->where('is_active', true)
            ->whereIn('status', [
                VehicleStatus::Available->value,
                VehicleStatus::Inspection->value,
                VehicleStatus::Damaged->value,
                VehicleStatus::Maintenance->value,
            ])
            ->whereDoesntHave('maintenanceRecords', static function (Builder $query): void {
                $query->whereIn('status', [
                    MaintenanceStatus::Reported->value,
                    MaintenanceStatus::Approved->value,
                    MaintenanceStatus::InProgress->value,
                    MaintenanceStatus::FurtherActionRequired->value,
                ]);
            })
            ->orderBy('vehicle_code')
            ->get();
    }

    /**
     * @return Collection<int, OperationalAsset>
     */
    private function availableOperationalAssets()
    {
        return OperationalAsset::query()
            ->where('is_active', true)
            ->whereIn('status', [
                OperationalAssetStatus::Available->value,
                OperationalAssetStatus::Inspection->value,
                OperationalAssetStatus::Maintenance->value,
                OperationalAssetStatus::Damaged->value,
            ])
            ->whereDoesntHave('maintenanceRecords', static function (Builder $query): void {
                $query->whereIn('status', [
                    MaintenanceStatus::Reported->value,
                    MaintenanceStatus::Approved->value,
                    MaintenanceStatus::InProgress->value,
                    MaintenanceStatus::FurtherActionRequired->value,
                ]);
            })
            ->orderBy('type')
            ->orderBy('asset_code')
            ->get();
    }

    /**
     * @return Collection<int, VehicleLoan>
     */
    private function eligibleReturnIssues()
    {
        return VehicleLoan::query()
            ->where('status', VehicleLoanStatus::ReturnIssue->value)
            ->whereDoesntHave('maintenanceRecords')
            ->with([
                'vehicle:id,public_id,vehicle_code,license_plate,brand,model,status,is_active',
                'borrower:id,name,employee_number',
            ])
            ->latest('actual_end_at')
            ->get();
    }

    /**
     * @return array<string, int>
     */
    private function summary(): array
    {
        return [
            'reported' => MaintenanceRecord::query()
                ->where('status', MaintenanceStatus::Reported->value)
                ->count(),
            'approved' => MaintenanceRecord::query()
                ->where('status', MaintenanceStatus::Approved->value)
                ->count(),
            'in_progress' => MaintenanceRecord::query()
                ->where('status', MaintenanceStatus::InProgress->value)
                ->count(),
            'further_action' => MaintenanceRecord::query()
                ->where('status', MaintenanceStatus::FurtherActionRequired->value)
                ->count(),
        ];
    }

    private function redirectToShow(
        MaintenanceRecord $maintenanceRecord,
        string $message,
    ): RedirectResponse {
        return redirect()
            ->route('maintenance-records.show', $maintenanceRecord->fresh())
            ->with('status', $message);
    }

    private function displayTimezone(): string
    {
        return (string) config(
            'simantap.display_timezone',
            'Asia/Jakarta',
        );
    }
}
