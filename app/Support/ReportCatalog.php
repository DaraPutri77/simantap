<?php

namespace App\Support;

final class ReportCatalog
{
    /**
     * @return array<string, array{label: string, description: string}>
     */
    public static function all(): array
    {
        return [
            'stock' => [
                'label' => 'Stok Persediaan',
                'description' => 'Saldo fisik, reservasi, saldo tersedia, dan batas minimum setiap barang.',
            ],
            'stock-in' => [
                'label' => 'Barang Masuk',
                'description' => 'Pergerakan stok masuk dari penerimaan, pengembalian, atau penyesuaian.',
            ],
            'stock-out' => [
                'label' => 'Barang Keluar',
                'description' => 'Pergerakan stok keluar untuk permintaan, kerusakan, atau penyesuaian.',
            ],
            'stock-card' => [
                'label' => 'Kartu Kendali Persediaan',
                'description' => 'Ledger stok berurutan dengan saldo sebelum, masuk, keluar, dan sesudah.',
            ],
            'inventory-requests' => [
                'label' => 'Permintaan Barang',
                'description' => 'Rekap permintaan pegawai beserta status, unit kerja, dan keperluannya.',
            ],
            'inventory-usage' => [
                'label' => 'Penggunaan Barang',
                'description' => 'Rekap barang yang telah diserahkan kepada pegawai dan unit kerja.',
            ],
            'vehicle-loans' => [
                'label' => 'Peminjaman Kendaraan',
                'description' => 'Rekap jadwal, peminjam, kendaraan, tujuan, dan status peminjaman.',
            ],
            'vehicle-overdue' => [
                'label' => 'Keterlambatan Kendaraan',
                'description' => 'Daftar peminjaman yang tercatat melewati waktu pengembalian.',
            ],
            'maintenance' => [
                'label' => 'Pemeliharaan Kendaraan',
                'description' => 'Rekap laporan pemeliharaan, kendaraan, biaya, hasil, dan status.',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    /**
     * @return array{label: string, description: string}
     */
    public static function definition(string $key): array
    {
        return self::all()[$key] ?? self::all()['stock'];
    }
}
