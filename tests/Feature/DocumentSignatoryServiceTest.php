<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Models\User;
use App\Services\DocumentSignatoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentSignatoryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_card_signatories_are_resolved_from_active_accounts(): void
    {
        User::factory()->create([
            'employee_number' => 'SIM-JBG-017',
            'name' => 'MITHA RAMADHANI PRATIWI',
            'status' => AccountStatus::Active,
        ]);

        User::factory()->create([
            'employee_number' => 'SIM-JBG-020',
            'name' => 'MOHAMAD ALLAMUL WAFA',
            'status' => AccountStatus::Active,
        ]);

        $signatories = app(DocumentSignatoryService::class)
            ->for('stock_card');

        $this->assertSame(
            'Kasubbag Umum',
            $signatories['kasubbag']['role_label'],
        );

        $this->assertSame(
            'MOHAMAD ALLAMUL WAFA',
            $signatories['kasubbag']['name'],
        );

        $this->assertSame(
            'Pengelola Barang',
            $signatories['inventory_manager']['role_label'],
        );

        $this->assertSame(
            'MITHA RAMADHANI PRATIWI',
            $signatories['inventory_manager']['name'],
        );
    }

    public function test_inactive_signatory_is_not_presented_as_current_signer(): void
    {
        User::factory()->create([
            'employee_number' => 'SIM-JBG-020',
            'name' => 'PEJABAT NONAKTIF',
            'status' => AccountStatus::Suspended,
        ]);

        $signatories = app(DocumentSignatoryService::class)
            ->for('stock_card');

        $this->assertNull(
            $signatories['kasubbag']['name'],
        );
    }
}
