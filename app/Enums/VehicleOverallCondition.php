<?php

namespace App\Enums;

use App\Enums\Concerns\HasEnumOptions;

enum VehicleOverallCondition: string
{
    use HasEnumOptions;

    case Good = 'good';
    case NeedsAttention = 'needs_attention';
    case Damaged = 'damaged';

    public function label(): string
    {
        return match ($this) {
            self::Good => 'Baik',
            self::NeedsAttention => 'Memerlukan Perhatian',
            self::Damaged => 'Rusak',
        };
    }
}
