<?php

namespace App\Http\Requests;

use App\Enums\MaintenanceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompleteMaintenanceRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        $record = $this->route('maintenanceRecord');

        return $record !== null
            && ($this->user()?->can('complete', $record) ?? false);
    }

    public function rules(): array
    {
        $maxSize = (int) config('simantap.uploads.evidence_max_size_kb', 5120);

        return [
            'outcome_status' => [
                'required',
                Rule::in([
                    MaintenanceStatus::Completed->value,
                    MaintenanceStatus::CompletedWithNotes->value,
                    MaintenanceStatus::FurtherActionRequired->value,
                    MaintenanceStatus::SeverelyDamaged->value,
                    MaintenanceStatus::Unserviceable->value,
                ]),
            ],
            'completion_date' => ['required', 'date', 'after_or_equal:start_date'],
            'cost' => ['nullable', 'numeric', 'min:0', 'max:99999999999999999.99'],
            'result' => ['required', 'string', 'max:10000'],
            'final_condition' => ['required', 'string', 'max:10000'],
            'photo_after' => [
                'required',
                'file',
                'mimetypes:image/jpeg,image/png,image/webp',
                "max:{$maxSize}",
            ],
            'receipt' => [
                'nullable',
                'file',
                'mimetypes:application/pdf,image/jpeg,image/png,image/webp',
                "max:{$maxSize}",
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $record = $this->route('maintenanceRecord');

        if ($record?->start_date !== null) {
            $this->merge([
                'start_date' => $record->start_date->toDateString(),
            ]);
        }
    }
}
