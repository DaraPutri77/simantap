<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Vehicle;
use App\Services\AuditLogger;
use App\Services\QrCodeService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class QrCodeController extends Controller
{
    public function itemSvg(
        Request $request,
        Item $item,
        QrCodeService $qrCodes,
        AuditLogger $auditLogger,
    ): Response {
        return $this->downloadSvg(
            request: $request,
            qrCodes: $qrCodes,
            auditLogger: $auditLogger,
            entity: $item,
            type: 'barang',
            code: $item->item_code,
            targetUrl: $qrCodes->itemUrl($item),
        );
    }

    public function vehicleSvg(
        Request $request,
        Vehicle $vehicle,
        QrCodeService $qrCodes,
        AuditLogger $auditLogger,
    ): Response {
        return $this->downloadSvg(
            request: $request,
            qrCodes: $qrCodes,
            auditLogger: $auditLogger,
            entity: $vehicle,
            type: 'kendaraan',
            code: $vehicle->vehicle_code,
            targetUrl: $qrCodes->vehicleUrl($vehicle),
        );
    }

    public function itemLabel(
        Request $request,
        Item $item,
        QrCodeService $qrCodes,
        AuditLogger $auditLogger,
    ): Response {
        $item->load(['category:id,name', 'unit:id,name,symbol']);

        return $this->downloadLabel(
            request: $request,
            qrCodes: $qrCodes,
            auditLogger: $auditLogger,
            entity: $item,
            type: 'barang',
            code: $item->item_code,
            title: $item->name,
            subtitle: $item->category?->name.' · '.$item->unit?->symbol,
            location: $item->storage_location,
            targetUrl: $qrCodes->itemUrl($item),
        );
    }

    public function vehicleLabel(
        Request $request,
        Vehicle $vehicle,
        QrCodeService $qrCodes,
        AuditLogger $auditLogger,
    ): Response {
        return $this->downloadLabel(
            request: $request,
            qrCodes: $qrCodes,
            auditLogger: $auditLogger,
            entity: $vehicle,
            type: 'kendaraan',
            code: $vehicle->vehicle_code,
            title: $vehicle->displayName(),
            subtitle: $vehicle->license_plate,
            location: $vehicle->storage_location,
            targetUrl: $qrCodes->vehicleUrl($vehicle),
        );
    }

    private function downloadSvg(
        Request $request,
        QrCodeService $qrCodes,
        AuditLogger $auditLogger,
        Item|Vehicle $entity,
        string $type,
        string $code,
        string $targetUrl,
    ): Response {
        $auditLogger->log(
            event: 'qr_code_downloaded',
            module: 'qr_code',
            auditable: $entity,
            newValues: [
                'entity_type' => $type,
                'entity_code' => $code,
                'format' => 'svg',
                'target_url' => $targetUrl,
            ],
            request: $request,
        );

        return response($qrCodes->svg($targetUrl, 360), 200, [
            'Content-Disposition' => 'attachment; filename="'
                .$qrCodes->filename($type, $code, 'svg').'"',
            'Content-Type' => 'image/svg+xml; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function downloadLabel(
        Request $request,
        QrCodeService $qrCodes,
        AuditLogger $auditLogger,
        Item|Vehicle $entity,
        string $type,
        string $code,
        string $title,
        string $subtitle,
        ?string $location,
        string $targetUrl,
    ): Response {
        $auditLogger->log(
            event: 'qr_label_downloaded',
            module: 'qr_code',
            auditable: $entity,
            newValues: [
                'entity_type' => $type,
                'entity_code' => $code,
                'format' => 'pdf',
                'target_url' => $targetUrl,
            ],
            request: $request,
        );

        return Pdf::loadView('qr-codes.label', [
            'code' => $code,
            'location' => $location ?: 'Lokasi belum diisi',
            'qrCodeSvg' => $qrCodes->svg($targetUrl, 240),
            'subtitle' => $subtitle,
            'targetUrl' => $targetUrl,
            'title' => $title,
            'type' => $type,
        ])->setPaper([0, 0, 283.46, 170.08])
            ->download($qrCodes->filename($type, $code, 'pdf'));
    }
}
