<?php

namespace Tests\Feature;

use App\Enums\InventoryRequestStatus;
use App\Models\InventoryRequest;
use App\Models\User;
use App\Services\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

class AuditLoggerTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_logger_records_context_and_sanitizes_sensitive_data(): void
    {
        $actor = User::factory()->create([
            'employee_number' => 'ADM-AUDIT-001',
            'work_unit' => 'Bagian Umum',
        ]);

        $inventoryRequest = $this->createInventoryRequest($actor);

        $requestId = (string) Str::uuid();

        $request = Request::create(
            'https://simantap.test/pengaturan?tab=umum',
            'POST',
            [],
            [],
            [],
            [
                'REMOTE_ADDR' => '127.0.0.1',
                'HTTP_USER_AGENT' => 'SIMANTAP Test Agent',
                'HTTP_X_REQUEST_ID' => $requestId,
            ],
        );

        $request->setUserResolver(
            fn (): User => $actor,
        );

        $auditLog = app(AuditLogger::class)->log(
            event: 'inventory_request_updated',
            module: 'inventory_request',
            auditable: $inventoryRequest,
            oldValues: [
                'purpose' => 'Keperluan sebelumnya.',
                'password' => 'rahasia-lama',
            ],
            newValues: [
                'purpose' => 'Keperluan terbaru.',
                'profile' => [
                    'name' => $actor->name,
                    'token' => 'rahasia-token',
                ],
                'signature_data' => 'data:image/png;base64,rahasia',
            ],
            request: $request,
        );

        $auditLog->load([
            'actor',
            'auditable',
        ]);

        $this->assertSame($requestId, $auditLog->request_id);
        $this->assertSame($actor->id, $auditLog->actor_id);
        $this->assertSame(
            'inventory_request_updated',
            $auditLog->event,
        );
        $this->assertSame(
            'inventory_request',
            $auditLog->module,
        );
        $this->assertSame(
            ['purpose' => 'Keperluan sebelumnya.'],
            $auditLog->old_values,
        );
        $this->assertSame([
            'purpose' => 'Keperluan terbaru.',
            'profile' => [
                'name' => $actor->name,
            ],
        ], $auditLog->new_values);
        $this->assertSame('127.0.0.1', $auditLog->ip_address);
        $this->assertSame(
            'SIMANTAP Test Agent',
            $auditLog->user_agent,
        );
        $this->assertSame(
            'https://simantap.test/pengaturan?tab=umum',
            $auditLog->url,
        );
        $this->assertSame('POST', $auditLog->http_method);
        $this->assertInstanceOf(
            CarbonImmutable::class,
            $auditLog->created_at,
        );
        $this->assertTrue($auditLog->actor->is($actor));
        $this->assertTrue(
            $auditLog->auditable->is($inventoryRequest),
        );

        $this->assertModelCannotBeChanged(
            $auditLog,
            ['event' => 'audit_log_tidak_boleh_diubah'],
        );
    }

    public function test_generated_request_id_is_reused_within_same_request(): void
    {
        $request = Request::create(
            'https://simantap.test/system',
            'GET',
        );

        $logger = app(AuditLogger::class);

        $firstAuditLog = $logger->log(
            event: 'system_check_started',
            module: 'system',
            request: $request,
        );

        $secondAuditLog = $logger->log(
            event: 'system_check_completed',
            module: 'system',
            request: $request,
        );

        $this->assertTrue(
            Str::isUuid($firstAuditLog->request_id),
        );
        $this->assertSame(
            $firstAuditLog->request_id,
            $secondAuditLog->request_id,
        );
        $this->assertNull($firstAuditLog->actor_id);
        $this->assertNull($firstAuditLog->auditable_type);
        $this->assertNull($firstAuditLog->auditable_id);
    }

    public function test_audit_logger_removes_nested_credentials_and_url_tokens(): void
    {
        $request = Request::create(
            'https://simantap.test/reset-kata-sandi/token-super-rahasia?email=pegawai%40example.test&api_key=kunci-rahasia',
            'POST',
        );

        $auditLog = app(AuditLogger::class)->log(
            event: 'password_reset_completed',
            module: 'authentication',
            newValues: [
                'profile' => [
                    'api_token' => 'token-payload',
                    'name' => 'Pegawai Aman',
                ],
                'transaction_hash' => 'hash-transaksi-boleh-disimpan',
            ],
            request: $request,
        );

        $this->assertSame([
            'profile' => ['name' => 'Pegawai Aman'],
            'transaction_hash' => 'hash-transaksi-boleh-disimpan',
        ], $auditLog->new_values);
        $this->assertSame(
            'https://simantap.test/reset-kata-sandi/{credential}?email=pegawai%40example.test',
            $auditLog->url,
        );
        $this->assertStringNotContainsString(
            'token-super-rahasia',
            $auditLog->url,
        );
        $this->assertStringNotContainsString(
            'kunci-rahasia',
            $auditLog->url,
        );
    }

    private function createInventoryRequest(
        User $requester,
    ): InventoryRequest {
        return InventoryRequest::query()->create([
            'request_number' => 'REQ/2026/07/0001',
            'requested_by' => $requester->id,
            'employee_number_snapshot' => $requester->employee_number,
            'requester_name_snapshot' => $requester->name,
            'work_unit_snapshot' => $requester->work_unit,
            'request_date' => now(),
            'purpose' => 'Keperluan sebelumnya.',
            'notes' => null,
            'status' => InventoryRequestStatus::Draft,
            'submitted_at' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'approved_at' => null,
            'rejected_at' => null,
            'rejection_reason' => null,
            'revision_note' => null,
            'delivered_by' => null,
            'delivered_at' => null,
            'received_at' => null,
            'completed_at' => null,
            'cancelled_at' => null,
            'expired_at' => null,
            'admin_notes' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function assertModelCannotBeChanged(
        Model $model,
        array $changes,
    ): void {
        try {
            $model->update($changes);

            $this->fail('Model immutable masih dapat diubah.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString(
                'tidak boleh diubah',
                $exception->getMessage(),
            );
        }

        $model->refresh();

        foreach ($changes as $attribute => $value) {
            $this->assertNotEquals(
                $value,
                $model->getAttribute($attribute),
            );
        }

        try {
            $model->delete();

            $this->fail('Model immutable masih dapat dihapus.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString(
                'tidak boleh dihapus',
                $exception->getMessage(),
            );
        }

        $this->assertDatabaseHas($model->getTable(), [
            $model->getKeyName() => $model->getKey(),
        ]);
    }
}
