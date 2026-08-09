<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ReportSummarySheet implements FromArray, WithEvents, WithTitle
{
    /** @param array<string, mixed> $report */
    public function __construct(private readonly array $report) {}

    /** @return list<list<string>> */
    public function array(): array
    {
        $filters = $this->report['filters'];
        $filterRows = array_filter([
            ['Pencarian', $filters['search']],
            ['ID Barang', $filters['itemId'] > 0 ? (string) $filters['itemId'] : ''],
            ['Jenis Transaksi', $filters['movementType']],
            ['Status', $filters['status']],
            ['Unit Kerja', $filters['workUnit']],
        ], static fn (array $row): bool => $row[1] !== '');

        return [
            ['SIMANTAP — LAPORAN OPERASIONAL', ''],
            [$this->report['title'], ''],
            ['Deskripsi', $this->report['description']],
            ['Periode', $this->report['periodLabel']],
            ['Dibuat', $this->report['generatedAt']->format('d/m/Y H:i').' WIB'],
            ['Zona waktu', $this->report['displayTimezone']],
            ['', ''],
            ['RINGKASAN', ''],
            ...array_map(static fn (array $item): array => [$item['label'], $item['value']], $this->report['summary']),
            ['', ''],
            ['FILTER AKTIF', ''],
            ...($filterRows === [] ? [['Filter tambahan', 'Tidak ada']] : array_values($filterRows)),
        ];
    }

    public function title(): string
    {
        return 'Ringkasan';
    }

    /** @return array<class-string, callable> */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $lastRow = $sheet->getHighestRow();
                $sheet->mergeCells('A1:B1');
                $sheet->mergeCells('A2:B2');
                $sheet->freezePane('A3');
                $sheet->getStyle("A1:B{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
                $sheet->getStyle("B1:B{$lastRow}")->getAlignment()->setWrapText(true);
                $sheet->getStyle('A1:B2')->getFont()->setBold(true)->setColor(new Color('FFFFFFFF'));
                $sheet->getStyle('A1:B2')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF075985');
                $sheet->getStyle('A1')->getFont()->setSize(15);
                foreach (range(1, $lastRow) as $row) {
                    if (in_array($sheet->getCell("A{$row}")->getValue(), ['RINGKASAN', 'FILTER AKTIF'], true)) {
                        $sheet->getStyle("A{$row}:B{$row}")->getFont()->setBold(true)->setColor(new Color('FF075985'));
                        $sheet->getStyle("A{$row}:B{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE0F2FE');
                    }
                }
                $sheet->getStyle("A1:B{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_HAIR)->getColor()->setARGB('FFCBD5E1');
                $sheet->getColumnDimension('A')->setWidth(24);
                $sheet->getColumnDimension('B')->setWidth(68);
                $sheet->getRowDimension(1)->setRowHeight(25);
            },
        ];
    }
}
