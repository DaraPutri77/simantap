<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Throwable;

class StoreVehicleLoanRequest extends FormRequest
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
        return [
            'vehicle_id' => [
                'required',
                'integer',
                Rule::exists('vehicles', 'id')->where(
                    static fn ($query) => $query
                        ->where('is_active', true)
                        ->whereNull('deleted_at'),
                ),
            ],
            'planned_start_at' => [
                'required',
                'date_format:Y-m-d\TH:i',
            ],
            'planned_end_at' => [
                'required',
                'date_format:Y-m-d\TH:i',
            ],
            'purpose' => ['required', 'string', 'min:10', 'max:2000'],
            'destination' => ['required', 'string', 'min:3', 'max:255'],
            'reason' => ['nullable', 'string', 'max:2000'],
            // Menambahkan validasi foto agar hanya menerima gambar (opsional)
            'foto' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            try {
                $timezone = (string) config(
                    'simantap.display_timezone',
                    'Asia/Jakarta',
                );
                $start = CarbonImmutable::createFromFormat(
                    '!Y-m-d\TH:i',
                    (string) $this->input('planned_start_at'),
                    $timezone,
                );
                $end = CarbonImmutable::createFromFormat(
                    '!Y-m-d\TH:i',
                    (string) $this->input('planned_end_at'),
                    $timezone,
                );

                if ($start === false || $end === false) {
                    return;
                }

                if ($start->lt(CarbonImmutable::now($timezone)->startOfMinute())) {
                    $validator->errors()->add(
                        'planned_start_at',
                        'Waktu mulai tidak boleh berada di masa lalu.',
                    );
                }

                if ($end->lte($start)) {
                    $validator->errors()->add(
                        'planned_end_at',
                        'Waktu selesai harus setelah waktu mulai.',
                    );

                    return;
                }

                $maxDays = (int) config(
                    'simantap.vehicle.max_loan_days',
                    3,
                );

                if ($start->diffInMinutes($end) > $maxDays * 1440) {
                    $validator->errors()->add(
                        'planned_end_at',
                        "Durasi peminjaman maksimal {$maxDays} hari.",
                    );
                }
            } catch (Throwable) {
                // Aturan date_format akan memberikan pesan validasi utama.
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'vehicle_id' => 'kendaraan',
            'planned_start_at' => 'waktu mulai',
            'planned_end_at' => 'waktu selesai',
            'purpose' => 'keperluan',
            'destination' => 'tujuan perjalanan',
            'reason' => 'keterangan pendukung',
            'foto' => 'foto peminjaman', // Nama atribut untuk pesan error
            'notes' => 'catatan',
        ];
    }
}