<?php

namespace App\Http\Controllers;

use App\Enums\MaintenanceStatus;
use App\Enums\OperationalAssetStatus;
use App\Enums\OperationalAssetType;
use App\Http\Requests\StoreOperationalAssetRequest;
use App\Http\Requests\UpdateOperationalAssetRequest;
use App\Models\OperationalAsset;
use App\Services\OperationalAssetService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OperationalAssetController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', Rule::in(OperationalAssetType::values())],
            'status' => ['nullable', Rule::in(OperationalAssetStatus::values())],
            'active' => ['nullable', 'in:active,inactive'],
        ]);
        $search = trim((string) ($filters['q'] ?? ''));
        $type = (string) ($filters['type'] ?? '');
        $status = (string) ($filters['status'] ?? '');
        $active = (string) ($filters['active'] ?? '');
        $baseQuery = OperationalAsset::query();
        $query = (clone $baseQuery)
            ->when($search !== '', static function (Builder $assetQuery) use ($search): void {
                $assetQuery->where(function (Builder $nested) use ($search): void {
                    $nested
                        ->where('asset_code', 'like', "%{$search}%")
                        ->orWhere('bmn_code', 'like', "%{$search}%")
                        ->orWhere('nup', 'like', "%{$search}%")
                        ->orWhere('register_code', 'like', "%{$search}%")
                        ->orWhere('brand', 'like', "%{$search}%")
                        ->orWhere('model', 'like', "%{$search}%")
                        ->orWhere('serial_number', 'like', "%{$search}%")
                        ->orWhere('location', 'like', "%{$search}%")
                        ->orWhere('responsible_person', 'like', "%{$search}%");
                });
            })
            ->when($type !== '', static fn (Builder $assetQuery): Builder => $assetQuery->where('type', $type))
            ->when($status !== '', static fn (Builder $assetQuery): Builder => $assetQuery->where('status', $status))
            ->when($active !== '', static fn (Builder $assetQuery): Builder => $assetQuery->where('is_active', $active === 'active'));

        return view('operational-assets.index', [
            'assets' => $query
                ->orderByDesc('is_active')
                ->orderBy('type')
                ->orderBy('asset_code')
                ->paginate(15)
                ->withQueryString(),
            'typeOptions' => OperationalAssetType::cases(),
            'statusOptions' => OperationalAssetStatus::cases(),
            'filters' => compact('search', 'type', 'status', 'active'),
            'summary' => [
                'total' => (clone $baseQuery)->count(),
                'available' => (clone $baseQuery)
                    ->where('is_active', true)
                    ->where('status', OperationalAssetStatus::Available->value)
                    ->count(),
                'maintenance' => (clone $baseQuery)
                    ->where('is_active', true)
                    ->where('status', OperationalAssetStatus::Maintenance->value)
                    ->count(),
                'attention' => (clone $baseQuery)
                    ->where('is_active', true)
                    ->whereIn('status', [
                        OperationalAssetStatus::Inspection->value,
                        OperationalAssetStatus::Damaged->value,
                    ])
                    ->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('operational-assets.create', [
            'asset' => null,
            'typeOptions' => OperationalAssetType::cases(),
            'statusOptions' => OperationalAssetStatus::manuallyManagedCases(),
        ]);
    }

    public function store(
        StoreOperationalAssetRequest $request,
        OperationalAssetService $service,
    ): RedirectResponse {
        $actor = $request->user();
        abort_if($actor === null, 401);

        $asset = $service->create(
            $request->validated(),
            $actor,
            $request,
        );

        return redirect()
            ->route('operational-assets.show', $asset)
            ->with('status', 'Aset perangkat berhasil ditambahkan.');
    }

    public function show(OperationalAsset $operationalAsset): View
    {
        $operationalAsset->loadCount('maintenanceRecords');
        $operationalAsset->load([
            'maintenanceRecords' => static fn ($query) => $query
                ->latest('reported_date')
                ->latest('id')
                ->limit(10),
        ]);

        return view('operational-assets.show', [
            'asset' => $operationalAsset,
            'hasActiveMaintenance' => $operationalAsset->maintenanceRecords()
                ->whereIn('status', [
                    MaintenanceStatus::Reported->value,
                    MaintenanceStatus::Approved->value,
                    MaintenanceStatus::InProgress->value,
                    MaintenanceStatus::FurtherActionRequired->value,
                ])
                ->exists(),
            'displayTimezone' => (string) config(
                'simantap.display_timezone',
                'Asia/Jakarta',
            ),
        ]);
    }

    public function edit(OperationalAsset $operationalAsset): View
    {
        return view('operational-assets.edit', [
            'asset' => $operationalAsset,
            'typeOptions' => OperationalAssetType::cases(),
            'statusOptions' => in_array($operationalAsset->status, [
                OperationalAssetStatus::Maintenance,
                OperationalAssetStatus::Inactive,
            ], true)
                ? [$operationalAsset->status]
                : OperationalAssetStatus::manuallyManagedCases(),
        ]);
    }

    public function update(
        UpdateOperationalAssetRequest $request,
        OperationalAsset $operationalAsset,
        OperationalAssetService $service,
    ): RedirectResponse {
        $actor = $request->user();
        abort_if($actor === null, 401);

        $service->update(
            $operationalAsset,
            $request->validated(),
            $actor,
            $request,
        );

        return redirect()
            ->route('operational-assets.show', $operationalAsset)
            ->with('status', 'Data aset perangkat berhasil diperbarui.');
    }

    public function deactivate(
        Request $request,
        OperationalAsset $operationalAsset,
        OperationalAssetService $service,
    ): RedirectResponse {
        $actor = $request->user();
        abort_if($actor === null, 401);

        $service->setActive(
            $operationalAsset,
            false,
            $actor,
            $request,
        );

        return back()->with('status', 'Aset perangkat berhasil dinonaktifkan.');
    }

    public function activate(
        Request $request,
        OperationalAsset $operationalAsset,
        OperationalAssetService $service,
    ): RedirectResponse {
        $actor = $request->user();
        abort_if($actor === null, 401);

        $service->setActive(
            $operationalAsset,
            true,
            $actor,
            $request,
        );

        return back()->with(
            'status',
            'Aset perangkat berhasil diaktifkan dan berstatus Tersedia.',
        );
    }
}
