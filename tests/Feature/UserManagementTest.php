<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\RoleName;
use App\Exports\EmployeeImportTemplateExport;
use App\Models\AccountActivationToken;
use App\Models\User;
use App\Notifications\ActivateAccountNotification;
use App\Notifications\ResetPasswordNotification;
use App\Services\AccountActivationService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_admin_can_view_search_and_filter_employee_list(): void
    {
        $admin = $this->admin();
        $visibleEmployee = $this->employee([
            'name' => 'Andi Statistik',
            'email' => 'andi@example.test',
            'work_unit' => 'Statistik Sosial',
            'status' => AccountStatus::Active,
        ]);
        $this->employee([
            'name' => 'Budi Umum',
            'email' => 'budi@example.test',
            'work_unit' => 'Umum',
            'status' => AccountStatus::Suspended,
        ]);

        $this->actingAs($admin)
            ->get(route('users.index', [
                'q' => 'Andi',
                'status' => AccountStatus::Active->value,
                'work_unit' => 'Statistik Sosial',
            ]))
            ->assertOk()
            ->assertSee('Manajemen Pengguna')
            ->assertSee($visibleEmployee->name)
            ->assertDontSee('Budi Umum')
            ->assertDontSee($admin->email);
    }

    public function test_employee_cannot_access_user_management_routes(): void
    {
        $employee = $this->employee();

        $this->actingAs($employee)
            ->get(route('users.index'))
            ->assertForbidden();

        $this->actingAs($employee)
            ->get(route('users.create'))
            ->assertForbidden();

        $this->actingAs($employee)
            ->get(route('users.import'))
            ->assertForbidden();
    }

    public function test_admin_can_create_employee_and_send_activation(): void
    {
        Notification::fake();

        $admin = $this->admin();

        $response = $this->actingAs($admin)
            ->post(route('users.store'), [
                'employee_number' => 'PEG-010',
                'name' => 'Pegawai Baru',
                'email' => 'PEGAWAI.BARU@EXAMPLE.TEST',
                'phone' => '0812-3456-7890',
                'work_unit' => 'Statistik Produksi',
                'position' => 'Statistisi',
            ]);

        $employee = User::query()
            ->where('employee_number', 'PEG-010')
            ->firstOrFail();

        $response->assertRedirect(route('users.show', $employee));

        $this->assertSame(
            AccountStatus::PendingActivation,
            $employee->status,
        );
        $this->assertNull($employee->password);
        $this->assertSame(
            'pegawai.baru@example.test',
            $employee->email,
        );
        $this->assertSame('081234567890', $employee->phone);
        $this->assertTrue(
            $employee->hasRole(RoleName::Employee->value),
        );
        $this->assertSame($admin->id, $employee->created_by);

        Notification::assertSentTo(
            $employee,
            ActivateAccountNotification::class,
        );

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'event' => 'employee_account_created',
            'module' => 'user_management',
            'auditable_type' => 'user',
            'auditable_id' => $employee->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'event' => 'account_activation_link_sent',
            'module' => 'user_management',
            'auditable_id' => $employee->id,
        ]);
    }

    public function test_create_employee_validates_unique_identity(): void
    {
        $admin = $this->admin();
        $employee = $this->employee([
            'employee_number' => 'PEG-011',
            'email' => 'existing@example.test',
        ]);

        $this->actingAs($admin)
            ->from(route('users.create'))
            ->post(route('users.store'), [
                'employee_number' => $employee->employee_number,
                'name' => 'Duplikat',
                'email' => $employee->email,
                'phone' => '123',
                'work_unit' => '',
                'position' => '',
            ])
            ->assertRedirect(route('users.create'))
            ->assertSessionHasErrors([
                'employee_number',
                'email',
                'phone',
                'work_unit',
                'position',
            ]);
    }

    public function test_admin_can_update_employee_without_changing_role_or_status(): void
    {
        $admin = $this->admin();
        $employee = $this->employee([
            'name' => 'Nama Lama',
            'status' => AccountStatus::Active,
        ]);

        $this->actingAs($admin)
            ->put(route('users.update', $employee), [
                'employee_number' => 'PEG-UPDATED',
                'name' => 'Nama Diperbarui',
                'email' => 'updated@example.test',
                'phone' => '+62 812 3456 7890',
                'work_unit' => 'Neraca Wilayah',
                'position' => 'Pranata Komputer',
                'status' => AccountStatus::Suspended->value,
                'role' => RoleName::Administrator->value,
            ])
            ->assertRedirect(route('users.show', $employee));

        $employee->refresh();

        $this->assertSame('Nama Diperbarui', $employee->name);
        $this->assertSame(
            AccountStatus::Active,
            $employee->status,
        );
        $this->assertTrue(
            $employee->hasRole(RoleName::Employee->value),
        );
        $this->assertFalse(
            $employee->hasRole(RoleName::Administrator->value),
        );
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'event' => 'employee_account_updated',
            'module' => 'user_management',
            'auditable_id' => $employee->id,
        ]);
    }

    public function test_admin_is_prompted_to_complete_incomplete_employee_data(): void
    {
        $admin = $this->admin();
        $employee = $this->employee([
            'work_unit' => 'BPS Kabupaten Jombang',
            'position' => null,
        ]);

        $this->actingAs($admin)
            ->get(route('users.show', $employee))
            ->assertOk()
            ->assertSee('Data kepegawaian belum lengkap')
            ->assertSee('Lengkapi Data Kepegawaian')
            ->assertSee('Jabatan belum diisi');
    }

    public function test_admin_account_cannot_be_managed_from_employee_routes(): void
    {
        $admin = $this->admin();
        $otherAdmin = $this->admin([
            'email' => 'other.admin@example.test',
        ]);

        $this->actingAs($admin)
            ->get(route('users.show', $otherAdmin))
            ->assertNotFound();

        $this->actingAs($admin)
            ->put(route('users.update', $otherAdmin), [
                'employee_number' => 'CHANGED',
                'name' => 'Tidak Boleh',
                'email' => 'changed@example.test',
                'phone' => '081234567890',
                'work_unit' => 'Lain',
                'position' => 'Lain',
            ])
            ->assertNotFound();
    }

    public function test_admin_can_resend_activation_and_old_token_is_invalidated(): void
    {
        Notification::fake();

        $admin = $this->admin();
        $employee = $this->pendingEmployee();
        $firstToken = app(
            AccountActivationService::class,
        )->issueToken($employee, $admin);
        $firstHash = hash('sha256', $firstToken);

        $this->actingAs($admin)
            ->post(route('users.activation.resend', $employee))
            ->assertRedirect();

        $storedToken = AccountActivationToken::query()
            ->where('user_id', $employee->id)
            ->firstOrFail();

        $this->assertNotSame(
            $firstHash,
            $storedToken->getRawOriginal('token_hash'),
        );
        Notification::assertSentTo(
            $employee,
            ActivateAccountNotification::class,
        );
    }

    public function test_admin_can_suspend_and_reactivate_employee(): void
    {
        $admin = $this->admin();
        $employee = $this->employee();

        config()->set('session.driver', 'database');

        DB::table('sessions')->insert([
            'id' => 'employee-session',
            'user_id' => $employee->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => 'payload',
            'last_activity' => now()->timestamp,
        ]);

        $this->actingAs($admin)
            ->patch(route('users.suspend', $employee))
            ->assertRedirect();

        $this->assertSame(
            AccountStatus::Suspended,
            $employee->fresh()->status,
        );
        $this->assertDatabaseMissing('sessions', [
            'id' => 'employee-session',
        ]);

        $this->actingAs($admin)
            ->patch(route('users.reactivate', $employee))
            ->assertRedirect();

        $this->assertSame(
            AccountStatus::Active,
            $employee->fresh()->status,
        );
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'employee_account_suspended',
            'auditable_id' => $employee->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'employee_account_reactivated',
            'auditable_id' => $employee->id,
        ]);
    }

    public function test_admin_can_send_password_reset_only_to_active_employee(): void
    {
        Notification::fake();

        $admin = $this->admin();
        $activeEmployee = $this->employee();
        $pendingEmployee = $this->pendingEmployee([
            'email' => 'pending.reset@example.test',
        ]);

        $this->actingAs($admin)
            ->post(
                route(
                    'users.password-reset.send',
                    $activeEmployee,
                ),
            )
            ->assertRedirect();

        Notification::assertSentTo(
            $activeEmployee,
            ResetPasswordNotification::class,
        );

        $this->actingAs($admin)
            ->post(
                route(
                    'users.password-reset.send',
                    $pendingEmployee,
                ),
            )
            ->assertSessionHasErrors('user');
    }

    public function test_admin_can_download_employee_import_template(): void
    {
        Excel::fake();

        $this->actingAs($this->admin())
            ->get(route('users.import.template'))
            ->assertOk();

        Excel::matchByRegex();
        Excel::assertDownloaded(
            '/template-impor-pegawai-simantap\.xlsx/',
            static fn (
                EmployeeImportTemplateExport $export,
            ): bool => $export->headings() === [
                'nip',
                'nama_lengkap',
                'email',
                'nomor_telepon',
                'unit_kerja',
                'jabatan',
            ],
        );
    }

    public function test_admin_can_import_valid_employee_file(): void
    {
        Notification::fake();

        $admin = $this->admin();
        $file = $this->employeeSpreadsheet([
            [
                'PEG-020',
                'Pegawai Impor Satu',
                'impor.satu@example.test',
                '081234567890',
                'Umum',
                'Pengelola',
            ],
            [
                'PEG-021',
                'Pegawai Impor Dua',
                'impor.dua@example.test',
                '',
                'Statistik Sosial',
                'Statistisi',
            ],
        ]);

        $this->actingAs($admin)
            ->post(route('users.import.store'), [
                'employee_file' => $file,
            ])
            ->assertRedirect(route('users.index'))
            ->assertSessionHas(
                'status',
                '2 akun pegawai berhasil diimpor dan tautan aktivasi telah dikirim.',
            );

        $importedUsers = User::query()
            ->whereIn(
                'employee_number',
                ['PEG-020', 'PEG-021'],
            )
            ->get();

        $this->assertCount(2, $importedUsers);

        foreach ($importedUsers as $importedUser) {
            $this->assertSame(
                AccountStatus::PendingActivation,
                $importedUser->status,
            );
            $this->assertTrue(
                $importedUser->hasRole(
                    RoleName::Employee->value,
                ),
            );
            Notification::assertSentTo(
                $importedUser,
                ActivateAccountNotification::class,
            );
        }
    }

    public function test_invalid_import_is_rejected_without_partial_accounts(): void
    {
        Notification::fake();

        $admin = $this->admin();
        $file = $this->employeeSpreadsheet([
            [
                'PEG-030',
                'Baris Valid',
                'valid@example.test',
                '081234567890',
                'Umum',
                'Pegawai',
            ],
            [
                'PEG-030',
                '',
                'email-tidak-valid',
                '123',
                '',
                '',
            ],
        ]);

        $this->actingAs($admin)
            ->from(route('users.import'))
            ->post(route('users.import.store'), [
                'employee_file' => $file,
            ])
            ->assertRedirect(route('users.import'))
            ->assertSessionHasErrors();

        $this->assertDatabaseMissing('users', [
            'employee_number' => 'PEG-030',
        ]);
        Notification::assertNothingSent();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function admin(array $attributes = []): User
    {
        $admin = User::factory()->create([
            'status' => AccountStatus::Active,
            'must_change_password' => false,
            ...$attributes,
        ]);

        $admin->assignRole(RoleName::Administrator->value);

        return $admin;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function employee(array $attributes = []): User
    {
        $employee = User::factory()->create([
            'status' => AccountStatus::Active,
            'must_change_password' => false,
            ...$attributes,
        ]);

        $employee->assignRole(RoleName::Employee->value);

        return $employee;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function pendingEmployee(array $attributes = []): User
    {
        $employee = User::factory()->create([
            'status' => AccountStatus::PendingActivation,
            'password' => null,
            'must_change_password' => false,
            'email_verified_at' => null,
            'activated_at' => null,
            'password_changed_at' => null,
            ...$attributes,
        ]);

        $employee->assignRole(RoleName::Employee->value);

        return $employee;
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function employeeSpreadsheet(array $rows): UploadedFile
    {
        $contents = Excel::raw(
            new class($rows) implements FromArray, WithHeadings
            {
                /**
                 * @param  list<list<string>>  $rows
                 */
                public function __construct(
                    private readonly array $rows,
                ) {}

                /**
                 * @return list<list<string>>
                 */
                public function array(): array
                {
                    return $this->rows;
                }

                /**
                 * @return list<string>
                 */
                public function headings(): array
                {
                    return [
                        'nip',
                        'nama_lengkap',
                        'email',
                        'nomor_telepon',
                        'unit_kerja',
                        'jabatan',
                    ];
                }
            },
            ExcelWriter::XLSX,
        );

        return UploadedFile::fake()->createWithContent(
            'pegawai.xlsx',
            $contents,
        );
    }
}
