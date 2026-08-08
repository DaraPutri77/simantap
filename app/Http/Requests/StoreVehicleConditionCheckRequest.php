<?php

namespace App\Http\Requests;

use App\Enums\VehicleOverallCondition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVehicleConditionCheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $imageRules = [
            'required',
            'file',
            'image',
            'mimetypes:image/jpeg,image/png,image/webp',
            'extensions:jpg,jpeg,png,webp',
            'max:'.(int) config(
                'simantap.uploads.evidence_max_size_kb',
                5120,
            ),
        ];

        return [
            'odometer' => ['required', 'numeric', 'min:0'],
            'fuel_level' => ['required', 'integer', 'between:0,100'],
            'overall_condition' => [
                'required',
                Rule::enum(VehicleOverallCondition::class),
            ],
            'body_condition' => ['required', 'string', 'max:2000'],
            'engine_condition' => ['required', 'string', 'max:2000'],
            'tire_condition' => ['required', 'string', 'max:2000'],
            'equipment_condition' => ['required', 'string', 'max:2000'],
            'damage_notes' => [
                Rule::requiredIf(
                    $this->input('overall_condition')
                        !== VehicleOverallCondition::Good->value,
                ),
                'nullable',
                'string',
                'max:4000',
            ],
            'photo_front' => $imageRules,
            'photo_back' => $imageRules,
            'photo_left' => $imageRules,
            'photo_right' => $imageRules,
            'photo_odometer' => $imageRules,
            'photo_fuel' => $imageRules,
            'photo_damage' => [
                Rule::requiredIf(
                    $this->input('overall_condition')
                        !== VehicleOverallCondition::Good->value,
                ),
                'nullable',
                'file',
                'image',
                'mimetypes:image/jpeg,image/png,image/webp',
                'extensions:jpg,jpeg,png,webp',
                'max:'.(int) config(
                    'simantap.uploads.evidence_max_size_kb',
                    5120,
                ),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fuel_level.between' => 'Tingkat bahan bakar harus antara 0 sampai 100 persen.',
            'photo_front.required' => 'Foto kendaraan tampak depan wajib diunggah.',
            'photo_back.required' => 'Foto kendaraan tampak belakang wajib diunggah.',
            'photo_left.required' => 'Foto kendaraan sisi kiri wajib diunggah.',
            'photo_right.required' => 'Foto kendaraan sisi kanan wajib diunggah.',
            'photo_odometer.required' => 'Foto odometer wajib diunggah.',
            'photo_fuel.required' => 'Foto indikator bahan bakar wajib diunggah.',
            'photo_damage.required' => 'Foto kerusakan wajib diunggah ketika kondisi tidak baik.',
            'damage_notes.required' => 'Catatan kerusakan wajib diisi ketika kondisi tidak baik.',
        ];
    }
}
