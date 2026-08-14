<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Support\AttachmentIntegrity;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttachmentIntegrityTest extends TestCase
{
    public function test_private_local_disk_is_not_served_by_framework_routes(): void
    {
        $this->assertFalse(
            (bool) config('filesystems.disks.local.serve'),
        );

        $this->assertFalse(
            app('router')->has('storage.local'),
        );

        $this->assertFalse(
            app('router')->has('storage.local.upload'),
        );
    }

    public function test_attachment_checksum_fails_closed_after_file_tampering(): void
    {
        Storage::fake('local');

        $path = 'security/evidence-probe.txt';
        $original = 'SIMANTAP-EVIDENCE-ORIGINAL';

        Storage::disk('local')->put(
            $path,
            $original,
        );

        $attachment = new Attachment([
            'disk' => 'local',
            'file_path' => $path,
            'checksum' => hash(
                'sha256',
                $original,
            ),
        ]);

        $this->assertTrue(
            AttachmentIntegrity::checksumMatches(
                $attachment,
            ),
        );

        Storage::disk('local')->put(
            $path,
            'SIMANTAP-EVIDENCE-TAMPERED',
        );

        $this->assertFalse(
            AttachmentIntegrity::checksumMatches(
                $attachment,
            ),
        );
    }

    public function test_attachment_checksum_rejects_missing_or_malformed_checksum(): void
    {
        Storage::fake('local');

        $path = 'security/checksum-probe.txt';

        Storage::disk('local')->put(
            $path,
            'SIMANTAP',
        );

        $attachment = new Attachment([
            'disk' => 'local',
            'file_path' => $path,
            'checksum' => '',
        ]);

        $this->assertFalse(
            AttachmentIntegrity::checksumMatches(
                $attachment,
            ),
        );

        $attachment->checksum = 'not-a-sha256';

        $this->assertFalse(
            AttachmentIntegrity::checksumMatches(
                $attachment,
            ),
        );
    }
}
