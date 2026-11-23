<?php

namespace App\Support;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Excel as Format;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** One place that turns headings and rows into an Excel or CSV download. */
final class Sheet
{
    /** @param iterable<int, array<int, mixed>> $rows */
    public static function download(string $name, array $head, iterable $rows, string $format = 'xlsx'): BinaryFileResponse
    {
        // A leading =, +, - or @ would be run as a formula by spreadsheet programs; a leading quote makes it text.
        $safe = [];
        foreach ($rows as $row) {
            $safe[] = array_map(fn ($v) => is_string($v) && $v !== '' && str_contains('=+-@', $v[0]) && ! is_numeric($v) ? "'" . $v : $v, array_values((array) $row));
        }
        $export = new class($head, $safe) implements FromArray, ShouldAutoSize, WithHeadings, WithStrictNullComparison
        {
            public function __construct(private array $head, private array $rows) {}

            public function array(): array
            {
                return $this->rows;
            }

            public function headings(): array
            {
                return $this->head;
            }
        };

        return Excel::download($export, $name . '-' . now()->format('Ymd-His') . '.' . $format, $format === 'csv' ? Format::CSV : Format::XLSX);
    }
}
