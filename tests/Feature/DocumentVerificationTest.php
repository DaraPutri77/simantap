<?php

namespace Tests\Feature;

use App\Enums\VehicleStatus;
use App\Models\Attachment;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\DocumentVerificationService;
use App\Services\QrCodeService;
use App\Services\VehicleLoanLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Tests\TestCase;

class DocumentVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_payload_reuses_version_and_changed_payload_appends_new_version(): void
    {
        $actor = User::factory()->create();
        $vehicle = $this->vehicle();

        $service = app(
            DocumentVerificationService::class,
        );

        $first = $service->issue(
            verifiable: $vehicle,
            documentType: 'vehicle_control_card',
            documentLabel: 'Kartu Kendali Kendaraan',
            documentReference: $vehicle->vehicle_code,
            payload: [
                'vehicle_code' => $vehicle->vehicle_code,
                'rows' => [
                    [
                        'date' => '08/08/2026',
                        'maintenance_type' => 'Servis berkala',
                    ],
                ],
            ],
            actor: $actor,
        );

        $same = $service->issue(
            verifiable: $vehicle,
            documentType: 'vehicle_control_card',
            documentLabel: 'Kartu Kendali Kendaraan',
            documentReference: $vehicle->vehicle_code,
            payload: [
                'rows' => [
                    [
                        'maintenance_type' => 'Servis berkala',
                        'date' => '08/08/2026',
                    ],
                ],
                'vehicle_code' => $vehicle->vehicle_code,
            ],
            actor: $actor,
        );

        $this->assertSame(
            $first->id,
            $same->id,
        );

        $this->assertSame(
            $first->public_token,
            $same->public_token,
        );

        $this->assertSame(1, $same->version);

        $second = $service->issue(
            verifiable: $vehicle,
            documentType: 'vehicle_control_card',
            documentLabel: 'Kartu Kendali Kendaraan',
            documentReference: $vehicle->vehicle_code,
            payload: [
                'vehicle_code' => $vehicle->vehicle_code,
                'rows' => [
                    [
                        'date' => '08/08/2026',
                        'maintenance_type' => 'Servis berkala',
                    ],
                    [
                        'date' => '14/08/2026',
                        'maintenance_type' => 'Ganti oli',
                    ],
                ],
            ],
            actor: $actor,
        );

        $this->assertSame(2, $second->version);

        $this->assertNotSame(
            $first->public_token,
            $second->public_token,
        );

        $this->assertNotSame(
            $first->payload_hash,
            $second->payload_hash,
        );

        $this->assertDatabaseCount(
            'document_verifications',
            2,
        );

        $this->assertDatabaseCount(
            'audit_logs',
            2,
        );

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'document_verification_issued',
            'module' => 'document_verification',
            'auditable_type' => 'vehicle',
            'auditable_id' => $vehicle->id,
            'actor_id' => $actor->id,
        ]);
    }

    public function test_public_verification_is_privacy_safe_and_marks_old_version_as_superseded(): void
    {
        $actor = User::factory()->create();

        $vehicle = $this->vehicle([
            'notes' => 'RAHASIA-INTERNAL-STEP25B5',
        ]);

        $service = app(
            DocumentVerificationService::class,
        );

        $first = $service->issue(
            verifiable: $vehicle,
            documentType: 'vehicle_control_card',
            documentLabel: 'Kartu Kendali Kendaraan',
            documentReference: $vehicle->vehicle_code,
            payload: [
                'state' => 'awal',
            ],
            actor: $actor,
        );

        $second = $service->issue(
            verifiable: $vehicle,
            documentType: 'vehicle_control_card',
            documentLabel: 'Kartu Kendali Kendaraan',
            documentReference: $vehicle->vehicle_code,
            payload: [
                'state' => 'baru',
            ],
            actor: $actor,
        );

        $this->get(
            route(
                'document-verifications.show',
                ['token' => $first->public_token],
            ),
        )
            ->assertOk()
            ->assertSee('Versi Lama')
            ->assertSee('Kartu Kendali Kendaraan')
            ->assertSee($vehicle->vehicle_code)
            ->assertSee($first->payload_hash)
            ->assertSee(
                'QR ini bukan tanda tangan digital.',
            )
            ->assertDontSee(
                'RAHASIA-INTERNAL-STEP25B5',
            )
            ->assertHeader(
                'X-Robots-Tag',
                'noindex, nofollow',
            );

        $this->get(
            route(
                'document-verifications.show',
                ['token' => $second->public_token],
            ),
        )
            ->assertOk()
            ->assertSee('Valid')
            ->assertSee($second->payload_hash);

        $this->get(
            route(
                'document-verifications.show',
                [
                    'token' => str_repeat('f', 64),
                ],
            ),
        )->assertNotFound();
    }

    public function test_verification_records_are_append_only(): void
    {
        $actor = User::factory()->create();
        $vehicle = $this->vehicle();

        $verification = app(
            DocumentVerificationService::class,
        )->issue(
            verifiable: $vehicle,
            documentType: 'vehicle_control_card',
            documentLabel: 'Kartu Kendali Kendaraan',
            documentReference: $vehicle->vehicle_code,
            payload: [
                'state' => 'immutable',
            ],
            actor: $actor,
        );

        try {
            $verification->forceFill([
                'document_reference' => 'DIUBAH',
            ])->save();

            $this->fail(
                'Document verification seharusnya append-only.',
            );
        } catch (LogicException $exception) {
            $this->assertSame(
                'Versi verifikasi dokumen tidak boleh diubah.',
                $exception->getMessage(),
            );
        }

        $verification->refresh();

        try {
            $verification->delete();

            $this->fail(
                'Document verification tidak boleh dihapus.',
            );
        } catch (LogicException $exception) {
            $this->assertSame(
                'Versi verifikasi dokumen tidak boleh dihapus.',
                $exception->getMessage(),
            );
        }

        $this->assertDatabaseHas(
            'document_verifications',
            [
                'id' => $verification->id,
                'document_reference' => $vehicle->vehicle_code,
            ],
        );
    }

    public function test_lifecycle_attachment_data_uri_fails_closed_when_evidence_checksum_changes(): void
    {
        Storage::fake('local');

        $original = 'SIMANTAP-EVIDENCE-ORIGINAL';
        $path = 'vehicle-loans/evidence/probe.png';

        Storage::disk('local')->put(
            $path,
            $original,
        );

        $attachment = new Attachment([
            'disk' => 'local',
            'file_path' => $path,
            'mime_type' => 'image/png',
            'checksum' => hash(
                'sha256',
                $original,
            ),
        ]);

        $service = app(
            VehicleLoanLifecycleService::class,
        );

        $this->assertNotNull(
            $service->attachmentDataUri(
                $attachment,
            ),
        );

        Storage::disk('local')->put(
            $path,
            'SIMANTAP-EVIDENCE-TAMPERED',
        );

        $this->assertNull(
            $service->attachmentDataUri(
                $attachment,
            ),
        );
    }

    public function test_control_card_payload_and_verification_qr_use_semantic_state_without_private_vehicle_fields(): void
    {
        $actor = User::factory()->create();

        $vehicle = $this->vehicle([
            'notes' => 'RAHASIA-KENDARAAN-STEP25B5B',
            'chassis_number' => 'RAHASIA-CHASSIS-STEP25B5B',
            'engine_number' => 'RAHASIA-ENGINE-STEP25B5B',
        ]);

        $data = [
            'pages' => [
                [
                    [
                        'date' => '14/08/2026',
                        'maintenance_type' => 'Ganti oli',
                        'service_provider' => 'Bengkel Uji',
                    ],
                    null,
                ],
            ],
            'recordCount' => 1,
            'rowsPerCard' => 20,
        ];

        $service = app(
            DocumentVerificationService::class,
        );

        $payload = $service->vehicleControlCardPayload(
            $vehicle,
            $data,
        );

        $encoded = json_encode(
            $payload,
            JSON_THROW_ON_ERROR,
        );

        $this->assertStringContainsString(
            $vehicle->vehicle_code,
            $encoded,
        );

        $this->assertStringContainsString(
            'Ganti oli',
            $encoded,
        );

        $this->assertStringNotContainsString(
            'RAHASIA-KENDARAAN-STEP25B5B',
            $encoded,
        );

        $this->assertStringNotContainsString(
            'RAHASIA-CHASSIS-STEP25B5B',
            $encoded,
        );

        $this->assertStringNotContainsString(
            'RAHASIA-ENGINE-STEP25B5B',
            $encoded,
        );

        $verification = $service->issue(
            verifiable: $vehicle,
            documentType: 'vehicle_control_card',
            documentLabel: 'Kartu Kendali Kendaraan',
            documentReference: $vehicle->vehicle_code,
            payload: $payload,
            actor: $actor,
        );

        $dataUri = $service->qrDataUri(
            $verification,
            app(QrCodeService::class),
            72,
        );

        $this->assertStringStartsWith(
            'data:image/svg+xml;base64,',
            $dataUri,
        );

        $encodedSvg = substr(
            $dataUri,
            strlen('data:image/svg+xml;base64,'),
        );

        $svg = base64_decode(
            $encodedSvg,
            true,
        );

        $this->assertNotFalse($svg);

        $this->assertStringContainsString(
            '<svg',
            $svg,
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function vehicle(
        array $attributes = [],
    ): Vehicle {
        return Vehicle::query()->create([
            'vehicle_code' => fake()
                ->unique()
                ->bothify('KND-VER-###??'),
            'license_plate' => fake()
                ->unique()
                ->bothify('S #### VR'),
            'brand' => 'Toyota',
            'model' => 'Avanza',
            'year' => 2025,
            'color' => 'Hitam',
            'chassis_number' => fake()
                ->unique()
                ->bothify('MH1###############'),
            'engine_number' => fake()
                ->unique()
                ->bothify('ENG############'),
            'current_odometer' => 1000,
            'status' => VehicleStatus::Available,
            'registration_expiry_date' => '2027-08-14',
            'storage_location' => 'Garasi Kantor',
            'responsible_person' => 'Pengelola Barang',
            'image_path' => null,
            'notes' => null,
            'is_active' => true,
            ...$attributes,
        ]);
    }
}
