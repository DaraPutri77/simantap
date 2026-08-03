<?php

namespace App\Support;

use Carbon\CarbonImmutable;

final class DisplayDateRange
{
    private const DISPLAY_TIMEZONE = 'Asia/Jakarta';

    /**
     * @return array{
     *     from: CarbonImmutable|null,
     *     until: CarbonImmutable|null
     * }
     */
    public static function utcBounds(
        ?string $from,
        ?string $until,
    ): array {
        return [
            'from' => $from === null || $from === ''
                ? null
                : CarbonImmutable::parse(
                    $from,
                    self::DISPLAY_TIMEZONE,
                )->startOfDay()->utc(),
            'until' => $until === null || $until === ''
                ? null
                : CarbonImmutable::parse(
                    $until,
                    self::DISPLAY_TIMEZONE,
                )->endOfDay()->utc(),
        ];
    }
}
