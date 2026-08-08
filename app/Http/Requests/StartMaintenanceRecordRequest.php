<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StartMaintenanceRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        $record = $this->route('maintenanceRecord');

        return $record !== null
            && ($this->user()?->can('start', $record) ?? false);
    }

    public function rules(): array
    {
        return [
            'start_date' => ['required', 'date'],
            'service_provider' => ['nullable', 'string', 'max:255'],
        ];
    }
}
