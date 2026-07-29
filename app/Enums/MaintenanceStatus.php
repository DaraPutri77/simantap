<?php

namespace App\Enums;

use App\Enums\Concerns\HasEnumOptions;

enum MaintenanceStatus: string
{
    use HasEnumOptions;

    case Reported = 'reported';
    case Approved = 'approved';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case CompletedWithNotes = 'completed_with_notes';
    case FurtherActionRequired = 'further_action_required';
    case SeverelyDamaged = 'severely_damaged';
    case Unserviceable = 'unserviceable';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Reported => 'Dilaporkan',
            self::Approved => 'Disetujui',
            self::InProgress => 'Dalam Pengerjaan',
            self::Completed => 'Selesai',
            self::CompletedWithNotes => 'Selesai dengan Catatan',
            self::FurtherActionRequired => 'Memerlukan Tindakan Lanjutan',
            self::SeverelyDamaged => 'Rusak Berat',
            self::Unserviceable => 'Tidak Layak Digunakan',
            self::Cancelled => 'Dibatalkan',
        };
    }
}
