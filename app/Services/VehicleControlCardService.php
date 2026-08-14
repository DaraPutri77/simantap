<?php

namespace App\Services;

use App\Enums\MaintenanceStatus;
use App\Models\MaintenanceRecord;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Http\Request;

class VehicleControlCardService
{
    private const ROWS_PER_CARD = 20;

    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @return array{
     *     vehicle: Vehicle,
     *     pages: list<list<array{
     *         date: string,
     *         maintenance_type: string,
     *         service_provider: string
     *     }|null>>,
     *     recordCount: int,
     *     rowsPerCard: int
     * }
     */
    public function build(Vehicle $vehicle): array
    {
        $records = $vehicle->maintenanceRecords()
            ->whereIn('status', [
                MaintenanceStatus::Completed->value,
                MaintenanceStatus::CompletedWithNotes->value,
                MaintenanceStatus::SeverelyDamaged->value,
                MaintenanceStatus::Unserviceable->value,
            ])
            ->whereNotNull('completion_date')
            ->orderBy('completion_date')
            ->orderBy('id')
            ->get([
                'id',
                'maintenance_type',
                'service_provider',
                'completion_date',
                'status',
            ]);

        $pages = $records
            ->chunk(self::ROWS_PER_CARD)
            ->map(
                fn ($chunk): array => array_pad(
                    $chunk
                        ->map(
                            static fn (
                                MaintenanceRecord $record,
                            ): array => [
                                'date' => $record->completion_date
                                    ?->format('d/m/Y') ?? '',
                                'maintenance_type' => trim(
                                    (string) $record->maintenance_type,
                                ),
                                'service_provider' => trim(
                                    (string) ($record->service_provider ?? ''),
                                ),
                            ],
                        )
                        ->values()
                        ->all(),
                    self::ROWS_PER_CARD,
                    null,
                ),
            )
            ->values()
            ->all();

        if ($pages === []) {
            $pages = [
                array_fill(0, self::ROWS_PER_CARD, null),
            ];
        }

        return [
            'vehicle' => $vehicle,
            'pages' => $pages,
            'recordCount' => $records->count(),
            'rowsPerCard' => self::ROWS_PER_CARD,
        ];
    }

    public function auditDownload(
        Vehicle $vehicle,
        User $actor,
        int $recordCount,
        ?Request $request = null,
    ): void {
        $this->auditLogger->log(
            event: 'vehicle_control_card_downloaded',
            module: 'vehicle',
            auditable: $vehicle,
            oldValues: [],
            newValues: [
                'document_type' => 'vehicle_control_card',
                'vehicle_code' => $vehicle->vehicle_code,
                'maintenance_row_count' => $recordCount,
            ],
            request: $request,
            actorId: (int) $actor->getKey(),
        );
    }

    public function filename(Vehicle $vehicle): string
    {
        $code = preg_replace(
            '/[^A-Za-z0-9_-]+/',
            '-',
            (string) $vehicle->vehicle_code,
        );

        $code = trim((string) $code, '-');

        if ($code === '') {
            $code = 'KENDARAAN';
        }

        return "KARTU-KENDALI-{$code}.pdf";
    }
}
