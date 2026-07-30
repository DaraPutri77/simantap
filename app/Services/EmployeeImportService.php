<?php

namespace App\Services;

use App\Imports\EmployeeRowsImport;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class EmployeeImportService
{
    public function __construct(
        private readonly AccountService $accountService,
    ) {}

    public function import(
        UploadedFile $file,
        User $creator,
        ?Request $request = null,
    ): int {
        $import = new EmployeeRowsImport;

        Excel::import($import, $file);

        $records = $this->records($import->rows);

        $validator = Validator::make(
            ['rows' => $records->all()],
            $this->rules(),
            $this->messages(),
            $this->attributes($records),
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        /** @var Collection<int, User> $users */
        $users = collect();

        DB::transaction(function () use (
            $records,
            $creator,
            $request,
            &$users,
        ): void {
            $users = $records->map(
                fn (array $record): User => $this->accountService
                    ->createEmployee(
                        $record,
                        $creator,
                        $request,
                        false,
                    ),
            );
        }, 3);

        $users->each(
            fn (User $user) => $this->accountService
                ->resendActivation(
                    $user,
                    $creator,
                    $request,
                ),
        );

        return $users->count();
    }

    /**
     * @param  Collection<int, Collection<string, mixed>>  $rows
     * @return Collection<int, array<string, string|null>>
     */
    private function records(Collection $rows): Collection
    {
        if ($rows->isEmpty()) {
            throw ValidationException::withMessages([
                'employee_file' => 'File tidak berisi data pegawai.',
            ]);
        }

        $requiredHeadings = [
            'nip',
            'nama_lengkap',
            'email',
            'nomor_telepon',
            'unit_kerja',
            'jabatan',
        ];

        $availableHeadings = array_keys(
            $rows->first()?->all() ?? [],
        );
        $missingHeadings = array_diff(
            $requiredHeadings,
            $availableHeadings,
        );

        if ($missingHeadings !== []) {
            throw ValidationException::withMessages([
                'employee_file' => 'Kolom file tidak lengkap: '
                    .implode(', ', $missingHeadings)
                    .'. Gunakan template dari SIMANTAP.',
            ]);
        }

        return $rows->values()->map(
            function (Collection $row): array {
                $phone = $this->nullableString(
                    $row->get('nomor_telepon'),
                );

                return [
                    'employee_number' => trim(
                        (string) $row->get('nip'),
                    ),
                    'name' => trim(
                        (string) $row->get('nama_lengkap'),
                    ),
                    'email' => mb_strtolower(
                        trim((string) $row->get('email')),
                    ),
                    'phone' => $phone === null
                        ? null
                        : preg_replace(
                            '/[\s().-]+/',
                            '',
                            $phone,
                        ),
                    'work_unit' => trim(
                        (string) $row->get('unit_kerja'),
                    ),
                    'position' => trim(
                        (string) $row->get('jabatan'),
                    ),
                ];
            },
        );
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function rules(): array
    {
        return [
            'rows' => [
                'required',
                'array',
                'min:1',
                'max:200',
            ],
            'rows.*.employee_number' => [
                'required',
                'string',
                'max:50',
                'distinct',
                Rule::unique(User::class, 'employee_number'),
            ],
            'rows.*.name' => [
                'required',
                'string',
                'max:255',
            ],
            'rows.*.email' => [
                'required',
                'string',
                'email:rfc',
                'max:255',
                'distinct',
                Rule::unique(User::class, 'email'),
            ],
            'rows.*.phone' => [
                'nullable',
                'string',
                'max:30',
                'regex:/^(?:\+62|62|0)[0-9]{8,13}$/',
            ],
            'rows.*.work_unit' => [
                'required',
                'string',
                'max:255',
            ],
            'rows.*.position' => [
                'required',
                'string',
                'max:255',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'rows.max' => 'Satu file maksimal berisi 200 pegawai.',
            'rows.*.employee_number.required' => ':attribute wajib diisi.',
            'rows.*.employee_number.max' => ':attribute maksimal 50 karakter.',
            'rows.*.employee_number.distinct' => ':attribute berulang di dalam file.',
            'rows.*.employee_number.unique' => ':attribute sudah terdaftar.',
            'rows.*.name.required' => ':attribute wajib diisi.',
            'rows.*.name.max' => ':attribute maksimal 255 karakter.',
            'rows.*.email.required' => ':attribute wajib diisi.',
            'rows.*.email.email' => ':attribute tidak valid.',
            'rows.*.email.max' => ':attribute maksimal 255 karakter.',
            'rows.*.email.distinct' => ':attribute berulang di dalam file.',
            'rows.*.email.unique' => ':attribute sudah digunakan akun lain.',
            'rows.*.phone.max' => ':attribute maksimal 30 karakter.',
            'rows.*.phone.regex' => ':attribute bukan nomor Indonesia yang valid.',
            'rows.*.work_unit.required' => ':attribute wajib diisi.',
            'rows.*.work_unit.max' => ':attribute maksimal 255 karakter.',
            'rows.*.position.required' => ':attribute wajib diisi.',
            'rows.*.position.max' => ':attribute maksimal 255 karakter.',
        ];
    }

    /**
     * @param  Collection<int, array<string, string|null>>  $records
     * @return array<string, string>
     */
    private function attributes(Collection $records): array
    {
        $attributes = [];

        foreach ($records->keys() as $index) {
            $rowNumber = $index + 2;

            $attributes["rows.{$index}.employee_number"]
                = "NIP pada baris {$rowNumber}";
            $attributes["rows.{$index}.name"]
                = "nama lengkap pada baris {$rowNumber}";
            $attributes["rows.{$index}.email"]
                = "email pada baris {$rowNumber}";
            $attributes["rows.{$index}.phone"]
                = "nomor telepon pada baris {$rowNumber}";
            $attributes["rows.{$index}.work_unit"]
                = "unit kerja pada baris {$rowNumber}";
            $attributes["rows.{$index}.position"]
                = "jabatan pada baris {$rowNumber}";
        }

        return $attributes;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }
}
