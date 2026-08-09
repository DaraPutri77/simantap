<?php

namespace Tests\Feature;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuditLogWorkspaceTest extends TestCase
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

    public function test_administrator_can_open_audit_list_and_detail_in_wib(): void
    {
        $admin = $this->admin();
        Carbon::setTestNow('2026-08-08 17:30:45 UTC');
        $auditLog = $this->auditLog([
            'actor_id' => $admin->id,
            'event' => 'inventory_request_approved',
            'module' => 'inventory_request',
            'auditable_type' => 'inventory_request',
            'auditable_id' => 17,
            'new_values' => [
                'request_number' => 'REQ/2026/08/0017',
                'status' => 'approved',
            ],
        ]);

        $this->actingAs($admin)
            ->get(route('audit-logs.index'))
            ->assertOk()
            ->assertSee('Audit Log')
            ->assertSee('Permintaan barang disetujui')
            ->assertSee('09 Agt 2026, 00:30:45')
            ->assertSee('REQ/2026/08/0017');

        $this->actingAs($admin)
            ->get(route('audit-logs.show', $auditLog))
            ->assertOk()
            ->assertSee('Detail Audit Log')
            ->assertSee('09 Agustus 2026, 00:30:45')
            ->assertSee('Nilai Sesudah')
            ->assertSee('approved');
    }

    public function test_employee_cannot_access_audit_even_when_given_permission(): void
    {
        $employee = $this->employee();
        $employee->givePermissionTo(PermissionName::AuditLogView->value);
        $auditLog = $this->auditLog();

        $this->actingAs($employee)
            ->get(route('audit-logs.index'))
            ->assertForbidden();

        $this->actingAs($employee)
            ->get(route('audit-logs.show', $auditLog))
            ->assertForbidden();
    }

    public function test_filters_select_exact_activity_and_respect_wib_date_bounds(): void
    {
        $admin = $this->admin(['name' => 'Administrator Audit Utama']);
        $other = $this->admin(['name' => 'Administrator Lain']);
        $requestId = (string) Str::uuid();

        Carbon::setTestNow('2026-08-08 17:15:00 UTC');
        $expected = $this->auditLog([
            'request_id' => $requestId,
            'actor_id' => $admin->id,
            'event' => 'vehicle_updated',
            'module' => 'vehicle',
            'http_method' => 'PATCH',
            'ip_address' => '10.20.30.40',
        ]);

        Carbon::setTestNow('2026-08-07 16:00:00 UTC');
        $this->auditLog([
            'actor_id' => $other->id,
            'event' => 'item_updated',
            'module' => 'inventory',
            'http_method' => 'PUT',
        ]);

        $response = $this->actingAs($admin)->get(route('audit-logs.index', [
            'q' => '10.20.30.40',
            'actor' => $admin->id,
            'module' => 'vehicle',
            'event' => 'vehicle_updated',
            'method' => 'PATCH',
            'request_id' => $requestId,
            'from' => '2026-08-09',
            'until' => '2026-08-09',
            'per_page' => 30,
        ]));

        $response
            ->assertOk()
            ->assertSee(route('audit-logs.show', $expected), false)
            ->assertSee('Kendaraan diperbarui')
            ->assertDontSee('Barang diperbarui');
    }

    public function test_soft_deleted_actor_remains_traceable(): void
    {
        $admin = $this->admin();
        $archivedActor = $this->admin([
            'name' => 'Petugas Audit Diarsipkan',
            'employee_number' => 'ADM-ARSIP-001',
        ]);
        $auditLog = $this->auditLog(['actor_id' => $archivedActor->id]);
        $archivedActor->delete();

        $this->actingAs($admin)
            ->get(route('audit-logs.index'))
            ->assertOk()
            ->assertSee('Petugas Audit Diarsipkan')
            ->assertSee('Akun diarsipkan');

        $this->actingAs($admin)
            ->get(route('audit-logs.show', $auditLog))
            ->assertOk()
            ->assertSee('Petugas Audit Diarsipkan')
            ->assertSee('Akun telah diarsipkan.');
    }

    public function test_detail_defensively_redacts_legacy_secrets_and_escapes_html(): void
    {
        $admin = $this->admin();
        $auditLog = $this->auditLog([
            'url' => 'https://simantap.test/reset-kata-sandi/token-rahasia?email=pegawai%40example.test&api_key=kunci-rahasia',
            'old_values' => [
                'password' => 'kata-sandi-rahasia',
                'profile' => ['api_token' => 'token-lama'],
            ],
            'new_values' => [
                'notes' => '<script>alert("audit-xss")</script>',
                'signature_data' => 'data:image/png;base64,rahasia',
                'transaction_hash' => 'hash-transaksi-aman',
            ],
        ]);

        $this->actingAs($admin)
            ->get(route('audit-logs.show', $auditLog))
            ->assertOk()
            ->assertSee('[DISENSOR]')
            ->assertSee('hash-transaksi-aman')
            ->assertSee('&lt;script&gt;alert(&quot;audit-xss&quot;)&lt;/script&gt;', false)
            ->assertSee('/reset-kata-sandi/{credential}', false)
            ->assertDontSee('kata-sandi-rahasia')
            ->assertDontSee('token-lama')
            ->assertDontSee('token-rahasia')
            ->assertDontSee('kunci-rahasia')
            ->assertDontSee('<script>alert("audit-xss")</script>', false);
    }

    public function test_audit_workspace_has_no_mutation_routes(): void
    {
        $admin = $this->admin();
        $auditLog = $this->auditLog();

        $this->actingAs($admin)
            ->put('/audit-log/'.$auditLog->id, ['event' => 'changed'])
            ->assertMethodNotAllowed();

        $this->actingAs($admin)
            ->delete('/audit-log/'.$auditLog->id)
            ->assertMethodNotAllowed();

        $this->assertDatabaseHas('audit_logs', [
            'id' => $auditLog->id,
            'event' => $auditLog->event,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function admin(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole(RoleName::Administrator->value);

        return $user;
    }

    private function employee(): User
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Employee->value);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function auditLog(array $attributes = []): AuditLog
    {
        return AuditLog::query()->create([
            'request_id' => (string) Str::uuid(),
            'actor_id' => null,
            'event' => 'system_check_completed',
            'module' => 'system',
            'auditable_type' => null,
            'auditable_id' => null,
            'old_values' => null,
            'new_values' => null,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'SIMANTAP Audit Workspace Test',
            'url' => 'https://simantap.test/audit-test',
            'http_method' => 'GET',
            ...$attributes,
        ]);
    }
}
