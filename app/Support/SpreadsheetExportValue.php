<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

final class SpreadsheetExportValue
{
    public static function isFormulaLike(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[\x00-\x20]*[=+\-@]/', $value) === 1;
    }

    public static function forCsv(mixed $value): mixed
    {
        return self::isFormulaLike($value) ? "'".$value : $value;
    }

    public static function setCell(
        Worksheet $sheet,
        string $address,
        mixed $value,
        bool $isCsv,
    ): void {
        if (! self::isFormulaLike($value)) {
            $sheet->setCellValue($address, $value);

            return;
        }

        if ($isCsv) {
            $sheet->setCellValue($address, self::forCsv($value));

            return;
        }

        $sheet->setCellValueExplicit($address, $value, DataType::TYPE_STRING);
    }
}
