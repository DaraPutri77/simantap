<?php

namespace App\Enums;

enum InventoryRequestStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case RevisionRequired = 'revision_required';
    case Approved = 'approved';
    case PartiallyApproved = 'partially_approved';
    case WaitingStock = 'waiting_stock';
    case ReadyForDelivery = 'ready_for_delivery';
    case Delivered = 'delivered';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Diajukan',
            self::UnderReview => 'Menunggu Persetujuan',
            self::RevisionRequired => 'Perlu Perbaikan',
            self::Approved => 'Disetujui',
            self::PartiallyApproved => 'Disetujui Sebagian',
            self::WaitingStock => 'Menunggu Stok',
            self::ReadyForDelivery => 'Siap Diserahkan',
            self::Delivered => 'Telah Diserahkan',
            self::Completed => 'Selesai',
            self::Rejected => 'Ditolak',
            self::Cancelled => 'Dibatalkan',
            self::Expired => 'Kedaluwarsa',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Draft => 'Formulir masih dapat diubah oleh pegawai.',
            self::Submitted => 'Permintaan telah dikirim kepada Administrator.',
            self::UnderReview => 'Permintaan sedang diperiksa Administrator.',
            self::RevisionRequired => 'Pegawai perlu memperbaiki dan mengirim ulang permintaan.',
            self::Approved => 'Seluruh barang disetujui dan telah direservasi.',
            self::PartiallyApproved => 'Sebagian barang disetujui dan telah direservasi.',
            self::WaitingStock => 'Permintaan menunggu ketersediaan stok.',
            self::ReadyForDelivery => 'Barang siap diserahkan kepada pegawai.',
            self::Delivered => 'Barang telah diserahkan dan menunggu konfirmasi pegawai.',
            self::Completed => 'Barang telah diterima dan proses selesai.',
            self::Rejected => 'Permintaan tidak disetujui.',
            self::Cancelled => 'Permintaan telah dibatalkan.',
            self::Expired => 'Masa proses permintaan telah berakhir.',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Draft => 'bg-slate-100 text-slate-800 ring-slate-300',
            self::Submitted,
            self::UnderReview => 'bg-blue-100 text-blue-800 ring-blue-300',
            self::RevisionRequired,
            self::WaitingStock => 'bg-amber-100 text-amber-900 ring-amber-300',
            self::Approved,
            self::PartiallyApproved,
            self::ReadyForDelivery => 'bg-emerald-100 text-emerald-800 ring-emerald-300',
            self::Delivered => 'bg-cyan-100 text-cyan-900 ring-cyan-300',
            self::Completed => 'bg-teal-100 text-teal-900 ring-teal-300',
            self::Rejected,
            self::Cancelled,
            self::Expired => 'bg-red-100 text-red-800 ring-red-300',
        };
    }

    public function isEditable(): bool
    {
        return in_array($this, [
            self::Draft,
            self::RevisionRequired,
        ], true);
    }

    public function isFinal(): bool
    {
        return in_array($this, [
            self::Rejected,
            self::Completed,
            self::Cancelled,
            self::Expired,
        ], true);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $status): string => $status->value,
            self::cases(),
        );
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $status) {
            $options[$status->value] = $status->label();
        }

        return $options;
    }
}
