<?php

namespace App\Enums;

use App\Enums\Concerns\HasEnumOptions;

enum VehicleLoanStatus: string
{
    use HasEnumOptions;

    case Draft = 'draft';
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case ReadyForPickup = 'ready_for_pickup';
    case Borrowed = 'borrowed';
    case AwaitingReturnInspection = 'awaiting_return_inspection';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case ReturnIssue = 'return_issue';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Diajukan',
            self::UnderReview => 'Sedang Ditinjau',
            self::Approved => 'Disetujui',
            self::ReadyForPickup => 'Siap Diambil',
            self::Borrowed => 'Sedang Dipinjam',
            self::AwaitingReturnInspection => 'Menunggu Pemeriksaan Pengembalian',
            self::Completed => 'Selesai',
            self::Rejected => 'Ditolak',
            self::Cancelled => 'Dibatalkan',
            self::ReturnIssue => 'Bermasalah Saat Pengembalian',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Draft => 'Formulir masih dapat diubah oleh peminjam.',
            self::Submitted => 'Permintaan sudah dikirim kepada Administrator.',
            self::UnderReview => 'Permintaan sedang diperiksa Administrator.',
            self::Approved => 'Jadwal sudah disetujui dan kendaraan direservasi.',
            self::ReadyForPickup => 'Kendaraan siap diserahterimakan.',
            self::Borrowed => 'Kendaraan sedang digunakan peminjam.',
            self::AwaitingReturnInspection => 'Kendaraan menunggu pemeriksaan pengembalian.',
            self::Completed => 'Peminjaman dan pengembalian telah selesai.',
            self::Rejected => 'Permintaan tidak disetujui.',
            self::Cancelled => 'Permintaan peminjaman telah dibatalkan.',
            self::ReturnIssue => 'Pengembalian kendaraan memerlukan tindak lanjut.',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Draft => 'bg-slate-100 text-slate-800 ring-slate-300',
            self::Submitted,
            self::UnderReview => 'bg-blue-100 text-blue-800 ring-blue-300',
            self::Approved,
            self::ReadyForPickup => 'bg-emerald-100 text-emerald-800 ring-emerald-300',
            self::Borrowed => 'bg-cyan-100 text-cyan-900 ring-cyan-300',
            self::AwaitingReturnInspection => 'bg-amber-100 text-amber-950 ring-amber-300',
            self::Completed => 'bg-teal-100 text-teal-900 ring-teal-300',
            self::Rejected,
            self::Cancelled,
            self::ReturnIssue => 'bg-red-100 text-red-800 ring-red-300',
        };
    }

    public function isEditable(): bool
    {
        return $this === self::Draft;
    }

    public function isFinal(): bool
    {
        return in_array($this, [
            self::Rejected,
            self::Completed,
            self::Cancelled,
            self::ReturnIssue,
        ], true);
    }

    public function reservesSchedule(): bool
    {
        return in_array($this, [
            self::Submitted,
            self::UnderReview,
            self::Approved,
            self::ReadyForPickup,
            self::Borrowed,
            self::AwaitingReturnInspection,
            self::ReturnIssue,
        ], true);
    }
}
