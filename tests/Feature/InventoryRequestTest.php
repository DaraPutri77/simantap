<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\DigitalSignaturePurpose;
use App\Enums\InventoryRequestStatus;
use App\Enums\RoleName;
use App\Enums\StockMovementType;
use App\Models\DigitalSignature;
use App\Models\InventoryRequest;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InventoryRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RoleAndPermissionSeeder::class,
            ReferenceDataSeeder::class,
        ]);
        Storage::fake(
            (string) config('simantap.uploads.disk', 'local'),
        );
    }

    public function test_employee_creates_draft_without_changing_stock(): void
    {
        $employee = $this->employee();
        $item = $this->item();

        $response = $this->actingAs($employee)->post(
            route('my.inventory-requests.store'),
            $this->draftPayload($item, 4),
        );

        $inventoryRequest = InventoryRequest::query()->firstOrFail();

        $response->assertRedirect(
            route('my.inventory-requests.show', $inventoryRequest),
        );
        $this->assertSame(
            InventoryRequestStatus::Draft,
            $inventoryRequest->status,
        );
        $this->assertSame(
            $employee->name,
            $inventoryRequest->requester_name_snapshot,
        );
        $this->assertSame(
            '10.00',
            $item->refresh()->current_stock,
        );
        $this->assertSame('0.00', $item->reserved_stock);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseHas('request_status_histories', [
            'inventory_request_id' => $inventoryRequest->id,
            'new_status' => InventoryRequestStatus::Draft->value,
        ]);
    }

    public function test_employee_only_sees_and_opens_own_requests(): void
    {
        $employee = $this->employee();
        $other = $this->employee([
            'employee_number' => 'PEG-OTHER',
            'email' => 'other@example.test',
        ]);
        $ownRequest = $this->draftRequest($employee, $this->item());
        $otherRequest = $this->draftRequest(
            $other,
            $this->item([
                'item_code' => 'BRG-OTHER',
                'name' => 'Barang Pegawai Lain',
            ]),
        );

        $this->actingAs($employee)
            ->get(route('my.inventory-requests.index'))
            ->assertOk()
            ->assertSee($ownRequest->request_number)
            ->assertDontSee($otherRequest->request_number);

        $this->actingAs($employee)
            ->get(route('my.inventory-requests.show', $otherRequest))
            ->assertForbidden();
    }

    public function test_submission_does_not_require_applicant_signature_or_change_stock(): void
    {
        $employee = $this->employee();
        $item = $this->item();
        $inventoryRequest = $this->draftRequest(
            $employee,
            $item,
            3,
        );

        $this->actingAs($employee)
            ->post(
                route(
                    'my.inventory-requests.submit',
                    $inventoryRequest,
                ),
                [],
            )
            ->assertRedirect();

        $this->assertSame(
            InventoryRequestStatus::Submitted,
            $inventoryRequest->refresh()->status,
        );
        $this->assertSame('10.00', $item->refresh()->current_stock);
        $this->assertSame('0.00', $item->reserved_stock);
        $this->assertDatabaseCount('digital_signatures', 0);
        $this->assertNull($inventoryRequest->submissionSignature());
    }

    public function test_request_form_uses_fixed_purpose_and_discards_employee_notes(): void
    {
        $employee = $this->employee();
        $item = $this->item();
        $payload = $this->draftPayload($item, 2);
        $payload['purpose'] = 'Nilai dari klien harus diabaikan.';
        $payload['notes'] = 'Catatan umum harus dibuang.';
        $payload['items'][0]['notes'] = 'Catatan barang harus dibuang.';

        $this->actingAs($employee)
            ->get(route('my.inventory-requests.create'))
            ->assertOk()
            ->assertDontSee('id="purpose"', false)
            ->assertDontSee('id="notes"', false)
            ->assertDontSee('items_0_notes', false);

        $this->actingAs($employee)
            ->post(route('my.inventory-requests.store'), $payload)
            ->assertRedirect();

        $inventoryRequest = InventoryRequest::query()->firstOrFail();

        $this->assertSame(
            InventoryRequest::DEFAULT_PURPOSE,
            $inventoryRequest->purpose,
        );
        $this->assertNull($inventoryRequest->notes);
        $this->assertNull(
            $inventoryRequest->items()->firstOrFail()->notes,
        );
    }

    public function test_admin_approval_reserves_stock_without_reducing_physical_stock(): void
    {
        $admin = $this->admin();
        $employee = $this->employee();
        $item = $this->item();
        $inventoryRequest = $this->submittedRequest(
            $employee,
            $item,
            4,
        );

        $this->actingAs($admin)
            ->post(route('inventory-requests.review', $inventoryRequest))
            ->assertRedirect();
        $line = $inventoryRequest->items()->firstOrFail();
        $this->actingAs($admin)
            ->post(
                route(
                    'inventory-requests.approve',
                    $inventoryRequest,
                ),
                [
                    'items' => [
                        $line->id => [
                            'approved_quantity' => 4,
                            'admin_notes' => 'Sesuai kebutuhan.',
                        ],
                    ],
                    'admin_notes' => 'Disetujui seluruhnya.',
                    ...$this->signaturePayload(),
                ],
            )
            ->assertRedirect();

        $this->assertSame(
            InventoryRequestStatus::Approved,
            $inventoryRequest->refresh()->status,
        );
        $this->assertSame('10.00', $item->refresh()->current_stock);
        $this->assertSame('4.00', $item->reserved_stock);
        $this->assertSame('6.00', $item->available_stock);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_partial_approval_and_insufficient_stock_are_handled_atomically(): void
    {
        $admin = $this->admin();
        $employee = $this->employee();
        $item = $this->item();
        $inventoryRequest = $this->submittedRequest(
            $employee,
            $item,
            12,
        );

        $this->actingAs($admin)
            ->post(route('inventory-requests.review', $inventoryRequest));
        $line = $inventoryRequest->items()->firstOrFail();

        $this->actingAs($admin)
            ->post(
                route(
                    'inventory-requests.approve',
                    $inventoryRequest,
                ),
                [
                    'items' => [
                        $line->id => [
                            'approved_quantity' => 11,
                        ],
                    ],
                    ...$this->signaturePayload(),
                ],
            )
            ->assertSessionHasErrors(
                "items.{$line->id}.approved_quantity",
            );

        $this->assertSame(
            InventoryRequestStatus::UnderReview,
            $inventoryRequest->refresh()->status,
        );
        $this->assertSame('0.00', $item->refresh()->reserved_stock);

        $this->actingAs($admin)
            ->post(
                route(
                    'inventory-requests.approve',
                    $inventoryRequest,
                ),
                [
                    'items' => [
                        $line->id => [
                            'approved_quantity' => 5,
                        ],
                    ],
                    ...$this->signaturePayload(),
                ],
            )
            ->assertRedirect();

        $this->assertSame(
            InventoryRequestStatus::PartiallyApproved,
            $inventoryRequest->refresh()->status,
        );
        $this->assertSame('5.00', $item->refresh()->reserved_stock);
    }

    public function test_delivery_reduces_stock_and_creates_immutable_ledger(): void
    {
        [
            $admin,
            $employee,
            $item,
            $inventoryRequest,
        ] = $this->approvedRequest(4);
        $line = $inventoryRequest->items()->firstOrFail();

        $this->actingAs($admin)
            ->post(route('inventory-requests.ready', $inventoryRequest))
            ->assertRedirect();
        $this->actingAs($admin)
            ->post(
                route(
                    'inventory-requests.deliver',
                    $inventoryRequest,
                ),
                [
                    'items' => [
                        $line->id => [
                            'delivered_quantity' => 4,
                        ],
                    ],
                    'delivery_notes' => 'Diserahkan di ruang umum.',
                ],
            )
            ->assertRedirect();

        $this->assertSame(
            InventoryRequestStatus::Delivered,
            $inventoryRequest->refresh()->status,
        );
        $this->assertSame('6.00', $item->refresh()->current_stock);
        $this->assertSame('0.00', $item->reserved_stock);

        $movement = StockMovement::query()->firstOrFail();

        $this->assertSame(
            StockMovementType::RequestOut,
            $movement->movement_type,
        );
        $this->assertSame('4.00', $movement->quantity_out);
        $this->assertSame(
            $inventoryRequest->request_number,
            $movement->reference_number,
        );
        $this->assertSame('inventory_request', $movement->reference_type);
    }

    public function test_employee_confirmation_completes_request(): void
    {
        [
            $admin,
            $employee,
            $item,
            $inventoryRequest,
        ] = $this->approvedRequest(2);
        $line = $inventoryRequest->items()->firstOrFail();

        $this->actingAs($admin)
            ->post(route('inventory-requests.ready', $inventoryRequest));
        $this->actingAs($admin)
            ->post(
                route(
                    'inventory-requests.deliver',
                    $inventoryRequest,
                ),
                [
                    'items' => [
                        $line->id => [
                            'delivered_quantity' => 2,
                        ],
                    ],
                ],
            );

        $this->actingAs($employee)
            ->post(
                route(
                    'my.inventory-requests.confirm-receipt',
                    $inventoryRequest,
                ),
                [
                    'signature_data' => $this->signatureDataUrl(),
                    'receipt_consent' => '1',
                ],
            )
            ->assertRedirect();

        $this->assertSame(
            InventoryRequestStatus::Completed,
            $inventoryRequest->refresh()->status,
        );
        $this->assertNotNull($inventoryRequest->received_at);
        $this->assertNotNull($inventoryRequest->completed_at);
        $this->assertSame(
            2,
            DigitalSignature::query()
                ->where('signable_id', $inventoryRequest->id)
                ->count(),
        );
    }

    public function test_revision_and_resubmission_do_not_create_applicant_signatures(): void
    {
        $admin = $this->admin();
        $employee = $this->employee();
        $item = $this->item();
        $inventoryRequest = $this->submittedRequest(
            $employee,
            $item,
            3,
        );

        $this->assertDatabaseCount('digital_signatures', 0);

        $this->actingAs($admin)
            ->post(route('inventory-requests.review', $inventoryRequest));
        $this->actingAs($admin)
            ->post(
                route(
                    'inventory-requests.revision',
                    $inventoryRequest,
                ),
                [
                    'revision_note' => 'Periksa kembali daftar barang.',
                ],
            )
            ->assertRedirect();

        $inventoryRequest->refresh();

        $this->assertSame(
            InventoryRequestStatus::RevisionRequired,
            $inventoryRequest->status,
        );
        $this->assertNull($inventoryRequest->submissionSignature());

        $payload = $this->draftPayload($item, 2);
        $this->actingAs($employee)
            ->put(
                route(
                    'my.inventory-requests.update',
                    $inventoryRequest,
                ),
                $payload,
            )
            ->assertRedirect();
        $this->actingAs($employee)
            ->post(
                route(
                    'my.inventory-requests.submit',
                    $inventoryRequest,
                ),
                [],
            )
            ->assertRedirect();

        $inventoryRequest->refresh();
        $this->assertSame(
            InventoryRequestStatus::Submitted,
            $inventoryRequest->status,
        );
        $this->assertDatabaseMissing('digital_signatures', [
            'signable_type' => 'inventory_request',
            'signable_id' => $inventoryRequest->id,
            'purpose' => DigitalSignaturePurpose::InventoryRequestSubmission->value,
        ]);
    }

    public function test_cancellation_releases_existing_reservation(): void
    {
        [
            $admin,
            $employee,
            $item,
            $inventoryRequest,
        ] = $this->approvedRequest(4);

        $this->actingAs($admin)
            ->patch(
                route(
                    'inventory-requests.cancel',
                    $inventoryRequest,
                ),
                [
                    'cancellation_reason' => 'Kegiatan dibatalkan.',
                ],
            )
            ->assertRedirect();

        $this->assertSame(
            InventoryRequestStatus::Cancelled,
            $inventoryRequest->refresh()->status,
        );
        $this->assertSame('10.00', $item->refresh()->current_stock);
        $this->assertSame('0.00', $item->reserved_stock);
    }

    public function test_request_pdf_can_be_downloaded_by_owner_and_admin(): void
    {
        $admin = $this->admin();
        $employee = $this->employee();
        $inventoryRequest = $this->submittedRequest(
            $employee,
            $this->item(),
            2,
        );

        $this->actingAs($employee)
            ->get(
                route(
                    'my.inventory-requests.pdf',
                    $inventoryRequest,
                ),
            )
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($admin)
            ->get(
                route(
                    'inventory-requests.pdf',
                    $inventoryRequest,
                ),
            )
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_request_history_and_signature_times_are_displayed_in_current_wib(): void
    {
        $this->travelTo(CarbonImmutable::create(
            2026,
            8,
            3,
            2,
            47,
            0,
            'UTC',
        ));

        try {
            $employee = $this->employee();
            $inventoryRequest = $this->submittedRequest(
                $employee,
                $this->item(),
                2,
            );

            $this->actingAs($employee)
                ->get(route(
                    'my.inventory-requests.show',
                    $inventoryRequest,
                ))
                ->assertOk()
                ->assertSee('09:47')
                ->assertDontSee('02:47');

            $latestHistory = $inventoryRequest
                ->statusHistories()
                ->latest('changed_at')
                ->firstOrFail();

            $this->assertSame(
                '2026-08-03 02:47:00',
                $latestHistory->changed_at
                    ->copy()
                    ->utc()
                    ->format('Y-m-d H:i:s'),
            );
        } finally {
            $this->travelBack();
        }
    }

    public function test_batch3a_request_form_displays_category_and_minimum_stock_warning(): void
    {
        $employee = $this->employee();

        $category = ItemCategory::query()->create([
            'name' => 'Kategori Batch 3A',
            'description' => null,
            'is_active' => true,
        ]);

        $this->item([
            'item_code' => 'BRG-B3A-CAT',
            'name' => 'Tinta Printer Batch 3A',
            'category_id' => $category->id,
            'current_stock' => 5,
            'reserved_stock' => 0,
            'minimum_stock' => 5,
        ]);

        $this->actingAs($employee)
            ->get(route('my.inventory-requests.create'))
            ->assertOk()
            ->assertSee('BRG-B3A-CAT')
            ->assertSee('Tinta Printer Batch 3A')
            ->assertSee('Kategori Batch 3A')
            ->assertSee('STOK MINIMUM');
    }

    public function test_batch3a_item_at_minimum_stock_cannot_be_requested(): void
    {
        $employee = $this->employee();

        $item = $this->item([
            'item_code' => 'BRG-B3A-MIN',
            'name' => 'Barang Batas Minimum',
            'current_stock' => 5,
            'reserved_stock' => 0,
            'minimum_stock' => 5,
        ]);

        $this->actingAs($employee)
            ->post(
                route('my.inventory-requests.store'),
                $this->draftPayload($item, 1),
            )
            ->assertSessionHasErrors([
                'items.0.item_id',
            ]);

        $this->assertDatabaseCount(
            'inventory_requests',
            0,
        );

        $this->assertSame(
            '5.00',
            $item->refresh()->current_stock,
        );

        $this->assertSame(
            '0.00',
            $item->reserved_stock,
        );

        $this->assertDatabaseCount(
            'stock_movements',
            0,
        );
    }

    public function test_batch3a_submit_rechecks_minimum_stock(): void
    {
        $employee = $this->employee();

        $item = $this->item([
            'item_code' => 'BRG-B3A-SUBMIT',
            'name' => 'Barang Minimum Saat Submit',
            'current_stock' => 10,
            'reserved_stock' => 0,
            'minimum_stock' => 5,
        ]);

        $inventoryRequest = $this->draftRequest(
            $employee,
            $item,
            1,
        );

        $item->forceFill([
            'current_stock' => 5,
        ])->save();

        $this->actingAs($employee)
            ->post(
                route(
                    'my.inventory-requests.submit',
                    $inventoryRequest,
                ),
                $this->signaturePayload(),
            )
            ->assertSessionHasErrors([
                'items',
            ]);

        $this->assertSame(
            InventoryRequestStatus::Draft,
            $inventoryRequest->refresh()->status,
        );

        $this->assertDatabaseCount(
            'digital_signatures',
            0,
        );

        $this->assertDatabaseCount(
            'stock_movements',
            0,
        );

        $disk = (string) config(
            'simantap.uploads.disk',
            'local',
        );

        $this->assertSame(
            [],
            Storage::disk($disk)
                ->allFiles(
                    'signatures/inventory-requests/'
                    .$inventoryRequest->id,
                ),
        );
    }

    public function test_batch3a_revision_note_remains_after_resubmission(): void
    {
        $admin = $this->admin();
        $employee = $this->employee();
        $item = $this->item();

        $inventoryRequest = $this->submittedRequest(
            $employee,
            $item,
            3,
        );

        $this->actingAs($admin)
            ->post(
                route(
                    'inventory-requests.review',
                    $inventoryRequest,
                ),
            )
            ->assertRedirect();

        $this->actingAs($admin)
            ->post(
                route(
                    'inventory-requests.revision',
                    $inventoryRequest,
                ),
                [
                    'revision_note' => 'Perbaiki rincian kebutuhan barang.',
                ],
            )
            ->assertRedirect();

        $this->assertSame(
            'Perbaiki rincian kebutuhan barang.',
            $inventoryRequest->refresh()->revision_note,
        );

        $this->assertDatabaseHas(
            'request_status_histories',
            [
                'inventory_request_id' => $inventoryRequest->id,
                'new_status' => InventoryRequestStatus::RevisionRequired->value,
                'notes' => 'Perbaiki rincian kebutuhan barang.',
            ],
        );

        $payload = $this->draftPayload(
            $item,
            2,
        );

        $payload['purpose'] = 'Keperluan setelah diperbaiki.';

        $this->actingAs($employee)
            ->put(
                route(
                    'my.inventory-requests.update',
                    $inventoryRequest,
                ),
                $payload,
            )
            ->assertRedirect();

        $this->actingAs($employee)
            ->post(
                route(
                    'my.inventory-requests.submit',
                    $inventoryRequest,
                ),
                $this->signaturePayload(),
            )
            ->assertRedirect();

        $inventoryRequest->refresh();

        $this->assertSame(
            InventoryRequestStatus::Submitted,
            $inventoryRequest->status,
        );

        $this->assertSame(
            'Perbaiki rincian kebutuhan barang.',
            $inventoryRequest->revision_note,
        );

        $this->actingAs($employee)
            ->get(
                route(
                    'my.inventory-requests.show',
                    $inventoryRequest,
                ),
            )
            ->assertOk()
            ->assertSee('Catatan Perbaikan Terakhir')
            ->assertSee(
                'Perbaiki rincian kebutuhan barang.',
            );
    }

    private function admin(): User
    {
        $admin = $this->activeUser([
            'name' => 'Administrator Persediaan',
            'employee_number' => 'ADMIN-REQ',
            'email' => 'admin.req@example.test',
            'position' => 'Pengelola Barang',
        ]);
        $admin->assignRole(RoleName::Administrator->value);

        return $admin;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function employee(array $attributes = []): User
    {
        $employee = $this->activeUser([
            'name' => 'Pegawai Pemohon',
            'employee_number' => 'PEG-REQ-001',
            'email' => 'pegawai.req@example.test',
            'work_unit' => 'Bagian Umum',
            'position' => 'Statistisi',
            ...$attributes,
        ]);
        $employee->assignRole(RoleName::Employee->value);

        return $employee;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function activeUser(array $attributes = []): User
    {
        return User::factory()->create([
            'status' => AccountStatus::Active,
            'must_change_password' => false,
            ...$attributes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function item(array $attributes = []): Item
    {
        $category = ItemCategory::query()->first()
            ?? ItemCategory::query()->create([
                'name' => 'Kategori Pengujian',
                'description' => null,
                'is_active' => true,
            ]);
        $unit = Unit::query()->first()
            ?? Unit::query()->create([
                'name' => 'Buah',
                'symbol' => 'buah',
                'is_active' => true,
            ]);

        return Item::query()->create([
            'item_code' => 'BRG-REQ-001',
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'name' => 'Kertas Permintaan',
            'description' => null,
            'current_stock' => 10,
            'reserved_stock' => 0,
            'minimum_stock' => 2,
            'storage_location' => 'Gudang Umum',
            'is_active' => true,
            ...$attributes,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function draftPayload(Item $item, float $quantity): array
    {
        return [
            'request_date' => '2026-07-31',
            'purpose' => 'Kebutuhan operasional pengujian.',
            'notes' => 'Mohon diproses.',
            'items' => [
                [
                    'item_id' => $item->id,
                    'requested_quantity' => $quantity,
                    'notes' => null,
                ],
            ],
        ];
    }

    private function draftRequest(
        User $employee,
        Item $item,
        float $quantity = 2,
    ): InventoryRequest {
        $this->actingAs($employee)->post(
            route('my.inventory-requests.store'),
            $this->draftPayload($item, $quantity),
        );

        return InventoryRequest::query()
            ->where('requested_by', $employee->id)
            ->latest('id')
            ->firstOrFail();
    }

    private function submittedRequest(
        User $employee,
        Item $item,
        float $quantity,
    ): InventoryRequest {
        $inventoryRequest = $this->draftRequest(
            $employee,
            $item,
            $quantity,
        );
        $this->actingAs($employee)->post(
            route(
                'my.inventory-requests.submit',
                $inventoryRequest,
            ),
            [],
        );

        return $inventoryRequest->refresh();
    }

    /**
     * @return array{0: User, 1: User, 2: Item, 3: InventoryRequest}
     */
    private function approvedRequest(float $quantity): array
    {
        $admin = $this->admin();
        $employee = $this->employee();
        $item = $this->item();
        $inventoryRequest = $this->submittedRequest(
            $employee,
            $item,
            $quantity,
        );
        $this->actingAs($admin)
            ->post(route('inventory-requests.review', $inventoryRequest));
        $line = $inventoryRequest->items()->firstOrFail();
        $this->actingAs($admin)->post(
            route('inventory-requests.approve', $inventoryRequest),
            [
                'items' => [
                    $line->id => [
                        'approved_quantity' => $quantity,
                    ],
                ],
                ...$this->signaturePayload(),
            ],
        );

        return [
            $admin,
            $employee,
            $item->refresh(),
            $inventoryRequest->refresh(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function signaturePayload(): array
    {
        return [
            'signature_data' => $this->signatureDataUrl(),
            'signature_consent' => '1',
        ];
    }

    private function signatureDataUrl(): string
    {
        return 'data:image/png;base64,'
            .'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwC'
            .'AAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';
    }
}
