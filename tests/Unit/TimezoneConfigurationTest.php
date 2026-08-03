<?php

namespace Tests\Unit;

use Tests\TestCase;

class TimezoneConfigurationTest extends TestCase
{
    public function test_storage_and_display_timezones_are_separated(): void
    {
        $this->assertSame('UTC', config('app.timezone'));
        $this->assertSame(
            'Asia/Jakarta',
            config('simantap.display_timezone'),
        );
    }
}
