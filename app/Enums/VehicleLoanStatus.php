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
}
