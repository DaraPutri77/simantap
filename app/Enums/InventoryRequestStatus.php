<?php

namespace App\Enums;

use App\Enums\Concerns\HasEnumOptions;

enum InventoryRequestStatus: string
{
    use HasEnumOptions;

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
            self::UnderReview => 'Sedang Ditinjau',
            self::RevisionRequired => 'Perlu Revisi',
            self::Approved => 'Disetujui',
            self::PartiallyApproved => 'Disetujui Sebagian',
            self::WaitingStock => 'Menunggu Ketersediaan Stok',
            self::ReadyForDelivery => 'Siap Diserahkan',
            self::Delivered => 'Sudah Diserahkan',
            self::Completed => 'Selesai',
            self::Rejected => 'Ditolak',
            self::Cancelled => 'Dibatalkan',
            self::Expired => 'Kedaluwarsa',
        };
    }
}
