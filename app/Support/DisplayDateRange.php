<?php

namespace App\Support;

use Carbon\CarbonImmutable;

final class DisplayDateRange
{
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
                    self::displayTimezone(),
                )->startOfDay()->utc(),
            'until' => $until === null || $until === ''
                ? null
                : CarbonImmutable::parse(
                    $until,
                    self::displayTimezone(),
                )->endOfDay()->utc(),
        ];
    }

    private static function displayTimezone(): string
    {
        return (string) config(
            'simantap.display_timezone',
            'Asia/Jakarta',
        );
    }
}
