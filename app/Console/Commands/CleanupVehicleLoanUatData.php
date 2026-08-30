<?php

namespace App\Console\Commands;

use App\Services\VehicleLoanUatCleanupService;
use Illuminate\Console\Command;

class CleanupVehicleLoanUatData extends Command
{
    private const CONFIRMATION = 'HAPUS-SEMUA-PEMINJAMAN-UAT';

    protected $signature = 'simantap:cleanup-vehicle-loans
        {--execute : Menjalankan penghapusan setelah simulasi diperiksa}
        {--database-backup= : Path file dump MySQL yang sudah berhasil dibuat}
        {--private-files-backup= : Path arsip storage/app/private sebelum penghapusan}
        {--confirmation= : Frasa konfirmasi penghapusan permanen}
        {--keep-odometers : Mempertahankan odometer master saat ini}
        {--include-all-maintenance : Ikut membersihkan seluruh pemeliharaan UAT, termasuk tiket manual}
        {--allow-production : Konfirmasi tambahan bila APP_ENV=production}';

    protected $description = 'Simulasi atau hapus permanen data UAT peminjaman kendaraan serta pemeliharaan yang dipilih.';

    public function handle(VehicleLoanUatCleanupService $cleanup): int
    {
        $keepOdometers = (bool) $this->option('keep-odometers');
        $includeAllMaintenance = (bool) $this->option(
            'include-all-maintenance',
        );
        $plan = $cleanup->inspect(
            $keepOdometers,
            $includeAllMaintenance,
        );

        $this->displayPlan($plan);

        if (! $this->option('execute')) {
            $this->newLine();
            $this->warn('SIMULASI SAJA: tidak ada data atau file yang diubah.');
            $this->line(
                'Periksa angka di atas, buat backup MySQL, turunkan aplikasi, lalu jalankan kembali dengan --execute.',
            );

            return self::SUCCESS;
        }

        if (
            $plan['loans']['count'] === 0
            && $plan['maintenance_records']['count'] === 0
        ) {
            $this->info('Database sudah bersih: tidak ada transaksi UAT yang dipilih.');

            return self::SUCCESS;
        }

        if ($plan['cleanup_already_executed']) {
            $this->error(
                'Eksekusi ditolak: pembersihan UAT pernah diselesaikan. Command ini tidak boleh dipakai untuk menghapus transaksi operasional.',
            );

            return self::FAILURE;
        }

        $databaseBackup = $this->validatedBackup(
            'database-backup',
            'dump MySQL',
        );
        $privateFilesBackup = $this->validatedBackup(
            'private-files-backup',
            'arsip storage/app/private',
        );

        if ($databaseBackup === null || $privateFilesBackup === null) {
            return self::FAILURE;
        }

        if ((string) $this->option('confirmation') !== self::CONFIRMATION) {
            $this->error(
                'Eksekusi ditolak: gunakan --confirmation='.self::CONFIRMATION,
            );

            return self::FAILURE;
        }

        if (app()->environment('production') && ! $this->option('allow-production')) {
            $this->error(
                'Eksekusi pada APP_ENV=production memerlukan --allow-production.',
            );

            return self::FAILURE;
        }

        if (
            ! app()->environment('testing')
            && ! app()->isDownForMaintenance()
        ) {
            $this->error(
                'Eksekusi ditolak: jalankan php artisan down terlebih dahulu agar tidak ada transaksi baru saat pembersihan.',
            );

            return self::FAILURE;
        }

        $result = $cleanup->cleanup(
            [
                'database' => $databaseBackup,
                'private_files' => $privateFilesBackup,
            ],
            $keepOdometers,
            $includeAllMaintenance,
        );
        $execution = $result['execution'];

        $this->newLine();
        $this->info('Pembersihan database selesai.');
        $this->line(
            'Manifest private: storage/app/private/'.$execution['manifest_path'],
        );
        $this->line(
            'File bukti yang dihapus: '.$execution['deleted_files'],
        );

        if ($execution['failed_files'] !== []) {
            $this->error(
                'Database sudah bersih, tetapi ada file yang gagal dihapus. Periksa manifest sebelum aplikasi dinaikkan kembali.',
            );

            return self::FAILURE;
        }

        $this->info('Seluruh jejak transaksi UAT yang dipilih berhasil dibersihkan.');

        return self::SUCCESS;
    }

    /**
     * @return array{path: string, size: int, sha256: string}|null
     */
    private function validatedBackup(
        string $option,
        string $label,
    ): ?array {
        $path = trim((string) $this->option($option));

        if ($path === '') {
            $this->error(
                "Eksekusi ditolak: isi --{$option} dengan path {$label}.",
            );

            return null;
        }

        $realPath = realpath($path);
        if ($realPath === false || ! is_file($realPath)) {
            $this->error(
                "Eksekusi ditolak: file {$label} tidak ditemukan: {$path}",
            );

            return null;
        }

        $size = filesize($realPath);
        if ($size === false || $size < 1) {
            $this->error(
                "Eksekusi ditolak: file {$label} kosong atau tidak dapat dibaca: {$realPath}",
            );

            return null;
        }

        $checksum = hash_file('sha256', $realPath);
        if ($checksum === false) {
            $this->error(
                "Eksekusi ditolak: checksum {$label} tidak dapat dihitung: {$realPath}",
            );

            return null;
        }

        return [
            'path' => $realPath,
            'size' => $size,
            'sha256' => $checksum,
        ];
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    private function displayPlan(array $plan): void
    {
        $this->components->twoColumnDetail(
            'Peminjaman kendaraan',
            (string) $plan['loans']['count'],
        );
        $this->components->twoColumnDetail(
            'Riwayat status peminjaman',
            (string) $plan['vehicle_loan_status_histories']['count'],
        );
        $this->components->twoColumnDetail(
            'Pemeriksaan kondisi',
            (string) $plan['condition_checks']['count'],
        );
        $this->components->twoColumnDetail(
            'Pemeliharaan turunan',
            (string) $plan['linked_maintenance']['count'],
        );
        $this->components->twoColumnDetail(
            'Pemeliharaan UAT tambahan',
            (string) $plan['standalone_maintenance']['count'],
        );
        $this->components->twoColumnDetail(
            'Total pemeliharaan yang dihapus',
            (string) $plan['maintenance_records']['count'],
        );
        $this->components->twoColumnDetail(
            'Riwayat status pemeliharaan',
            (string) $plan['maintenance_status_histories']['count'],
        );
        $this->components->twoColumnDetail(
            'Lampiran',
            (string) $plan['attachments']['count'],
        );
        $this->components->twoColumnDetail(
            'Tanda tangan digital',
            (string) $plan['digital_signatures']['count'],
        );
        $this->components->twoColumnDetail(
            'Versi verifikasi dokumen',
            (string) $plan['document_verifications']['count'],
        );
        $this->components->twoColumnDetail(
            'Audit UAT terkait',
            (string) $plan['audit_logs']['count'],
        );
        $this->components->twoColumnDetail(
            'Notifikasi UAT terkait',
            (string) $plan['notifications']['count'],
        );
        $this->components->twoColumnDetail(
            $plan['include_all_maintenance']
                ? 'Urutan nomor dokumen LOAN/MTC'
                : 'Urutan nomor dokumen LOAN',
            (string) $plan['document_sequences']['count'],
        );
        $this->components->twoColumnDetail(
            'File bukti/tanda tangan',
            (string) $plan['files']['count'],
        );
        $this->components->twoColumnDetail(
            'Pembersihan pernah dijalankan',
            $plan['cleanup_already_executed'] ? 'Ya' : 'Tidak',
        );

        if ($plan['loans']['items'] !== []) {
            $this->newLine();
            $this->table(
                ['ID', 'Nomor peminjaman', 'Status', 'ID kendaraan'],
                collect($plan['loans']['items'])->map(
                    static fn (array $loan): array => [
                        $loan['id'],
                        $loan['loan_number'],
                        $loan['status'],
                        $loan['vehicle_id'],
                    ],
                )->all(),
            );
        }

        if ($plan['maintenance_records']['items'] !== []) {
            $this->newLine();
            $this->table(
                [
                    'ID',
                    'Pemeliharaan UAT',
                    'Status',
                    'ID peminjaman',
                    'Subjek',
                ],
                collect($plan['maintenance_records']['items'])->map(
                    static fn (array $record): array => [
                        $record['id'],
                        $record['maintenance_number'],
                        $record['status'],
                        $record['source_vehicle_loan_id'] ?? '-',
                        $record['vehicle_id'] !== null
                            ? 'Kendaraan #'.$record['vehicle_id']
                            : 'Aset #'.$record['operational_asset_id'],
                    ],
                )->all(),
            );
        }

        if ($plan['vehicle_adjustments'] !== []) {
            $this->newLine();
            $this->table(
                [
                    'Kendaraan',
                    'Status sekarang → target',
                    'Odometer sekarang → target',
                    'Catatan',
                ],
                collect($plan['vehicle_adjustments'])->map(
                    static fn (array $vehicle): array => [
                        $vehicle['vehicle_code'].' / '.$vehicle['license_plate'],
                        $vehicle['current_status'].' → '.$vehicle['target_status'],
                        $vehicle['current_odometer'].' → '.$vehicle['target_odometer'],
                        $vehicle['preserved_for_manual_maintenance']
                            ? 'Status dipertahankan: ada pemeliharaan lain'
                            : '-',
                    ],
                )->all(),
            );
        }

        if ($plan['operational_asset_adjustments'] !== []) {
            $this->newLine();
            $this->table(
                ['Aset perangkat', 'Status sekarang → target', 'Aktif', 'Catatan'],
                collect($plan['operational_asset_adjustments'])->map(
                    static fn (array $asset): array => [
                        $asset['asset_code'],
                        $asset['current_status'].' → '.$asset['target_status'],
                        ($asset['current_is_active'] ? 'Ya' : 'Tidak')
                            .' → '
                            .($asset['target_is_active'] ? 'Ya' : 'Tidak'),
                        $asset['preserved_for_remaining_maintenance']
                            ? 'Status dipertahankan: ada pemeliharaan lain'
                            : '-',
                    ],
                )->all(),
            );
        }
    }
}
