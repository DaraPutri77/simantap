<?php

namespace App\Services;

use App\Models\DigitalSignature;
use App\Models\DocumentVerification;
use App\Models\InventoryRequest;
use App\Models\MaintenanceRecord;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleConditionCheck;
use App\Models\VehicleLoan;
use BackedEnum;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DocumentVerificationService
{
    private const HASH_ALGORITHM = 'sha256';

    private const PAYLOAD_SCHEMA_VERSION = 1;

    private const TOKEN_BYTES = 32;

    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function issue(
        Model $verifiable,
        string $documentType,
        string $documentLabel,
        string $documentReference,
        array $payload,
        User $actor,
        ?Request $httpRequest = null,
    ): DocumentVerification {
        $documentType = trim($documentType);
        $documentLabel = trim($documentLabel);
        $documentReference = trim($documentReference);

        if (
            $documentType === ''
            || mb_strlen($documentType) > 60
        ) {
            throw new InvalidArgumentException(
                'Jenis dokumen verifikasi tidak valid.',
            );
        }

        if ($documentLabel === '') {
            throw new InvalidArgumentException(
                'Label dokumen verifikasi tidak valid.',
            );
        }

        if (
            $documentReference === ''
            || mb_strlen($documentReference) > 150
        ) {
            throw new InvalidArgumentException(
                'Referensi dokumen verifikasi tidak valid.',
            );
        }

        $payloadHash = $this->fingerprint([
            'document_type' => $documentType,
            'document_label' => $documentLabel,
            'document_reference' => $documentReference,
            'payload_schema_version' => self::PAYLOAD_SCHEMA_VERSION,
            'payload' => $payload,
        ]);

        return DB::transaction(function () use (
            $verifiable,
            $documentType,
            $documentLabel,
            $documentReference,
            $payloadHash,
            $actor,
            $httpRequest,
        ): DocumentVerification {
            $lockedSubject = $verifiable
                ->newQuery()
                ->whereKey($verifiable->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $verifiableType = $lockedSubject
                ->getMorphClass();

            $latest = DocumentVerification::query()
                ->where(
                    'document_type',
                    $documentType,
                )
                ->where(
                    'verifiable_type',
                    $verifiableType,
                )
                ->where(
                    'verifiable_id',
                    $lockedSubject->getKey(),
                )
                ->orderByDesc('version')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if (
                $latest !== null
                && $latest->payload_schema_version
                    === self::PAYLOAD_SCHEMA_VERSION
                && hash_equals(
                    $latest->payload_hash,
                    $payloadHash,
                )
                && ! $latest->isRevoked()
            ) {
                return $latest;
            }

            $verification = DocumentVerification::query()
                ->create([
                    'public_token' => $this->newPublicToken(),
                    'document_type' => $documentType,
                    'verifiable_type' => $verifiableType,
                    'verifiable_id' => $lockedSubject->getKey(),
                    'document_reference' => $documentReference,
                    'version' => ($latest?->version ?? 0) + 1,
                    'payload_schema_version' => self::PAYLOAD_SCHEMA_VERSION,
                    'hash_algorithm' => self::HASH_ALGORITHM,
                    'payload_hash' => $payloadHash,
                    'public_metadata' => [
                        'document_label' => $documentLabel,
                    ],
                    'issued_by' => $actor->getKey(),
                    'issued_at' => now(),
                ]);

            $this->auditLogger->log(
                event: 'document_verification_issued',
                module: 'document_verification',
                auditable: $lockedSubject,
                oldValues: [],
                newValues: [
                    'document_type' => $documentType,
                    'document_reference' => $documentReference,
                    'version' => $verification->version,
                    'payload_schema_version' => self::PAYLOAD_SCHEMA_VERSION,
                    'hash_algorithm' => self::HASH_ALGORITHM,
                    'payload_hash' => $payloadHash,
                ],
                request: $httpRequest,
                actorId: $actor->getKey(),
            );

            return $verification;
        }, 3);
    }

    /**
     * @return array<string, mixed>
     */
    public function inventoryRequestPayload(
        InventoryRequest $inventoryRequest,
    ): array {
        return [
            'request_number' => $inventoryRequest->request_number,
            'request_date' => $inventoryRequest->request_date,
            'requester' => [
                'name' => $inventoryRequest->requester_name_snapshot,
                'employee_number' => $inventoryRequest
                    ->employee_number_snapshot,
                'work_unit' => $inventoryRequest->work_unit_snapshot,
                'position' => $inventoryRequest->requester?->position,
            ],
            'purpose' => $inventoryRequest->purpose,
            'notes' => $inventoryRequest->notes,
            'status' => $inventoryRequest->status,
            'admin_notes' => $inventoryRequest->admin_notes,
            'received_at' => $inventoryRequest->received_at,
            'approver_display' => [
                'name' => $inventoryRequest->approver?->name,
                'position' => $inventoryRequest->approver?->position,
            ],
            'items' => $inventoryRequest->items
                ->sortBy('id')
                ->map(
                    static fn ($item): array => [
                        'item_code' => $item->item_code_snapshot,
                        'item_name' => $item->item_name_snapshot,
                        'unit' => $item->unit_snapshot,
                        'requested_quantity' => $item->requested_quantity,
                        'approved_quantity' => $item->approved_quantity,
                        'delivered_quantity' => $item->delivered_quantity,
                        'notes' => $item->notes,
                        'admin_notes' => $item->admin_notes,
                    ],
                )
                ->values()
                ->all(),
            'signatures' => [
                'submission' => $this->signatureFingerprint(
                    $inventoryRequest->submissionSignature(),
                ),
                'approval' => $this->signatureFingerprint(
                    $inventoryRequest->approvalSignature(),
                ),
                'receipt' => $this->signatureFingerprint(
                    $inventoryRequest->receiptSignature(),
                ),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function vehicleLoanPayload(
        VehicleLoan $vehicleLoan,
    ): array {
        $returnRequestHistory = $vehicleLoan->returnRequestHistory();
        $returnCheck = $vehicleLoan->returnCheck();
        $hasReturnData = $vehicleLoan->actual_end_at !== null
            || $returnRequestHistory !== null
            || $returnCheck !== null;

        return [
            'public_id' => $vehicleLoan->public_id,
            'loan_number' => $vehicleLoan->loan_number,
            'status' => $vehicleLoan->status,
            'borrower' => [
                'name' => $vehicleLoan->borrower_name_snapshot,
                'employee_number' => $vehicleLoan
                    ->employee_number_snapshot,
                'work_unit' => $vehicleLoan->work_unit_snapshot,
                'phone' => $vehicleLoan->phone_snapshot,
            ],
            'vehicle' => [
                'vehicle_code' => $vehicleLoan->vehicle_code_snapshot,
                'license_plate' => $vehicleLoan
                    ->license_plate_snapshot,
                'vehicle_name' => $vehicleLoan->vehicle_name_snapshot,
            ],
            'planned_start_at' => $vehicleLoan->planned_start_at,
            'planned_end_at' => $vehicleLoan->planned_end_at,
            'destination' => $vehicleLoan->destination,
            'purpose' => $vehicleLoan->purpose,
            'reason' => $vehicleLoan->reason,
            'admin_notes' => $vehicleLoan->admin_notes,
            'rejection_reason' => $vehicleLoan->rejection_reason,
            'cancellation_reason' => $vehicleLoan
                ->cancellation_reason,
            ...($hasReturnData ? [
                'return' => [
                    'returned_at' => $vehicleLoan->actual_end_at,
                    'request_notes' => $returnRequestHistory?->notes,
                    'condition_check' => $this->conditionCheckFingerprint(
                        $returnCheck,
                    ),
                ],
            ] : []),
            'signatures' => [
                'submission' => $this->signatureFingerprint(
                    $vehicleLoan->submissionSignature(),
                ),
                'approval' => $this->signatureFingerprint(
                    $vehicleLoan->approvalSignature(),
                ),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function vehicleLoanLifecyclePayload(
        VehicleLoan $vehicleLoan,
    ): array {
        return [
            'loan_number' => $vehicleLoan->loan_number,
            'status' => $vehicleLoan->status,
            'borrower' => [
                'name' => $vehicleLoan->borrower_name_snapshot,
                'employee_number' => $vehicleLoan
                    ->employee_number_snapshot,
                'work_unit' => $vehicleLoan->work_unit_snapshot,
                'phone' => $vehicleLoan->phone_snapshot,
            ],
            'vehicle' => [
                'vehicle_code' => $vehicleLoan->vehicle_code_snapshot,
                'license_plate' => $vehicleLoan
                    ->license_plate_snapshot,
                'vehicle_name' => $vehicleLoan->vehicle_name_snapshot,
            ],
            'destination' => $vehicleLoan->destination,
            'purpose' => $vehicleLoan->purpose,
            'planned_start_at' => $vehicleLoan->planned_start_at,
            'planned_end_at' => $vehicleLoan->planned_end_at,
            'actual_start_at' => $vehicleLoan->actual_start_at,
            'actual_end_at' => $vehicleLoan->actual_end_at,
            'overdue_at' => $vehicleLoan->overdue_at,
            'checkout' => $this->conditionCheckFingerprint(
                $vehicleLoan->checkoutCheck(),
            ),
            'return' => $this->conditionCheckFingerprint(
                $vehicleLoan->returnCheck(),
            ),
            'pickup_signature' => $this->signatureFingerprint(
                $vehicleLoan->pickupSignature(),
            ),
            'checkout_confirmation_signature' => $this->signatureFingerprint(
                $vehicleLoan->checkoutConfirmationSignature(),
            ),
            'return_request_signature' => $this->signatureFingerprint(
                $vehicleLoan->returnRequestSignature(),
            ),
            'return_confirmation_signature' => $this->signatureFingerprint(
                $vehicleLoan->returnConfirmationSignature(),
            ),
            'status_histories' => $vehicleLoan->statusHistories
                ->map(
                    static fn ($history): array => [
                        'previous_status' => $history->previous_status,
                        'new_status' => $history->new_status,
                        'notes' => $history->notes,
                        'changed_at' => $history->changed_at,
                        'changer_name' => $history->changer?->name
                            ?: 'Sistem',
                    ],
                )
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function maintenanceRecordPayload(
        MaintenanceRecord $maintenanceRecord,
    ): array {
        $subject = $maintenanceRecord->vehicle !== null
            ? [
                'type' => 'vehicle',
                'snapshot' => $maintenanceRecord->vehicle_snapshot,
                'code' => $maintenanceRecord->vehicle->vehicle_code,
                'license_plate' => $maintenanceRecord->vehicle->license_plate,
                'name' => $maintenanceRecord->vehicle->displayName(),
                'status' => $maintenanceRecord->vehicle->status,
            ]
            : [
                'type' => 'operational_asset',
                'snapshot' => $maintenanceRecord
                    ->operational_asset_snapshot,
                'code' => $maintenanceRecord
                    ->operationalAsset?->asset_code,
                'administrative_code' => $maintenanceRecord
                    ->operationalAsset?->administrativeCode(),
                'name' => $maintenanceRecord
                    ->operationalAsset?->displayName(),
                'status' => $maintenanceRecord
                    ->operationalAsset?->status,
            ];

        return [
            'public_id' => $maintenanceRecord->public_id,
            'maintenance_number' => $maintenanceRecord
                ->maintenance_number,
            'status' => $maintenanceRecord->status,
            'subject' => $subject,
            'source_vehicle_loan' => $maintenanceRecord
                ->sourceVehicleLoan?->loan_number,
            'maintenance_type' => $maintenanceRecord
                ->maintenance_type,
            'complaint' => $maintenanceRecord->complaint,
            'initial_condition' => $maintenanceRecord
                ->initial_condition,
            'service_provider' => $maintenanceRecord
                ->service_provider,
            'reported_date' => $maintenanceRecord->reported_date,
            'start_date' => $maintenanceRecord->start_date,
            'completion_date' => $maintenanceRecord
                ->completion_date,
            'cost' => $maintenanceRecord->cost,
            'result' => $maintenanceRecord->result,
            'final_condition' => $maintenanceRecord
                ->final_condition,
            'approval_notes' => $maintenanceRecord->approval_notes,
            'cancellation_reason' => $maintenanceRecord
                ->cancellation_reason,
            'people' => [
                'reporter' => $this->userFingerprint(
                    $maintenanceRecord->reporter,
                ),
                'handler' => $this->userFingerprint(
                    $maintenanceRecord->handler,
                ),
                'approver' => $this->userFingerprint(
                    $maintenanceRecord->approver,
                ),
                'canceller' => $this->userFingerprint(
                    $maintenanceRecord->canceller,
                ),
            ],
            'timestamps' => [
                'approved_at' => $maintenanceRecord->approved_at,
                'started_at' => $maintenanceRecord->started_at,
                'completed_at' => $maintenanceRecord->completed_at,
                'cancelled_at' => $maintenanceRecord->cancelled_at,
            ],
            'attachments' => $maintenanceRecord->attachments
                ->sortBy('id')
                ->map(
                    static fn ($attachment): array => [
                        'category' => $attachment->file_category,
                        'original_name' => $attachment->original_name,
                        'mime_type' => $attachment->mime_type,
                        'file_size' => $attachment->file_size,
                        'checksum' => $attachment->checksum,
                    ],
                )
                ->values()
                ->all(),
            'status_histories' => $maintenanceRecord
                ->statusHistories
                ->map(
                    fn ($history): array => [
                        'previous_status' => $history->previous_status,
                        'new_status' => $history->new_status,
                        'notes' => $history->notes,
                        'changed_at' => $history->changed_at,
                        'changer' => $this->userFingerprint(
                            $history->changer,
                        ),
                    ],
                )
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array{
     *     pages: list<list<array{
     *         date: string,
     *         maintenance_type: string,
     *         service_provider: string
     *     }|null>>,
     *     recordCount: int,
     *     rowsPerCard: int
     * }  $data
     * @return array<string, mixed>
     */
    public function vehicleControlCardPayload(
        Vehicle $vehicle,
        array $data,
    ): array {
        return [
            'vehicle' => [
                'vehicle_code' => $vehicle->vehicle_code,
                'display_name' => $vehicle->displayName(),
                'license_plate' => $vehicle->license_plate,
                'brand_type' => trim(
                    $vehicle->brand.' '.$vehicle->model,
                ),
                'responsible_person' => $vehicle
                    ->responsible_person,
            ],
            'pages' => $data['pages'],
            'record_count' => (int) $data['recordCount'],
            'rows_per_card' => (int) $data['rowsPerCard'],
        ];
    }

    public function qrDataUri(
        DocumentVerification $verification,
        QrCodeService $qrCodes,
        int $size = 84,
    ): string {
        $svg = $qrCodes->svg(
            $this->verificationUrl($verification),
            $size,
        );

        return 'data:image/svg+xml;base64,'
            .base64_encode($svg);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function fingerprint(array $payload): string
    {
        $normalized = $this->normalize($payload);

        $json = json_encode(
            $normalized,
            JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_PRESERVE_ZERO_FRACTION
                | JSON_THROW_ON_ERROR,
        );

        return hash(
            self::HASH_ALGORITHM,
            $json,
        );
    }

    public function verificationUrl(
        DocumentVerification $verification,
    ): string {
        return route(
            'document-verifications.show',
            [
                'token' => $verification->public_token,
            ],
        );
    }

    private function newPublicToken(): string
    {
        do {
            $token = bin2hex(
                random_bytes(self::TOKEN_BYTES),
            );
        } while (
            DocumentVerification::query()
                ->where('public_token', $token)
                ->exists()
        );

        return $token;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function signatureFingerprint(
        ?DigitalSignature $signature,
    ): ?array {
        if ($signature === null) {
            return null;
        }

        return [
            'purpose' => $signature->purpose,
            'version' => $signature->version,
            'signer_name' => $signature->signer_name_snapshot,
            'employee_number' => $signature
                ->employee_number_snapshot,

            'image_checksum' => $signature->image_checksum,
            'signed_at' => $signature->signed_at,
        ];
    }

    /**
     * @return array<string, string|null>|null
     */
    private function userFingerprint(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        return [
            'name' => $user->name,
            'employee_number' => $user->employee_number,
            'position' => $user->position,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function conditionCheckFingerprint(
        ?VehicleConditionCheck $conditionCheck,
    ): ?array {
        if ($conditionCheck === null) {
            return null;
        }

        return [
            'check_type' => $conditionCheck->check_type,
            'checker' => [
                'name' => $conditionCheck->checker_name_snapshot
                    ?: $conditionCheck->checker?->name,
                'employee_number' => $conditionCheck
                    ->checker_employee_number_snapshot
                    ?: $conditionCheck->checker?->employee_number,
            ],
            'checked_at' => $conditionCheck->checked_at,
            'borrower_confirmed_at' => $conditionCheck
                ->borrower_confirmed_at,
            'odometer' => $conditionCheck->odometer,
            'fuel_level' => $conditionCheck->fuel_level,
            'overall_condition' => $conditionCheck
                ->overall_condition,
            'body_condition' => $conditionCheck->body_condition,
            'engine_condition' => $conditionCheck
                ->engine_condition,
            'tire_condition' => $conditionCheck->tire_condition,
            'equipment_condition' => $conditionCheck
                ->equipment_condition,
            'damage_notes' => $conditionCheck->damage_notes,
            'attachments' => $conditionCheck->attachments
                ->sortBy('id')
                ->map(
                    static fn ($attachment): array => [
                        'category' => $attachment->file_category,
                        'mime_type' => $attachment->mime_type,
                        'file_size' => $attachment->file_size,
                        'checksum' => $attachment->checksum,
                    ],
                )
                ->values()
                ->all(),
        ];
    }

    private function normalize(mixed $value): mixed
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof CarbonInterface) {
            return CarbonImmutable::instance($value)
                ->utc()
                ->format('Y-m-d\TH:i:s\Z');
        }

        if (! is_array($value)) {
            if (
                $value === null
                || is_scalar($value)
            ) {
                return $value;
            }

            throw new InvalidArgumentException(
                'Canonical document payload berisi nilai yang tidak didukung.',
            );
        }

        if (array_is_list($value)) {
            return array_map(
                fn (mixed $item): mixed => $this
                    ->normalize($item),
                $value,
            );
        }

        $keys = array_keys($value);

        sort(
            $keys,
            SORT_STRING,
        );

        $normalized = [];

        foreach ($keys as $key) {
            $normalized[$key] = $this->normalize(
                $value[$key],
            );
        }

        return $normalized;
    }
}
