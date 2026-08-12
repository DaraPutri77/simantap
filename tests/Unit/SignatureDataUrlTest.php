<?php

namespace Tests\Unit;

use App\Rules\SignatureDataUrl;
use App\Support\SignaturePayload;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class SignatureDataUrlTest extends TestCase
{
    public function test_valid_png_data_url_is_accepted(): void
    {
        $validator = Validator::make(
            ['signature_data' => $this->validSignatureDataUrl()],
            ['signature_data' => ['required', new SignatureDataUrl]],
        );

        $this->assertTrue($validator->passes());
    }

    public function test_png_magic_header_without_valid_image_is_rejected(): void
    {
        $validator = Validator::make(
            [
                'signature_data' => 'data:image/png;base64,'.base64_encode(
                    "\x89PNG\r\n\x1a\nNOT-A-REAL-PNG",
                ),
            ],
            ['signature_data' => ['required', new SignatureDataUrl]],
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey(
            'signature_data',
            $validator->errors()->toArray(),
        );
    }

    public function test_signature_larger_than_configured_limit_is_rejected(): void
    {
        config([
            'simantap.uploads.signature_max_size_kb' => 1,
        ]);

        $validPng = base64_decode(
            substr(
                $this->validSignatureDataUrl(),
                strlen('data:image/png;base64,'),
            ),
            true,
        );

        $this->assertIsString($validPng);

        $validator = Validator::make(
            [
                'signature_data' => 'data:image/png;base64,'.base64_encode(
                    $validPng.str_repeat('A', 2048),
                ),
            ],
            ['signature_data' => ['required', new SignatureDataUrl]],
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey(
            'signature_data',
            $validator->errors()->toArray(),
        );
    }

    public function test_signature_dimensions_outside_safe_canvas_limits_are_rejected(): void
    {
        $tooWide = 'data:image/png;base64,'
            .'iVBORw0KGgoAAAANSUhEUgAAE4gAAAABCAYAAAAyEUSIAAAAKklEQVR42u3BMQEAAADCoPVPbQ0PoAAAAAAAAAAAAAAAAAAAAAAAAAC4ME4hAAGT4lqRAAAAAElFTkSuQmCC';

        $tooTall = 'data:image/png;base64,'
            .'iVBORw0KGgoAAAANSUhEUgAAAAEAAAQBCAYAAADrWuCaAAAAG0lEQVR42u3BAQ0AAADCoPdPbQ8HFAAAAABwbRQFAAHtbsmDAAAAAElFTkSuQmCC';

        foreach ([$tooWide, $tooTall] as $payload) {
            $validator = Validator::make(
                ['signature_data' => $payload],
                ['signature_data' => ['required', new SignatureDataUrl]],
            );

            $this->assertTrue($validator->fails());
            $this->assertArrayHasKey(
                'signature_data',
                $validator->errors()->toArray(),
            );
        }
    }

    public function test_canonical_payload_checksum_verification_fails_closed(): void
    {
        $canonical = SignaturePayload::decode(
            $this->validSignatureDataUrl(),
        );

        $algorithm = (string) config(
            'simantap.signature.hash_algorithm',
            'sha256',
        );
        $checksum = hash($algorithm, $canonical);

        $this->assertTrue(
            SignaturePayload::checksumMatches($canonical, $checksum),
        );
        $this->assertFalse(
            SignaturePayload::checksumMatches(
                $canonical.'tampered',
                $checksum,
            ),
        );
    }

    private function validSignatureDataUrl(): string
    {
        return 'data:image/png;base64,'
            .'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwC'
            .'AAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';
    }
}
