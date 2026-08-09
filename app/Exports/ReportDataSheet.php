<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ReportDataSheet implements FromArray, WithEvents, WithHeadings, WithTitle
{
    /** @param array<string, mixed> $report */
    public function __construct(private readonly array $report) {}

    /** @return list<list<string>> */
    public function array(): array
    {
        return array_map(fn (array $row): array => array_map(
            static fn (array $column): string => (string) ($row[$column['key']] ?? '-'),
            $this->report['columns'],
        ), $this->report['rows']);
    }

    /** @return list<string> */
    public function headings(): array
    {
        return array_column($this->report['columns'], 'label');
    }

    public function title(): string
    {
        return 'Data';
    }

    /** @return array<class-string, callable> */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $lastColumn = Coordinate::stringFromColumnIndex(max(1, count($this->report['columns'])));
                $lastRow = max(1, $sheet->getHighestRow());
                $sheet->freezePane('A2');
                $sheet->setAutoFilter("A1:{$lastColumn}{$lastRow}");
                $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true)->setColor(new Color('FFFFFFFF'));
                $sheet->getStyle("A1:{$lastColumn}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF0369A1');
                $sheet->getStyle("A1:{$lastColumn}{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);
                $sheet->getStyle("A1:{$lastColumn}{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_HAIR)->getColor()->setARGB('FFCBD5E1');
                $sheet->getRowDimension(1)->setRowHeight(30);
                foreach (range(1, count($this->report['columns'])) as $index) {
                    $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index))->setWidth($index === 1 ? 20 : 24);
                }
                for ($row = 2; $row <= $lastRow; $row++) {
                    if ($row % 2 === 0) {
                        $sheet->getStyle("A{$row}:{$lastColumn}{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF8FAFC');
                    }
                }
            },
        ];
    }
}
