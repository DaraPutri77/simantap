<?php

namespace Tests\Unit;

use App\Enums\AccountStatus;
use PHPUnit\Framework\TestCase;

class AccountStatusTest extends TestCase
{
    public function test_status_labels_are_available_in_indonesian(): void
    {
        $this->assertSame(
            'Menunggu Aktivasi',
            AccountStatus::PendingActivation->label(),
        );
        $this->assertSame('Aktif', AccountStatus::Active->label());
        $this->assertSame('Nonaktif', AccountStatus::Suspended->label());
    }
}
