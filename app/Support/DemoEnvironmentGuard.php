<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class DemoEnvironmentGuard
{
    public static function assertSafe(): string
    {
        if (! app()->environment('demo')) {
            throw new RuntimeException(
                'Operasi demo ditolak: APP_ENV harus bernilai demo.',
            );
        }

        if (config('simantap.demo.enabled') !== true) {
            throw new RuntimeException(
                'Operasi demo ditolak: SIMANTAP_DEMO_MODE harus bernilai true.',
            );
        }

        $actualDatabase = trim(
            (string) DB::connection()->getDatabaseName(),
        );
        $expectedDemoDatabase = trim(
            (string) config('simantap.demo.database'),
        );
        $productionDatabase = trim(
            (string) config('simantap.production_database'),
        );

        if ($actualDatabase === '') {
            throw new RuntimeException(
                'Operasi demo ditolak: nama database aktif tidak dapat dibaca.',
            );
        }

        if ($expectedDemoDatabase === '') {
            throw new RuntimeException(
                'Operasi demo ditolak: SIMANTAP_DEMO_DATABASE wajib diisi.',
            );
        }

        if (
            self::normalize($actualDatabase) === 'simantap'
            || (
                $productionDatabase !== ''
                && self::normalize($actualDatabase)
                    === self::normalize($productionDatabase)
            )
        ) {
            throw new RuntimeException(
                "Operasi demo ditolak: database `{$actualDatabase}` adalah database resmi.",
            );
        }

        if (
            self::normalize($actualDatabase)
            !== self::normalize($expectedDemoDatabase)
        ) {
            throw new RuntimeException(
                "Operasi demo ditolak: database aktif `{$actualDatabase}` tidak sama dengan database demo `{$expectedDemoDatabase}`.",
            );
        }

        return $actualDatabase;
    }

    private static function normalize(string $database): string
    {
        return mb_strtolower(trim($database));
    }
}
