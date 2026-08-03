<?php

namespace App\Services;

use App\Enums\DocumentType;
use App\Models\DocumentSequence;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DocumentNumberService
{
    /**
     * @var array<string, string>
     */
    private const LEGACY_TYPES = [
        'inventory_request' => 'REQ',
        'vehicle_loan' => 'LOAN',
        'maintenance' => 'MTC',
        'initial_stock' => 'STK-INIT',
        'stock_in' => 'STK-IN',
        'stock_adjustment' => 'STK-ADJ',
        'stock_movement' => 'MOV',
    ];

    /**
     * @var list<string>
     */
    private const SUPPORTED_PREFIXES = [
        'REQ',
        'LOAN',
        'MTC',
        'STK-INIT',
        'STK-IN',
        'STK-ADJ',
        'MOV',
    ];

    public function next(
        string|DocumentType $type,
        ?CarbonInterface $date = null,
    ): string {
        $requestedType = $type instanceof DocumentType
            ? $type->value
            : $type;
        $prefix = self::LEGACY_TYPES[$requestedType]
            ?? $requestedType;

        if (! in_array($prefix, self::SUPPORTED_PREFIXES, true)) {
            throw new InvalidArgumentException(
                "Jenis dokumen [{$requestedType}] tidak didukung.",
            );
        }

        $displayDate = CarbonImmutable::instance($date ?? now())
            ->setTimezone(
                (string) config(
                    'simantap.display_timezone',
                    'Asia/Jakarta',
                ),
            );

        return DB::transaction(function () use (
            $prefix,
            $displayDate,
        ): string {
            DB::table('document_sequences')->insertOrIgnore([
                'document_type' => $prefix,
                'year' => $displayDate->year,
                'month' => $displayDate->month,
                'last_number' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sequence = DocumentSequence::query()
                ->where('document_type', $prefix)
                ->where('year', $displayDate->year)
                ->where('month', $displayDate->month)
                ->lockForUpdate()
                ->firstOrFail();

            $sequence->increment('last_number');
            $sequence->refresh();

            return sprintf(
                '%s/%d/%02d/%04d',
                $prefix,
                $displayDate->year,
                $displayDate->month,
                $sequence->last_number,
            );
        }, 3);
    }
}
