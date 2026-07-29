<?php

namespace App\Enums\Concerns;

use BackedEnum;

trait HasEnumOptions
{
    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (BackedEnum $case): string => (string) $case->value,
            self::cases(),
        );
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
