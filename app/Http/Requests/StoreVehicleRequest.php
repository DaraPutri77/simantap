<?php

namespace App\Http\Requests;

use App\Enums\PermissionName;
use App\Enums\VehicleStatus;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            PermissionName::VehicleManage->value,
        ) === true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'vehicle_code' => $this->uppercase('vehicle_code'),
            'license_plate' => $this->uppercase('license_plate'),
            'brand' => trim((string) $this->input('brand')),
            'model' => trim((string) $this->input('model')),
            'color' => $this->nullableText('color'),
            'chassis_number' => $this->nullableUppercase(
                'chassis_number',
            ),
            'engine_number' => $this->nullableUppercase(
                'engine_number',
            ),
            'year' => $this->nullableText('year'),
            'registration_expiry_date' => $this->nullableText(
                'registration_expiry_date',
            ),
            'storage_location' => $this->nullableText(
                'storage_location',
            ),
            'responsible_person' => $this->nullableText(
                'responsible_person',
            ),
            'notes' => $this->nullableText('notes'),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maximumYear = CarbonImmutable::now(
            (string) config(
                'simantap.display_timezone',
                'Asia/Jakarta',
            ),
        )->year + 1;

        return [
            'vehicle_code' => [
                'required',
                'string',
                'max:80',
                'regex:/^[A-Z0-9._\/-]+$/',
                Rule::unique('vehicles', 'vehicle_code'),
            ],
            'license_plate' => [
                'required',
                'string',
                'max:30',
                'regex:/^[A-Z0-9][A-Z0-9 .\/-]*$/',
                Rule::unique('vehicles', 'license_plate'),
            ],
            'brand' => ['required', 'string', 'max:255'],
            'model' => ['required', 'string', 'max:255'],
            'year' => [
                'nullable',
                'integer',
                'min:1900',
                "max:{$maximumYear}",
            ],
            'color' => ['nullable', 'string', 'max:80'],
            'chassis_number' => [
                'nullable',
                'string',
                'max:120',
                Rule::unique('vehicles', 'chassis_number'),
            ],
            'engine_number' => [
                'nullable',
                'string',
                'max:120',
                Rule::unique('vehicles', 'engine_number'),
            ],
            'current_odometer' => [
                'required',
                'numeric',
                'min:0',
                'max:99999999999.9',
                'decimal:0,1',
            ],
            'status' => [
                'required',
                Rule::in(VehicleStatus::manuallyManagedValues()),
            ],
            'registration_expiry_date' => [
                'nullable',
                'date_format:Y-m-d',
            ],
            'storage_location' => [
                'nullable',
                'string',
                'max:255',
            ],
            'responsible_person' => [
                'nullable',
                'string',
                'max:255',
            ],
            'image' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:3072',
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
            'vehicle_code' => 'kode kendaraan',
            'license_plate' => 'nomor polisi',
            'brand' => 'merek kendaraan',
            'model' => 'tipe/model kendaraan',
            'year' => 'tahun kendaraan',
            'color' => 'warna kendaraan',
            'chassis_number' => 'nomor rangka',
            'engine_number' => 'nomor mesin',
            'current_odometer' => 'odometer saat ini',
            'status' => 'status operasional',
            'registration_expiry_date' => 'masa berlaku STNK',
            'storage_location' => 'lokasi penyimpanan',
            'responsible_person' => 'penanggung jawab',
            'image' => 'foto kendaraan',
            'notes' => 'catatan kendaraan',
            'is_active' => 'status master kendaraan',
        ];
    }

    private function uppercase(string $key): string
    {
        return mb_strtoupper(
            trim((string) $this->input($key)),
        );
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
