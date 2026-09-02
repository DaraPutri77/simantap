<?php

namespace App\Console\Commands;

use App\Support\DemoEnvironmentGuard;
use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;

class ResetDemoEnvironment extends Command
{
    private const CONFIRMATION = 'RESET-SIMANTAPDEMO';

    protected $signature = 'simantap:reset-demo
        {--execute : Hapus seluruh isi database demo, migrasi ulang, lalu buat akun demo}
        {--confirmation= : Frasa konfirmasi reset permanen}';

    protected $description = 'Simulasi atau reset aman database khusus seminar SIMANTAP.';

    public function handle(): int
    {
        try {
            $database = DemoEnvironmentGuard::assertSafe();
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->twoColumnDetail('APP_ENV', app()->environment());
        $this->components->twoColumnDetail('Database aktif', $database);
        $this->components->twoColumnDetail(
            'Database resmi',
            (string) config('simantap.production_database'),
        );

        if (! $this->option('execute')) {
            $this->newLine();
            $this->warn('SIMULASI SAJA: tidak ada data yang diubah.');
            $this->line(
                'Reset hanya akan dijalankan pada database demo yang lolos seluruh pengaman.',
            );

            return self::SUCCESS;
        }

        if ((string) $this->option('confirmation') !== self::CONFIRMATION) {
            $this->error(
                'Eksekusi ditolak: gunakan --confirmation='.self::CONFIRMATION,
            );

            return self::FAILURE;
        }

        if (
            ! app()->runningUnitTests()
            && ! app()->isDownForMaintenance()
        ) {
            $this->error(
                'Eksekusi ditolak: jalankan php artisan down terlebih dahulu agar tidak ada transaksi demo saat reset.',
            );

            return self::FAILURE;
        }

        $migrationExitCode = Artisan::call('migrate:fresh', [
            '--force' => true,
        ]);

        if ($migrationExitCode !== self::SUCCESS) {
            $this->error('Reset ditolak: migrasi database demo gagal.');

            return self::FAILURE;
        }

        $seederExitCode = Artisan::call('db:seed', [
            '--class' => DemoSeeder::class,
            '--force' => true,
        ]);

        if ($seederExitCode !== self::SUCCESS) {
            $this->error(
                'Database berhasil dimigrasi ulang, tetapi pembuatan data awal demo gagal.',
            );

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Database demo berhasil dikembalikan ke kondisi awal seminar.');
        $this->line(
            'Akun tersedia: '.
            config('simantap.demo.accounts.administrator.email').
            ' dan '.
            config('simantap.demo.accounts.employee.email'),
        );

        return self::SUCCESS;
    }
}
