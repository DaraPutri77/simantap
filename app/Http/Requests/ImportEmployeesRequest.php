<?php

namespace App\Http\Requests;

use App\Enums\PermissionName;
use Illuminate\Foundation\Http\FormRequest;

class ImportEmployeesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            PermissionName::UserImport->value,
        ) === true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'employee_file' => [
                'required',
                'file',
                'mimes:xlsx,xls,csv',
                'max:5120',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'employee_file.required' => 'Pilih file data pegawai yang akan diimpor.',
            'employee_file.file' => 'Berkas impor tidak valid.',
            'employee_file.mimes' => 'Gunakan file Excel atau CSV berformat XLSX, XLS, atau CSV.',
            'employee_file.max' => 'Ukuran file impor maksimal 5 MB.',
        ];
    }
}
