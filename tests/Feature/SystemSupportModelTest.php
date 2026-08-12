<?php

namespace Tests\Feature;

use App\Enums\AttachmentCategory;
use App\Enums\DigitalSignaturePurpose;
use App\Enums\DocumentType;
use App\Enums\InventoryRequestStatus;
use App\Models\Attachment;
use App\Models\DigitalSignature;
use App\Models\DocumentSequence;
use App\Models\InventoryRequest;
use App\Models\Setting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class SystemSupportModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_attachment_uses_expected_casts_and_relationships(): void
    {
        $requester = User::factory()->create([
            'employee_number' => 'PGW-ATT-001',
            'work_unit' => 'Bagian Umum',
        ]);

        $uploader = User::factory()->create([
            'employee_number' => 'ADM-ATT-001',
        ]);

        $inventoryRequest = $this->createInventoryRequest($requester);

        $attachment = Attachment::query()->create([
            'attachable_type' => $inventoryRequest->getMorphClass(),
            'attachable_id' => $inventoryRequest->id,
            'file_category' => AttachmentCategory::from('document'),
            'disk' => 'local',
            'original_name' => 'surat-permintaan.pdf',
            'stored_name' => 'surat-permintaan-001.pdf',
            'file_path' => 'attachments/inventory-requests/'
                .'surat-permintaan-001.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 2048,
            'checksum' => hash(
                'sha256',
                'attachment-test-content',
            ),
            'metadata' => [
                'page_count' => 2,
                'source' => 'automated-test',
            ],
            'uploaded_by' => $uploader->id,
        ]);

        $attachment->load([
            'attachable',
            'uploader',
        ]);

        $inventoryRequest->load('attachments');

        $this->assertSame(
            AttachmentCategory::from('document'),
            $attachment->file_category,
        );
        $this->assertSame(2048, $attachment->file_size);
        $this->assertSame([
            'page_count' => 2,
            'source' => 'automated-test',
        ], $attachment->metadata);
        $this->assertFalse($attachment->isImage());
        $this->assertTrue(
            $attachment->attachable->is($inventoryRequest),
        );
        $this->assertTrue($attachment->uploader->is($uploader));
        $this->assertCount(1, $inventoryRequest->attachments);
        $this->assertTrue(
            $inventoryRequest->attachments->first()->is($attachment),
        );

        $attachment->delete();

        $this->assertSoftDeleted($attachment);
    }

    public function test_digital_signature_uses_snapshots_and_is_immutable(): void
    {
        $requester = User::factory()->create([
            'employee_number' => 'PGW-SIGN-001',
            'work_unit' => 'Bagian Umum',
        ]);

        $inventoryRequest = $this->createInventoryRequest($requester);

        $signature = DigitalSignature::query()->create([
            'signable_type' => $inventoryRequest->getMorphClass(),
            'signable_id' => $inventoryRequest->id,
            'signer_id' => $requester->id,
            'signer_name_snapshot' => $requester->name,
            'employee_number_snapshot' => $requester->employee_number,
            'purpose' => DigitalSignaturePurpose::InventoryRequestSubmission,
            'version' => 1,
            'image_path' => 'signatures/inventory-request-001.png',
            'transaction_hash' => hash(
                'sha256',
                $inventoryRequest->request_number.'|submission',
            ),
            'image_checksum' => hash(
                'sha256',
                'signature-image-test',
            ),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'SIMANTAP Automated Test',
            'signed_at' => now(),
        ]);

        $signature->refresh()->load([
            'signable',
            'signer',
        ]);

        $inventoryRequest->load('digitalSignatures');

        $this->assertSame(
            DigitalSignaturePurpose::InventoryRequestSubmission,
            $signature->purpose,
        );
        $this->assertSame(1, $signature->version);
        $this->assertInstanceOf(
            CarbonImmutable::class,
            $signature->signed_at,
        );
        $this->assertSame(
            $requester->name,
            $signature->signer_name_snapshot,
        );
        $this->assertSame(
            $requester->employee_number,
            $signature->employee_number_snapshot,
        );
        $this->assertTrue(
            $signature->signable->is($inventoryRequest),
        );
        $this->assertTrue($signature->signer->is($requester));
        $this->assertCount(
            1,
            $inventoryRequest->digitalSignatures,
        );
        $this->assertTrue(
            $inventoryRequest->digitalSignatures
                ->first()
                ->is($signature),
        );

        $this->assertModelCannotBeChanged(
            $signature,
            [
                'signer_name_snapshot' => 'Nama penanda tangan tidak boleh berubah',
            ],
        );
    }

    public function test_legacy_digital_signature_purposes_remain_cast_compatible(): void
    {
        $requester = User::factory()->create([
            'employee_number' => 'PGW-SIGN-LEGACY-001',
        ]);
        $inventoryRequest = $this->createInventoryRequest($requester);

        foreach ([
            DigitalSignaturePurpose::VehicleCheckoutConfirmation,
            DigitalSignaturePurpose::VehicleReturnConfirmation,
        ] as $purpose) {
            $signature = DigitalSignature::query()->create([
                'signable_type' => $inventoryRequest->getMorphClass(),
                'signable_id' => $inventoryRequest->id,
                'signer_id' => $requester->id,
                'signer_name_snapshot' => $requester->name,
                'employee_number_snapshot' => $requester->employee_number,
                'purpose' => $purpose->value,
                'version' => 1,
                'image_path' => 'signatures/legacy-'.$purpose->value.'.png',
                'transaction_hash' => hash(
                    'sha256',
                    'legacy-'.$purpose->value,
                ),
                'image_checksum' => hash(
                    'sha256',
                    'legacy-image-'.$purpose->value,
                ),
                'signed_at' => now(),
            ]);

            $this->assertSame(
                $purpose,
                $signature->refresh()->purpose,
            );
        }
    }

    public function test_digital_signature_versions_are_append_only_and_unique_per_purpose_version(): void
    {
        $requester = User::factory()->create([
            'employee_number' => 'PGW-SIGN-VER-001',
        ]);
        $otherSigner = User::factory()->create([
            'employee_number' => 'PGW-SIGN-VER-002',
        ]);
        $inventoryRequest = $this->createInventoryRequest($requester);

        foreach ([1, 2] as $version) {
            DigitalSignature::query()->create([
                'signable_type' => $inventoryRequest->getMorphClass(),
                'signable_id' => $inventoryRequest->id,
                'signer_id' => $requester->id,
                'signer_name_snapshot' => $requester->name,
                'employee_number_snapshot' => $requester->employee_number,
                'purpose' => DigitalSignaturePurpose::InventoryRequestSubmission,
                'version' => $version,
                'image_path' => "signatures/version-{$version}.png",
                'transaction_hash' => hash(
                    'sha256',
                    "signature-version-{$version}",
                ),
                'image_checksum' => hash(
                    'sha256',
                    "image-version-{$version}",
                ),
                'signed_at' => now()->addSeconds($version),
            ]);
        }

        $this->assertSame(
            [1, 2],
            DigitalSignature::query()
                ->where('signable_type', $inventoryRequest->getMorphClass())
                ->where('signable_id', $inventoryRequest->id)
                ->where(
                    'purpose',
                    DigitalSignaturePurpose::InventoryRequestSubmission->value,
                )
                ->orderBy('version')
                ->pluck('version')
                ->all(),
        );

        $this->expectException(QueryException::class);

        DigitalSignature::query()->create([
            'signable_type' => $inventoryRequest->getMorphClass(),
            'signable_id' => $inventoryRequest->id,
            'signer_id' => $otherSigner->id,
            'signer_name_snapshot' => $otherSigner->name,
            'employee_number_snapshot' => $otherSigner->employee_number,
            'purpose' => DigitalSignaturePurpose::InventoryRequestSubmission,
            'version' => 2,
            'image_path' => 'signatures/duplicate-version.png',
            'transaction_hash' => hash(
                'sha256',
                'duplicate-signature-version',
            ),
            'image_checksum' => hash(
                'sha256',
                'duplicate-image-version',
            ),
            'signed_at' => now()->addMinute(),
        ]);
    }

    public function test_document_sequence_and_setting_use_expected_casts(): void
    {
        $sequence = DocumentSequence::query()->create([
            'document_type' => DocumentType::from('MOV'),
            'year' => 2026,
            'month' => 7,
            'last_number' => 12,
        ]);

        $publicSetting = Setting::query()->create([
            'key' => 'organization.name',
            'value' => [
                'text' => 'BPS Kabupaten Malang',
            ],
            'group' => 'organization',
            'is_public' => true,
        ]);

        $privateSetting = Setting::query()->create([
            'key' => 'vehicle.max_loan_days',
            'value' => [
                'number' => 3,
            ],
            'group' => 'vehicle',
            'is_public' => false,
        ]);

        $this->assertSame(
            DocumentType::from('MOV'),
            $sequence->document_type,
        );
        $this->assertSame(2026, $sequence->year);
        $this->assertSame(7, $sequence->month);
        $this->assertSame(12, $sequence->last_number);

        $this->assertSame([
            'text' => 'BPS Kabupaten Malang',
        ], $publicSetting->value);
        $this->assertTrue($publicSetting->is_public);
        $this->assertTrue($publicSetting->isPublic());
        $this->assertFalse($privateSetting->isPublic());

        $this->assertSame(
            [$publicSetting->id],
            Setting::query()
                ->publiclyVisible()
                ->pluck('id')
                ->all(),
        );

        $this->assertSame(
            1,
            Setting::query()
                ->inGroup('vehicle')
                ->count(),
        );
    }

    private function createInventoryRequest(
        User $requester,
    ): InventoryRequest {
        return InventoryRequest::query()->create([
            'request_number' => 'REQ/2026/07/0001',
            'requested_by' => $requester->id,
            'employee_number_snapshot' => $requester->employee_number,
            'requester_name_snapshot' => $requester->name,
            'work_unit_snapshot' => $requester->work_unit,
            'request_date' => now(),
            'purpose' => 'Kebutuhan pengujian model pendukung.',
            'notes' => null,
            'status' => InventoryRequestStatus::Draft,
            'submitted_at' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'approved_at' => null,
            'rejected_at' => null,
            'rejection_reason' => null,
            'revision_note' => null,
            'delivered_by' => null,
            'delivered_at' => null,
            'received_at' => null,
            'completed_at' => null,
            'cancelled_at' => null,
            'expired_at' => null,
            'admin_notes' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function assertModelCannotBeChanged(
        Model $model,
        array $changes,
    ): void {
        try {
            $model->update($changes);

            $this->fail('Model immutable masih dapat diubah.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString(
                'tidak boleh diubah',
                $exception->getMessage(),
            );
        }

        $model->refresh();

        foreach ($changes as $attribute => $value) {
            $this->assertNotEquals(
                $value,
                $model->getAttribute($attribute),
            );
        }

        try {
            $model->delete();

            $this->fail('Model immutable masih dapat dihapus.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString(
                'tidak boleh dihapus',
                $exception->getMessage(),
            );
        }

        $this->assertDatabaseHas($model->getTable(), [
            $model->getKeyName() => $model->getKey(),
        ]);
    }
}
