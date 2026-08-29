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

    public function test_every_formal_document_resolves_the_current_officials(): void
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

        $service = app(DocumentSignatoryService::class);

        foreach ([
            'inventory_request',
            'maintenance_record',
            'reports',
            'vehicle_control_card',
            'vehicle_loan',
            'vehicle_loan_lifecycle',
        ] as $document) {
            $signatories = $service->for($document);

            $this->assertSame(
                'MOHAMAD ALLAMUL WAFA',
                $signatories['kasubbag']['name'],
                "Kasubbag tidak terselesaikan untuk {$document}.",
            );
            $this->assertSame(
                'SIM-JBG-020',
                $signatories['kasubbag']['employee_number'],
                "Nomor pegawai Kasubbag tidak tepat untuk {$document}.",
            );
            $this->assertSame(
                'MITHA RAMADHANI PRATIWI',
                $signatories['administrator']['name'],
                "Administrator tidak terselesaikan untuk {$document}.",
            );
            $this->assertSame(
                'SIM-JBG-017',
                $signatories['administrator']['employee_number'],
                "Nomor pegawai Administrator tidak tepat untuk {$document}.",
            );
        }
    }

    public function test_shared_pdf_signature_section_distinguishes_manual_approval(): void
    {
        $html = view('pdf.official-signatories', [
            'documentSignatories' => [
                'kasubbag' => [
                    'role_label' => 'Kasubbag Umum',
                    'name' => 'PEJABAT KASUBBAG',
                    'employee_number' => 'NIP-001',
                ],
                'administrator' => [
                    'role_label' => 'Administrator / Pengelola Barang',
                    'name' => 'PEJABAT ADMINISTRATOR',
                    'employee_number' => 'NIP-002',
                ],
            ],
        ])->render();

        $this->assertStringContainsString('PEJABAT KASUBBAG', $html);
        $this->assertStringContainsString('PEJABAT ADMINISTRATOR', $html);
        $this->assertStringContainsString(
            'Pengesahan administratif.',
            $html,
        );
        $this->assertStringNotContainsString(
            'tidak menggantikan tanda tangan digital',
            $html,
        );
    }
}
