<?php

namespace App\Support;

use App\Models\AuditLog;
use BackedEnum;
use Illuminate\Support\Str;
use Stringable;

final class AuditLogPresenter
{
    /**
     * @var array<string, string>
     */
    private const MODULE_LABELS = [
        'authentication' => 'Autentikasi',
        'inventory' => 'Persediaan',
        'inventory_request' => 'Permintaan Barang',
        'maintenance' => 'Pemeliharaan',
        'profile' => 'Profil',
        'qr_code' => 'QR Code',
        'report' => 'Laporan',
        'system' => 'Sistem',
        'user_management' => 'Manajemen Pengguna',
        'vehicle' => 'Kendaraan',
        'vehicle_loan' => 'Peminjaman Kendaraan',
    ];

    /**
     * @var array<string, string>
     */
    private const EVENT_LABELS = [
        'account_activated' => 'Akun diaktivasi',
        'account_activation_link_sent' => 'Tautan aktivasi dikirim',
        'employee_account_created' => 'Akun pegawai dibuat',
        'employee_account_reactivated' => 'Akun pegawai diaktifkan kembali',
        'employee_account_suspended' => 'Akun pegawai dinonaktifkan',
        'employee_account_updated' => 'Akun pegawai diperbarui',
        'inactive_account_session_terminated' => 'Sesi akun nonaktif dihentikan',
        'inventory_receipt_cancelled' => 'Penerimaan persediaan dibatalkan',
        'inventory_receipt_created' => 'Penerimaan persediaan dibuat',
        'inventory_receipt_posted' => 'Penerimaan persediaan diposting',
        'inventory_receipt_updated' => 'Penerimaan persediaan diperbarui',
        'inventory_request_approved' => 'Permintaan barang disetujui',
        'inventory_request_cancelled' => 'Permintaan barang dibatalkan',
        'inventory_request_completed' => 'Permintaan barang diselesaikan',
        'inventory_request_created' => 'Permintaan barang dibuat',
        'inventory_request_delivered' => 'Barang diserahkan',
        'inventory_request_expired' => 'Permintaan barang kedaluwarsa',
        'inventory_request_partially_approved' => 'Permintaan disetujui sebagian',
        'inventory_request_ready_for_delivery' => 'Barang siap diserahkan',
        'inventory_request_rejected' => 'Permintaan barang ditolak',
        'inventory_request_revision_required' => 'Perbaikan permintaan diminta',
        'inventory_request_submitted' => 'Permintaan barang diajukan',
        'inventory_request_under_review' => 'Peninjauan permintaan dimulai',
        'inventory_request_updated' => 'Permintaan barang diperbarui',
        'inventory_request_waiting_stock' => 'Permintaan menunggu stok',
        'item_activated' => 'Barang diaktifkan',
        'item_category_created' => 'Kategori barang dibuat',
        'item_category_updated' => 'Kategori barang diperbarui',
        'item_created' => 'Barang dibuat',
        'item_deactivated' => 'Barang dinonaktifkan',
        'item_updated' => 'Barang diperbarui',
        'login_blocked' => 'Login diblokir',
        'login_failed' => 'Login gagal',
        'login_rate_limited' => 'Login dibatasi',
        'login_succeeded' => 'Login berhasil',
        'logout_succeeded' => 'Logout berhasil',
        'maintenance_approved' => 'Pemeliharaan disetujui',
        'maintenance_cancelled' => 'Pemeliharaan dibatalkan',
        'maintenance_completed' => 'Pemeliharaan diselesaikan',
        'maintenance_completed_with_notes' => 'Pemeliharaan selesai dengan catatan',
        'maintenance_further_action_required' => 'Tindak lanjut pemeliharaan diperlukan',
        'maintenance_in_progress' => 'Pemeliharaan dimulai',
        'maintenance_reported' => 'Pemeliharaan dilaporkan',
        'maintenance_severely_damaged' => 'Kendaraan dinyatakan rusak berat',
        'maintenance_unserviceable' => 'Kendaraan dinyatakan tidak layak',
        'mandatory_password_change_completed' => 'Penggantian kata sandi wajib selesai',
        'password_changed' => 'Kata sandi diubah',
        'password_reset_completed' => 'Reset kata sandi selesai',
        'password_reset_link_sent' => 'Tautan reset kata sandi dikirim',
        'password_reset_link_sent_by_admin' => 'Admin mengirim tautan reset kata sandi',
        'password_reset_link_throttled' => 'Permintaan reset kata sandi dibatasi',
        'profile_updated' => 'Profil diperbarui',
        'qr_code_downloaded' => 'QR Code diunduh',
        'qr_label_downloaded' => 'Label QR diunduh',
        'report_downloaded' => 'Laporan diunduh',
        'stock_adjustment_cancelled' => 'Penyesuaian stok dibatalkan',
        'stock_adjustment_created' => 'Penyesuaian stok dibuat',
        'stock_adjustment_posted' => 'Penyesuaian stok diposting',
        'stock_adjustment_updated' => 'Penyesuaian stok diperbarui',
        'unit_created' => 'Satuan dibuat',
        'unit_updated' => 'Satuan diperbarui',
        'vehicle_activated' => 'Kendaraan diaktifkan',
        'vehicle_created' => 'Kendaraan dibuat',
        'vehicle_deactivated' => 'Kendaraan dinonaktifkan',
        'vehicle_loan_approved' => 'Peminjaman kendaraan disetujui',
        'vehicle_loan_awaiting_return_inspection' => 'Pemeriksaan pengembalian diminta',
        'vehicle_loan_borrowed' => 'Kendaraan diserahterimakan',
        'vehicle_loan_cancelled' => 'Peminjaman kendaraan dibatalkan',
        'vehicle_loan_completed' => 'Pengembalian kendaraan selesai',
        'vehicle_loan_created' => 'Peminjaman kendaraan dibuat',
        'vehicle_loan_ready_for_pickup' => 'Kendaraan siap diambil',
        'vehicle_loan_rejected' => 'Peminjaman kendaraan ditolak',
        'vehicle_loan_return_issue' => 'Masalah pengembalian dicatat',
        'vehicle_loan_return_issue_resolved' => 'Masalah pengembalian diselesaikan',
        'vehicle_loan_submitted' => 'Peminjaman kendaraan diajukan',
        'vehicle_loan_under_review' => 'Peninjauan peminjaman dimulai',
        'vehicle_loan_updated' => 'Peminjaman kendaraan diperbarui',
        'vehicle_updated' => 'Kendaraan diperbarui',
    ];

    /**
     * @var array<string, string>
     */
    private const AUDITABLE_LABELS = [
        'inventory_receipt' => 'Penerimaan Persediaan',
        'inventory_request' => 'Permintaan Barang',
        'item' => 'Barang',
        'item_category' => 'Kategori Barang',
        'maintenance_record' => 'Pemeliharaan',
        'stock_adjustment' => 'Penyesuaian Stok',
        'unit' => 'Satuan',
        'user' => 'Pengguna',
        'vehicle' => 'Kendaraan',
        'vehicle_condition_check' => 'Pemeriksaan Kendaraan',
        'vehicle_loan' => 'Peminjaman Kendaraan',
    ];

    public static function moduleLabel(string $module): string
    {
        return self::MODULE_LABELS[$module] ?? Str::headline($module);
    }

    public static function eventLabel(string $event): string
    {
        return self::EVENT_LABELS[$event] ?? Str::headline($event);
    }

    public static function auditableLabel(?string $type): string
    {
        if ($type === null || $type === '') {
            return 'Aktivitas Sistem';
        }

        return self::AUDITABLE_LABELS[$type] ?? Str::headline($type);
    }

    public static function recordLabel(AuditLog $auditLog): string
    {
        $values = [
            ...($auditLog->old_values ?? []),
            ...($auditLog->new_values ?? []),
        ];

        foreach ([
            'request_number',
            'loan_number',
            'maintenance_number',
            'receipt_number',
            'adjustment_number',
            'vehicle_code',
            'item_code',
            'employee_number',
            'name',
        ] as $key) {
            if (isset($values[$key]) && is_scalar($values[$key])) {
                return (string) $values[$key];
            }
        }

        return $auditLog->auditable_id === null
            ? 'Tanpa objek khusus'
            : '#'.$auditLog->auditable_id;
    }

    public static function fieldLabel(string|int $field): string
    {
        return is_int($field) ? '#'.($field + 1) : Str::headline($field);
    }

    public static function formatValue(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'Ya' : 'Tidak';
        }

        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        if ($value instanceof Stringable) {
            return (string) $value;
        }

        if (is_array($value) || is_object($value)) {
            $encoded = json_encode(
                $value,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );

            return $encoded === false ? '[nilai tidak dapat ditampilkan]' : $encoded;
        }

        return (string) $value;
    }
}
