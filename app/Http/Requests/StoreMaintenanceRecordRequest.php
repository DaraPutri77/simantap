<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMaintenanceRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('maintenance.manage') ?? false;
    }

    public function rules(): array
    {
        $maxSize = (int) config('simantap.uploads.evidence_max_size_kb', 5120);

        return [
            'vehicle_public_id' => ['required', 'uuid', 'exists:vehicles,public_id'],
            'source_vehicle_loan_public_id' => [
                'nullable',
                'uuid',
                'exists:vehicle_loans,public_id',
            ],
            'maintenance_type' => ['required', 'string', 'max:100'],
            'complaint' => ['required', 'string', 'max:5000'],
            'initial_condition' => ['required', 'string', 'max:5000'],
            'reported_date' => ['required', 'date'],
            'photo_before' => [
                'required',
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
}
