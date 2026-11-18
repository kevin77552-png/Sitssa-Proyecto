<?php

namespace App\Support;

use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

class SpreadsheetExportValueBinder extends DefaultValueBinder implements WithCustomValueBinder
{
    public function bindValue(Cell $cell, mixed $value): bool
    {
        if (SpreadsheetExportValue::isFormulaLike($value)) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }
}
