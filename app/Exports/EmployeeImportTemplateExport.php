<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EmployeeImportTemplateExport implements FromArray, ShouldAutoSize, WithHeadings, WithStyles
{
    /**
     * @return list<array<string>>
     */
    public function array(): array
    {
        return [];
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return [
            'nip',
            'nama_lengkap',
            'email',
            'nomor_telepon',
            'unit_kerja',
            'jabatan',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function styles(Worksheet $sheet): array
    {
        $sheet->freezePane('A2');
        $sheet->getStyle('A1:F1')
            ->getFont()
            ->setBold(true);

        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['argb' => 'FFFFFFFF'],
                ],
                'fill' => [
                    'fillType' => 'solid',
                    'startColor' => ['argb' => 'FF0369A1'],
                ],
            ],
        ];
    }
}
