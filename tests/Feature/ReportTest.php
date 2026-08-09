<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\RoleName;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Unit;
use App\Models\User;
use App\Services\ReportService;
use App\Support\ReportCatalog;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_administrator_can_preview_and_download_filtered_stock_card_pdf(): void
    {
        $admin = $this->admin();
        $item = $this->item();
        Carbon::setTestNow('2026-08-08 17:30:00 UTC');

        $item->forceFill(['current_stock' => 12])->save();
        $item->stockMovements()->create([
            'movement_number' => 'MOV/2026/08/0001',
            'reference_number' => 'STK-IN/2026/08/0001',
            'movement_type' => 'stock_in',
            'quantity_in' => 2,
            'quantity_out' => 0,
            'stock_before' => 10,
            'stock_after' => 12,
            'transaction_date' => now(),
            'description' => 'Penerimaan pengujian laporan.',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('reports.index', [
                'report' => 'stock-card',
                'item' => $item->id,
                'from' => '2026-08-09',
                'until' => '2026-08-09',
            ]))
            ->assertOk()
            ->assertSee('Kartu Kendali Persediaan')
            ->assertSee('MOV/2026/08/0001')
            ->assertSee('09/08/2026 00:30');

        $response = $this->actingAs($admin)->get(route('reports.pdf', [
            'report' => 'stock-card',
            'item' => $item->id,
            'from' => '2026-08-09',
            'until' => '2026-08-09',
        ]));

        $response
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'event' => 'report_downloaded',
            'module' => 'report',
        ]);
    }

    public function test_every_step_21_report_type_can_build_empty_report_data(): void
    {
        $this->admin();
        $service = app(ReportService::class);

        foreach (ReportCatalog::keys() as $report) {
            $data = $service->build([
                'report' => $report,
                'search' => '',
                'itemId' => 0,
                'movementType' => '',
                'status' => '',
                'workUnit' => '',
                'from' => '',
                'until' => '',
            ]);

            $this->assertSame($report, $data['report']);
            $this->assertIsArray($data['columns']);
            $this->assertIsArray($data['rows']);
        }
    }

    public function test_employee_cannot_open_report_center_or_download_report(): void
    {
        $employee = $this->employee();

        $this->actingAs($employee)
            ->get(route('reports.index'))
            ->assertForbidden();

        $this->actingAs($employee)
            ->get(route('reports.pdf', 'stock'))
            ->assertForbidden();
    }

    private function admin(): User
    {
        $user = User::factory()->create([
            'status' => AccountStatus::Active,
        ]);
        $user->assignRole(RoleName::Administrator->value);

        return $user;
    }

    private function employee(): User
    {
        $user = User::factory()->create([
            'status' => AccountStatus::Active,
        ]);
        $user->assignRole(RoleName::Employee->value);

        return $user;
    }

    private function item(): Item
    {
        $category = ItemCategory::query()->create([
            'name' => 'Kategori Laporan '.Str::random(6),
            'is_active' => true,
        ]);
        $unit = Unit::query()->create([
            'name' => 'Unit Laporan '.Str::random(6),
            'symbol' => 'u'.Str::lower(Str::random(3)),
            'is_active' => true,
        ]);

        return Item::query()->create([
            'item_code' => 'BRG-REPORT-001',
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'name' => 'Kertas Laporan',
            'current_stock' => 10,
            'reserved_stock' => 0,
            'minimum_stock' => 2,
            'is_active' => true,
        ]);
    }
}
