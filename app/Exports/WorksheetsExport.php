<?php

namespace App\Exports;

use App\Models\Worksheet;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet as SpreadsheetWorksheet;

class WorksheetsExport implements FromCollection, ShouldAutoSize, WithHeadings, WithStyles
{
    public function __construct(
        private readonly Collection $worksheets,
        private readonly int $rangeTotal,
    ) {
    }

    public function headings(): array
    {
        return [
            'Dátum',
            'Munkalapneve',
            'Tételek',
            'Megjegyzés',
            'Összeg',
        ];
    }

    public function collection(): Collection
    {
        return $this->worksheets
            ->map(function (Worksheet $worksheet): array {
                return [
                    $worksheet->work_date?->format('Y-m-d'),
                    $worksheet->worksheet_number,
                    $worksheet->items->pluck('item_name_at_time')->filter()->implode(' + '),
                    $worksheet->note ?? '',
                    number_format((int) $worksheet->getAttribute('calculated_total'), 0, ',', ' ') . ' Ft',
                ];
            })
            ->push([
                '',
                '',
                '',
                'Kiválasztott időszak bevétele',
                number_format($this->rangeTotal, 0, ',', ' ') . ' Ft',
            ]);
    }

    public function styles(SpreadsheetWorksheet $sheet): array
    {
        $summaryRow = $this->worksheets->count() + 2;

        return [
            1 => ['font' => ['bold' => true]],
            $summaryRow => ['font' => ['bold' => true]],
        ];
    }
}
