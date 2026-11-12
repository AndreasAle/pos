<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Generic multi-sheet export: [sheet title => rows]. Row 1 (report title) is bold. */
class FinanceSheetsExport implements WithMultipleSheets
{
    /** @param array<string, array<int, array<int, mixed>>> $sheets */
    public function __construct(private array $sheets) {}

    public function sheets(): array
    {
        $out = [];
        foreach ($this->sheets as $title => $rows) {
            $out[] = new FinanceSheet((string) $title, $rows);
        }

        return $out;
    }
}

class FinanceSheet implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    public function __construct(private string $title, private array $rows) {}

    public function title(): string
    {
        return substr($this->title, 0, 31);
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true, 'size' => 13]]];
    }
}
