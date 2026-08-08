<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApproveMaintenanceRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        $record = $this->route('maintenanceRecord');

        return $record !== null
            && ($this->user()?->can('approve', $record) ?? false);
    }

    public function rules(): array
    {
        return [
            'approval_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
