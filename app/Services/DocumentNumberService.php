<?php

namespace App\Services;

use App\Enums\DocumentType;
use App\Models\DocumentSequence;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class DocumentNumberService
{
    public function next(
        DocumentType $type,
        ?CarbonInterface $date = null,
    ): string {
        $businessDate = CarbonImmutable::instance(
            $date ?? now(),
        )->setTimezone(
            (string) config(
                'simantap.display_timezone',
                'Asia/Jakarta',
            ),
        );

        return DB::transaction(
            function () use ($type, $businessDate): string {
                $timestamp = now();

                DB::table('document_sequences')->insertOrIgnore([
                    'document_type' => $type->value,
                    'year' => $businessDate->year,
                    'month' => $businessDate->month,
                    'last_number' => 0,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);

                $sequence = DocumentSequence::query()
                    ->where('document_type', $type->value)
                    ->where('year', $businessDate->year)
                    ->where('month', $businessDate->month)
                    ->lockForUpdate()
                    ->firstOrFail();

                $nextNumber = $sequence->last_number + 1;

                $sequence->forceFill([
                    'last_number' => $nextNumber,
                ])->save();

                return sprintf(
                    '%s/%d/%02d/%04d',
                    $type->value,
                    $businessDate->year,
                    $businessDate->month,
                    $nextNumber,
                );
            },
            3,
        );
    }
}
