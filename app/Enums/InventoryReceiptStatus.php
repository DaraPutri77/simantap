<?php

namespace App\Enums;

use App\Enums\Concerns\HasEnumOptions;

enum InventoryReceiptStatus: string
{
    use HasEnumOptions;

    case Draft = 'draft';
    case Posted = 'posted';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Posted => 'Diposting',
            self::Cancelled => 'Dibatalkan',
        };
    }
}
