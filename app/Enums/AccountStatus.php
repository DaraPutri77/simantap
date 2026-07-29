<?php

namespace App\Enums;

use App\Enums\Concerns\HasEnumOptions;

enum AccountStatus: string
{
    use HasEnumOptions;

    case PendingActivation = 'pending_activation';
    case Active = 'active';
    case Inactive = 'inactive';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::PendingActivation => 'Menunggu Aktivasi',
            self::Active => 'Aktif',
            self::Inactive => 'Tidak Aktif',
            self::Suspended => 'Ditangguhkan',
        };
    }
}
