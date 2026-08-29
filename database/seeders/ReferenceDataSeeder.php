<?php

namespace Database\Seeders;

use App\Models\ItemCategory;
use App\Models\Setting;
use App\Models\Unit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ReferenceDataSeeder extends Seeder
{
    /**
     * @var list<array{
     *     name:string,
     *     description:string
     * }>
     */
    private const ITEM_CATEGORIES = [
        [
            'name' => 'Alat Tulis',
            'description' => 'Barang habis pakai untuk kegiatan administrasi perkantoran.',
        ],
        [
            'name' => 'Alat Tulis Kantor',
            'description' => 'Peralatan administrasi kantor seperti pulpen, pensil, map, dan perlengkapan ATK.',
        ],
        [
            'name' => 'Kertas',
            'description' => 'Berbagai jenis dan ukuran kertas untuk kebutuhan kantor.',
        ],
        [
            'name' => 'Tinta',
            'description' => 'Tinta, toner, cartridge, dan bahan habis pakai untuk perangkat pencetakan.',
        ],
        [
            'name' => 'Perlengkapan Kebersihan',
            'description' => 'Bahan dan perlengkapan untuk menjaga kebersihan lingkungan kerja.',
        ],
        [
            'name' => 'Barang Cetakan',
            'description' => 'Formulir, buku, amplop, dan kebutuhan cetak kedinasan.',
        ],
        [
            'name' => 'Perlengkapan Komputer',
            'description' => 'Perlengkapan pendukung komputer dan perangkat kerja digital.',
        ],
        [
            'name' => 'Perlengkapan Arsip',
            'description' => 'Map, ordner, binder, dan perlengkapan penyimpanan dokumen.',
        ],
        [
            'name' => 'Perlengkapan Cetak',
            'description' => 'Perlengkapan pendukung proses pencetakan dokumen.',
        ],
        [
            'name' => 'Pantry / Konsumsi',
            'description' => 'Kebutuhan pantry dan konsumsi kantor.',
        ],
        [
            'name' => 'Baterai & Kelistrikan',
            'description' => 'Baterai, kabel, dan perlengkapan kelistrikan pendukung.',
        ],
        [
            'name' => 'Materai & Administrasi',
            'description' => 'Materai dan kebutuhan administrasi resmi.',
        ],
        [
            'name' => 'Lainnya',
            'description' => 'Kategori barang lainnya.',
        ],
    ];

    /**
     * @var list<array{
     *     name:string,
     *     symbol:string
     * }>
     */
    private const UNITS = [
        [
            'name' => 'Buah',
            'symbol' => 'buah',
        ],
        [
            'name' => 'Rim',
            'symbol' => 'rim',
        ],
        [
            'name' => 'Pak',
            'symbol' => 'pak',
        ],
        [
            'name' => 'Kotak',
            'symbol' => 'kotak',
        ],
        [
            'name' => 'Botol',
            'symbol' => 'botol',
        ],
        [
            'name' => 'Unit',
            'symbol' => 'unit',
        ],
        [
            'name' => 'PCS',
            'symbol' => 'pcs',
        ],
        [
            'name' => 'Roll',
            'symbol' => 'roll',
        ],
        [
            'name' => 'Set',
            'symbol' => 'set',
        ],
        [
            'name' => 'Lembar',
            'symbol' => 'lembar',
        ],
    ];

    public function run(): void
    {
        DB::transaction(function (): void {

            $this->seedItemCategories();

            $this->seedUnits();

            $this->seedSettings();

        });
    }

    private function seedItemCategories(): void
    {
        foreach (self::ITEM_CATEGORIES as $data) {

            $category = ItemCategory::withTrashed()
                ->where('name', $data['name'])
                ->first();

            if ($category === null) {

                ItemCategory::query()->create([
                    ...$data,
                    'is_active' => true,
                ]);

                continue;
            }

            if ($category->trashed()) {
                $category->restore();
            }

            $category->forceFill([
                'description' => $data['description'],
                'is_active' => true,
            ])->save();
        }
    }

    private function seedUnits(): void
    {
        foreach (self::UNITS as $data) {

            $unit = Unit::withTrashed()
                ->where('symbol', $data['symbol'])
                ->first();

            if ($unit === null) {

                Unit::query()->create([
                    ...$data,
                    'is_active' => true,
                ]);

                continue;
            }

            if ($unit->trashed()) {
                $unit->restore();
            }

            $unit->forceFill([
                'name' => $data['name'],
                'is_active' => true,
            ])->save();
        }
    }

    private function seedSettings(): void
    {
        $settings = [

            [
                'key' => 'application.name',
                'value' => [
                    'text' => (string) config(
                        'simantap.name',
                        'SIMANTAP',
                    ),
                ],
                'group' => 'application',
                'is_public' => true,
            ],

            [
                'key' => 'organization.name',
                'value' => [
                    'text' => (string) config(
                        'simantap.institution.name',
                        'Badan Pusat Statistik',
                    ),
                ],
                'group' => 'organization',
                'is_public' => true,
            ],

            [
                'key' => 'organization.short_name',
                'value' => [
                    'text' => (string) config(
                        'simantap.institution.short_name',
                        'BPS',
                    ),
                ],
                'group' => 'organization',
                'is_public' => true,
            ],

            [
                'key' => 'system.display_timezone',
                'value' => [
                    'text' => (string) config(
                        'simantap.display_timezone',
                        'Asia/Jakarta',
                    ),
                ],
                'group' => 'system',
                'is_public' => false,
            ],

            [
                'key' => 'vehicle.max_loan_days',
                'value' => [
                    'number' => (int) config(
                        'simantap.vehicle.max_loan_days',
                        3,
                    ),
                ],
                'group' => 'vehicle',
                'is_public' => false,
            ],

        ];

        foreach ($settings as $setting) {

            Setting::query()->firstOrCreate(
                [
                    'key' => $setting['key'],
                ],
                [
                    'value' => $setting['value'],
                    'group' => $setting['group'],
                    'is_public' => $setting['is_public'],
                ],
            );

        }
    }
}
