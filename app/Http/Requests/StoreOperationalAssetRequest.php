<?php

namespace App\Http\Requests;

use App\Enums\OperationalAssetStatus;
use App\Enums\OperationalAssetType;
use App\Enums\PermissionName;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOperationalAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            PermissionName::MaintenanceManage->value,
        ) === true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge($this->normalizedInput());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maximumYear = CarbonImmutable::now(
            (string) config('simantap.display_timezone', 'Asia/Jakarta'),
        )->year + 1;
        $bmnCodeRules = [
            'nullable',
            'string',
            'max:50',
            'regex:/^[A-Z0-9._\/-]+$/',
        ];

        if ($this->filled('bmn_code') && $this->filled('nup')) {
            $bmnCodeRules[] = Rule::unique(
                'operational_assets',
                'bmn_code',
            )->where('nup', (string) $this->input('nup'));
        }

        return [
            'asset_code' => [
                'required',
                'string',
                'max:80',
                'regex:/^[A-Z0-9._\/-]+$/',
                Rule::unique('operational_assets', 'asset_code'),
            ],
            'bmn_code' => $bmnCodeRules,
            'nup' => ['nullable', 'string', 'max:30', 'regex:/^[0-9]+$/'],
            'register_code' => [
                'nullable',
                'string',
                'max:100',
                'regex:/^[A-Z0-9._\/-]+$/',
                Rule::unique('operational_assets', 'register_code'),
            ],
            'type' => ['required', Rule::in(OperationalAssetType::values())],
            'brand' => ['required', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'serial_number' => [
                'nullable',
                'string',
                'max:120',
                Rule::unique('operational_assets', 'serial_number'),
            ],
            'acquisition_year' => [
                'nullable',
                'integer',
                'min:1900',
                "max:{$maximumYear}",
            ],
            'location' => ['nullable', 'string', 'max:255'],
            'responsible_person' => ['nullable', 'string', 'max:255'],
            'status' => [
                'required',
                Rule::in(OperationalAssetStatus::manuallyManagedValues()),
            ],
            'notes' => ['nullable', 'string', 'max:3000'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'asset_code' => 'kode aset',
            'bmn_code' => 'kode barang BMN',
            'nup' => 'NUP',
            'register_code' => 'kode register',
            'type' => 'jenis perangkat',
            'brand' => 'merek perangkat',
            'model' => 'tipe/model perangkat',
            'serial_number' => 'nomor seri',
            'acquisition_year' => 'tahun perolehan',
            'location' => 'lokasi ruang',
            'responsible_person' => 'penanggung jawab',
            'status' => 'status operasional',
            'notes' => 'catatan aset',
            'is_active' => 'status master aset',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizedInput(): array
    {
        return [
            'asset_code' => $this->uppercase('asset_code'),
            'bmn_code' => $this->nullableUppercase('bmn_code'),
            'nup' => $this->nullableText('nup'),
            'register_code' => $this->nullableUppercase('register_code'),
            'brand' => trim((string) $this->input('brand')),
            'model' => $this->nullableText('model'),
            'serial_number' => $this->nullableUppercase('serial_number'),
            'acquisition_year' => $this->nullableText('acquisition_year'),
            'location' => $this->nullableText('location'),
            'responsible_person' => $this->nullableText('responsible_person'),
            'notes' => $this->nullableText('notes'),
            'is_active' => $this->boolean('is_active'),
        ];
    }

    private function uppercase(string $key): string
    {
        return mb_strtoupper(trim((string) $this->input($key)));
    }

    private function nullableUppercase(string $key): ?string
    {
        $value = $this->uppercase($key);

        return $value === '' ? null : $value;
    }

    private function nullableText(string $key): ?string
    {
        $value = trim((string) $this->input($key));

        return $value === '' ? null : $value;
    }
}
