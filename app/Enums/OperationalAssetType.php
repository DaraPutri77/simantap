<?php

namespace App\Enums;

use App\Enums\Concerns\HasEnumOptions;

enum OperationalAssetType: string
{
    use HasEnumOptions;

    case Pc = 'pc';
    case Laptop = 'laptop';
    case Printer = 'printer';

    public function label(): string
    {
        return match ($this) {
            self::Pc => 'PC',
            self::Laptop => 'Laptop',
            self::Printer => 'Printer',
        };
    }
}
