<?php

namespace App\Http\Requests;

use App\Enums\MaintenanceSubjectType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMaintenanceRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('maintenance.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (
            ! $this->filled('subject_type')
            && $this->filled('vehicle_public_id')
        ) {
            $this->merge([
                'subject_type' => MaintenanceSubjectType::Vehicle->value,
            ]);
        }
    }

    public function rules(): array
    {
        $maxSize = (int) config('simantap.uploads.evidence_max_size_kb', 5120);

        return [
            'subject_type' => [
                'required',
                Rule::in(MaintenanceSubjectType::values()),
            ],
            'vehicle_public_id' => [
                'required_if:subject_type,'.MaintenanceSubjectType::Vehicle->value,
                'prohibited_unless:subject_type,'.MaintenanceSubjectType::Vehicle->value,
                'nullable',
                'uuid',
                'exists:vehicles,public_id',
            ],
            'operational_asset_public_id' => [
                'required_if:subject_type,'.MaintenanceSubjectType::OperationalAsset->value,
                'prohibited_unless:subject_type,'.MaintenanceSubjectType::OperationalAsset->value,
                'nullable',
                'uuid',
                'exists:operational_assets,public_id',
            ],
            'source_vehicle_loan_public_id' => [
                'prohibited_unless:subject_type,'.MaintenanceSubjectType::Vehicle->value,
                'nullable',
                'uuid',
                'exists:vehicle_loans,public_id',
            ],
            'maintenance_type' => ['required', 'string', 'max:100'],
            'complaint' => ['required', 'string', 'max:5000'],
            // Menjaga initial_condition (wajib) karena struktur DB, namun label di UI adalah "Pelaksana/Penyedia"
            'initial_condition' => ['required', 'string', 'max:5000'], 
            'cost' => ['nullable', 'numeric', 'min:0'], // Tambahan untuk biaya
            'reported_date' => ['required', 'date'],
            'photo_before' => [
                'nullable', // Mengubah menjadi opsional (tidak wajib)
                'file',
                'mimetypes:image/jpeg,image/png,image/webp',
                "max:{$maxSize}",
            ],
            'supporting_document' => [
                'nullable',
                'file',
                'mimetypes:application/pdf,image/jpeg,image/png,image/webp',
                "max:{$maxSize}",
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'subject_type' => 'jenis subjek pemeliharaan',
            'vehicle_public_id' => 'kendaraan',
            'operational_asset_public_id' => 'aset perangkat',
            'source_vehicle_loan_public_id' => 'sumber masalah pengembalian',
            'maintenance_type' => 'jenis pemeliharaan',
            'complaint' => 'jenis / uraian', // Ubah nama atribut untuk pesan error
            'initial_condition' => 'pelaksana / penyedia', // Ubah nama atribut untuk pesan error
            'cost' => 'biaya pemeliharaan', // Tambahan atribut biaya
            'reported_date' => 'tanggal laporan',
            'photo_before' => 'foto sebelum pemeliharaan',
            'supporting_document' => 'dokumen pendukung',
        ];
    }
}