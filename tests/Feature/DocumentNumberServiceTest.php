<?php

namespace Tests\Feature;

use App\Enums\DocumentType;
use App\Models\DocumentSequence;
use App\Services\DocumentNumberService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentNumberServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_numbers_are_sequential_per_type_and_month(): void
    {
        $service = app(DocumentNumberService::class);

        $july = CarbonImmutable::create(
            2026,
            7,
            29,
            10,
            0,
            0,
            'Asia/Jakarta',
        );

        $august = CarbonImmutable::create(
            2026,
            8,
            1,
            10,
            0,
            0,
            'Asia/Jakarta',
        );

        $this->assertSame(
            'REQ/2026/07/0001',
            $service->next(
                DocumentType::from('REQ'),
                $july,
            ),
        );

        $this->assertSame(
            'REQ/2026/07/0002',
            $service->next(
                DocumentType::from('REQ'),
                $july,
            ),
        );

        $this->assertSame(
            'LOAN/2026/07/0001',
            $service->next(
                DocumentType::from('LOAN'),
                $july,
            ),
        );

        $this->assertSame(
            'REQ/2026/08/0001',
            $service->next(
                DocumentType::from('REQ'),
                $august,
            ),
        );

        $julyRequestSequence = DocumentSequence::query()
            ->where('document_type', 'REQ')
            ->where('year', 2026)
            ->where('month', 7)
            ->firstOrFail();

        $this->assertSame(
            DocumentType::from('REQ'),
            $julyRequestSequence->document_type,
        );
        $this->assertSame(
            2,
            $julyRequestSequence->last_number,
        );
        $this->assertDatabaseCount('document_sequences', 3);
    }

    public function test_all_document_types_use_their_enum_value_as_prefix(): void
    {
        $service = app(DocumentNumberService::class);

        $date = CarbonImmutable::create(
            2026,
            7,
            29,
            10,
            0,
            0,
            'Asia/Jakarta',
        );

        foreach (DocumentType::cases() as $type) {
            $this->assertSame(
                sprintf(
                    '%s/2026/07/0001',
                    $type->value,
                ),
                $service->next($type, $date),
            );
        }

        $this->assertDatabaseCount(
            'document_sequences',
            count(DocumentType::cases()),
        );
    }

    public function test_document_month_uses_simantap_display_timezone(): void
    {
        config()->set(
            'simantap.display_timezone',
            'Asia/Jakarta',
        );

        $service = app(DocumentNumberService::class);

        $utcBoundary = CarbonImmutable::create(
            2026,
            7,
            31,
            17,
            30,
            0,
            'UTC',
        );

        $this->assertSame(
            'MTC/2026/08/0001',
            $service->next(
                DocumentType::from('MTC'),
                $utcBoundary,
            ),
        );
    }
}
