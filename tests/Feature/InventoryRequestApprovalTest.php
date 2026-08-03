<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\InventoryRequestStatus;
use App\Enums\RoleName;
use App\Models\InventoryRequest;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InventoryRequestApprovalTest extends TestCase
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

    public function test_admin_queue_only_shows_actionable_requests_in_priority_order(): void
    {
        $admin = $this->admin();
        $employee = $this->employee();
        $item = $this->item();
        $newerRequest = $this->submittedRequest(
            $employee,
            $item,
            2,
            '2026-08-02',
        );
        $olderRequest = $this->submittedRequest(
            $employee,
            $item,
            3,
            '2026-08-01',
        );
        $draftRequest = $this->draftRequest(
            $employee,
            $item,
            1,
            '2026-07-31',
        );
        $newerRequest->forceFill([
            'submitted_at' => '2026-08-02 08:00:00',
        ])->save();
        $olderRequest->forceFill([
            'submitted_at' => '2026-08-01 08:00:00',
        ])->save();

        $this->actingAs($admin)
            ->get(route('inventory-requests.approval-queue'))
            ->assertOk()
            ->assertSeeInOrder([
                $olderRequest->request_number,
                $newerRequest->request_number,
            ])
            ->assertDontSee($draftRequest->request_number)
            ->assertSee('Stok Cukup')
            ->assertSee('1 dari 1 jenis barang mencukupi', false);
    }

    public function test_admin_can_filter_approval_queue_by_stage(): void
    {
        $admin = $this->admin();
        $employee = $this->employee();
        $item = $this->item();
        $submittedRequest = $this->submittedRequest(
            $employee,
            $item,
            2,
            '2026-08-01',
        );
        $waitingRequest = $this->submittedRequest(
            $employee,
            $item,
            12,
            '2026-08-02',
        );

        $this->actingAs($admin)
            ->post(route('inventory-requests.review', $waitingRequest))
            ->assertRedirect();
        $this->actingAs($admin)
            ->post(
                route(
                    'inventory-requests.await-stock',
                    $waitingRequest,
                ),
                ['admin_notes' => 'Stok belum mencukupi permintaan.'],
            )
            ->assertRedirect();

        $this->actingAs($admin)
            ->get(route('inventory-requests.approval-queue', [
                'stage' => InventoryRequestStatus::WaitingStock->value,
            ]))
            ->assertOk()
            ->assertSee($waitingRequest->request_number)
            ->assertDontSee($submittedRequest->request_number)
            ->assertSee('Tersedia Sebagian');
    }

    public function test_employee_cannot_open_admin_approval_queue(): void
    {
        $this->actingAs($this->employee())
            ->get(route('inventory-requests.approval-queue'))
            ->assertForbidden();
    }

    public function test_starting_review_records_reviewer_and_immutable_history(): void
    {
        $admin = $this->admin();
        $inventoryRequest = $this->submittedRequest(
            $this->employee(),
            $this->item(),
            2,
            '2026-08-01',
        );

        $this->actingAs($admin)
            ->post(route('inventory-requests.review', $inventoryRequest))
            ->assertRedirect();

        $inventoryRequest->refresh();

        $this->assertSame(
            InventoryRequestStatus::UnderReview,
            $inventoryRequest->status,
        );
        $this->assertSame($admin->id, $inventoryRequest->reviewed_by);
        $this->assertNotNull($inventoryRequest->reviewed_at);
        $this->assertDatabaseHas('request_status_histories', [
            'inventory_request_id' => $inventoryRequest->id,
            'previous_status' => InventoryRequestStatus::Submitted->value,
            'new_status' => InventoryRequestStatus::UnderReview->value,
            'changed_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('inventory-requests.approval-queue'))
            ->assertOk()
            ->assertSee($admin->name)
            ->assertSee('Lanjutkan Keputusan');
    }

    private function admin(): User
    {
        $admin = $this->activeUser([
            'name' => 'Administrator Approval',
            'employee_number' => 'ADMIN-APPROVAL',
            'email' => 'admin.approval@example.test',
            'position' => 'Pengelola Barang',
        ]);
        $admin->assignRole(RoleName::Administrator->value);

        return $admin;
    }

    private function employee(): User
    {
        $employee = $this->activeUser([
            'name' => 'Pegawai Pemohon Approval',
            'employee_number' => 'PEG-APPROVAL-001',
            'email' => 'pegawai.approval@example.test',
            'work_unit' => 'Bagian Umum',
            'position' => 'Statistisi',
        ]);
        $employee->assignRole(RoleName::Employee->value);

        return $employee;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function activeUser(array $attributes): User
    {
        return User::factory()->create([
            'status' => AccountStatus::Active,
            'must_change_password' => false,
            ...$attributes,
        ]);
    }

    private function item(): Item
    {
        $category = ItemCategory::query()->first()
            ?? ItemCategory::query()->create([
                'name' => 'Kategori Approval',
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
            'item_code' => 'BRG-APPROVAL-001',
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'name' => 'Kertas Approval',
            'description' => null,
            'current_stock' => 10,
            'reserved_stock' => 0,
            'minimum_stock' => 2,
            'storage_location' => 'Gudang Umum',
            'is_active' => true,
        ]);
    }

    private function draftRequest(
        User $employee,
        Item $item,
        float $quantity,
        string $requestDate,
    ): InventoryRequest {
        $this->actingAs($employee)->post(
            route('my.inventory-requests.store'),
            [
                'request_date' => $requestDate,
                'purpose' => 'Kebutuhan operasional approval.',
                'notes' => 'Mohon diperiksa.',
                'items' => [
                    [
                        'item_id' => $item->id,
                        'requested_quantity' => $quantity,
                        'notes' => null,
                    ],
                ],
            ],
        )->assertRedirect();

        return InventoryRequest::query()
            ->where('requested_by', $employee->id)
            ->latest('id')
            ->firstOrFail();
    }

    private function submittedRequest(
        User $employee,
        Item $item,
        float $quantity,
        string $requestDate,
    ): InventoryRequest {
        $inventoryRequest = $this->draftRequest(
            $employee,
            $item,
            $quantity,
            $requestDate,
        );
        $this->actingAs($employee)->post(
            route('my.inventory-requests.submit', $inventoryRequest),
            [
                'signature_data' => $this->signatureDataUrl(),
                'signature_consent' => '1',
            ],
        )->assertRedirect();

        return $inventoryRequest->refresh();
    }

    private function signatureDataUrl(): string
    {
        return 'data:image/png;base64,'
            .'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwC'
            .'AAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';
    }
}
