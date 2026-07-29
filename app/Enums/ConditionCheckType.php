<?php

namespace App\Enums;

use App\Enums\Concerns\HasEnumOptions;

enum ConditionCheckType: string
{
    use HasEnumOptions;

    case Checkout = 'checkout';
    case Return = 'return';

    public function label(): string
    {
        return match ($this) {
            self::Checkout => 'Pemeriksaan Pengambilan',
            self::Return => 'Pemeriksaan Pengembalian',
        };
    }
}
