<?php

namespace App\Services\Import;

use DateTimeInterface;
use Illuminate\Support\Str;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\ODS\Options as OdsOptions;
use OpenSpout\Reader\ODS\Reader as OdsReader;
use OpenSpout\Reader\ReaderInterface;
use OpenSpout\Reader\XLSX\Options as XlsxOptions;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use RuntimeException;

class LegacyExcelParser
{
    private const TEXT_FIELDS = [
        'unidad',
        'pasillo',
        'estante',
        'peldaño',
        'realizado_por',
        'resp_accesorios',
    ];

    private const EMPTY_VALUES = [
        '',
        '0',
        '-',
        '--',
        'N/A',
        'NA',
        'NAN',
        'NULL',
        '#N/A',
        '#N/D',
        '#REF!',
        '#VALUE!',
        '#DIV/0!',
    ];

    /**
     * @return array{sheet: string, header_row: int, columns: array<string, int|null>}
     */
    public function inspect(string $path, ?string $requestedSheet = null): array
    {
        $reader = $this->createReader($path);
        $reader->open($path);
        $found = [];

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                if ($requestedSheet !== null && $sheet->getName() !== $requestedSheet) {
                    continue;
                }

                $head = [];
                foreach ($sheet->getRowIterator() as $index => $row) {
                    if ($index > 10) {
                        break;
                    }
                    $head[$index] = $row->toArray();
                }

                $candidate = $this->findHeaders($head);
                if ($candidate !== null) {
                    $found[] = [
                        'sheet' => $sheet->getName(),
                        ...$candidate,
                    ];
                }
            }
        } finally {
            $reader->close();
        }

        if ($requestedSheet !== null) {
            foreach ($found as $candidate) {
                if ($candidate['sheet'] === $requestedSheet) {
                    return $candidate;
                }
            }

            throw new RuntimeException("La hoja solicitada '{$requestedSheet}' no existe o no contiene encabezados de SIGICOV, descripción y pasillo.");
        }

        $preferred = config('inventory_import.preferred_sheet');
        foreach ($found as $candidate) {
            if ($candidate['sheet'] === $preferred) {
                return $candidate;
            }
        }

        return $found[0] ?? throw new RuntimeException('No se encontró una hoja con encabezados de SIGICOV, descripción y pasillo.');
    }

    /**
     * @return iterable<array{excel_row: int, original: array, payload: array, invalid_reason: ?string}>
     */
    public function rows(string $path, ?string $requestedSheet = null): iterable
    {
        $inspection = $this->inspect($path, $requestedSheet);
        $reader = $this->createReader($path);
        $reader->open($path);

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                if ($sheet->getName() !== $inspection['sheet']) {
                    continue;
                }

                foreach ($sheet->getRowIterator() as $index => $row) {
                    if ($index <= $inspection['header_row']) {
                        continue;
                    }

                    $values = $row->toArray();
                    if (! $this->hasData($values)) {
                        continue;
                    }

                    $original = [];
                    foreach ($values as $column => $value) {
                        $text = $this->stringValue($value);
                        if ($text !== '') {
                            $original[(string) $column] = $text;
                        }
                    }

                    $invalidReason = null;
                    try {
                        $payload = $this->mapRow($values, $inspection['columns']);
                    } catch (RuntimeException $exception) {
                        $invalidReason = $exception->getMessage();
                        $payload = [
                            'sigicov' => $this->normalizeSigicov($this->value($values, $inspection['columns']['sigicov'])),
                        ];
                    }

                    yield [
                        'excel_row' => $index,
                        'original' => $original,
                        'payload' => $payload,
                        'invalid_reason' => $invalidReason,
                    ];
                }
            }
        } finally {
            $reader->close();
        }
    }

    private function findHeaders(array $rows): ?array
    {
        $candidates = [];
        if (isset($rows[6], $rows[7])) {
            $combined = [];
            $length = max(count($rows[6]), count($rows[7]));
            for ($column = 0; $column < $length; $column++) {
                $combined[] = trim($this->stringValue($rows[6][$column] ?? '').' '.$this->stringValue($rows[7][$column] ?? ''));
            }

            $candidates[] = ['header_row' => 7, 'headers' => $combined];
        }

        foreach ($rows as $index => $row) {
            $candidates[] = ['header_row' => $index, 'headers' => $row];
        }

        foreach ($candidates as $candidate) {
            $columns = $this->mapHeaders($candidate['headers']);
            if ($columns['sigicov'] !== null && $columns['descripcion'] !== null && $columns['pasillo'] !== null) {
                return [
                    'header_row' => $candidate['header_row'],
                    'columns' => $columns,
                ];
            }
        }

        return null;
    }

    private function createReader(string $path): ReaderInterface
    {
        switch (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            case 'xlsx':
                $options = new XlsxOptions();
                $options->SHOULD_PRESERVE_EMPTY_ROWS = true;

                return new XlsxReader($options);
            case 'csv':
                $options = new CsvOptions();
                $options->SHOULD_PRESERVE_EMPTY_ROWS = true;

                return new CsvReader($options);
            case 'ods':
                $options = new OdsOptions();
                $options->SHOULD_PRESERVE_EMPTY_ROWS = true;

                return new OdsReader($options);
            default:
                throw new RuntimeException('Formato no compatible. Usa XLSX, CSV u ODS.');
        }
    }

    private function mapHeaders(array $headers): array
    {
        $columns = [
            'item_number' => null,
            'sigicov' => null,
            'descripcion' => null,
            'unidad' => null,
            'pasillo' => null,
            'estante' => null,
            'peldaño' => null,
            'fecha' => null,
            'realizado_por' => null,
            'resp_accesorios' => null,
        ];

        foreach ($headers as $index => $header) {
            $normalized = $this->normalizeHeader($this->stringValue($header));
            if ($normalized === '' || str_contains($normalized, 'REEMPLAZO')) {
                continue;
            }

            if ($columns['sigicov'] === null && (str_contains($normalized, 'SIGICOV') || str_contains($normalized, 'SIGCOV'))) {
                $columns['sigicov'] = $index;
            } elseif ($columns['descripcion'] === null && (str_contains($normalized, 'DESCRIPCION') || str_contains($normalized, 'DESCRIPTION'))) {
                $columns['descripcion'] = $index;
            } elseif ($columns['unidad'] === null && str_contains($normalized, 'UNIDAD')) {
                $columns['unidad'] = $index;
            } elseif ($columns['item_number'] === null && (str_contains($normalized, 'ITEMNUMBER') || str_contains($normalized, 'NUMEROITEM'))) {
                $columns['item_number'] = $index;
            } elseif ($columns['pasillo'] === null && str_contains($normalized, 'PASILLO')) {
                $columns['pasillo'] = $index;
            } elseif ($columns['estante'] === null && str_contains($normalized, 'ESTANTE')) {
                $columns['estante'] = $index;
            } elseif ($columns['peldaño'] === null && (str_contains($normalized, 'PELDA') || str_contains($normalized, 'NIVEL'))) {
                $columns['peldaño'] = $index;
            } elseif ($columns['fecha'] === null && str_contains($normalized, 'FECHA')) {
                $columns['fecha'] = $index;
            } elseif ($columns['realizado_por'] === null && (str_contains($normalized, 'REALIZADOPOR') || str_contains($normalized, 'RESPONSABLE'))) {
                $columns['realizado_por'] = $index;
            } elseif ($columns['resp_accesorios'] === null && (str_contains($normalized, 'ACCESORIO') || str_contains($normalized, 'RESPACC'))) {
                $columns['resp_accesorios'] = $index;
            }

        }

        return $columns;
    }

    private function mapRow(array $values, array $columns): array
    {
        $sigicov = $this->normalizeSigicov($this->value($values, $columns['sigicov']));
        $payload = [
            'item_number' => $this->optionalText($this->value($values, $columns['item_number'])),
            'sigicov' => $sigicov,
            'descripcion' => $this->optionalText($this->value($values, $columns['descripcion'])) ?? 'NO DISPONIBLE',
            'unidad' => $this->textField($this->value($values, $columns['unidad'])),
            'pasillo' => $this->textField($this->value($values, $columns['pasillo'])),
            'estante' => $this->textField($this->value($values, $columns['estante'])),
            'peldaño' => $this->textField($this->value($values, $columns['peldaño'])),
            'fecha' => $this->dateValue($this->value($values, $columns['fecha'])),
            'realizado_por' => $this->textField($this->value($values, $columns['realizado_por'])),
            'inventario' => '1',
            'resp_accesorios' => $this->textField($this->value($values, $columns['resp_accesorios'])),
            'data' => [],
        ];

        foreach (['descripcion', 'unidad', 'pasillo', 'estante', 'peldaño', 'realizado_por', 'resp_accesorios'] as $field) {
            if ($payload[$field] !== null) {
                $payload[$field] = mb_strtoupper($payload[$field], 'UTF-8');
            }
        }

        return $payload;
    }

    private function value(array $values, ?int $index): ?string
    {
        if ($index === null || ! array_key_exists($index, $values)) {
            return null;
        }

        return $this->stringValue($values[$index]);
    }

    private function textField(?string $value): string
    {
        return $this->optionalText($value) ?? 'NO DISPONIBLE';
    }

    private function optionalText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if (in_array(mb_strtoupper($value, 'UTF-8'), self::EMPTY_VALUES, true)) {
            return null;
        }

        return $value;
    }

    private function normalizeSigicov(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if (preg_match('/^\d+\.0+$/', $value) === 1) {
            $value = substr($value, 0, strpos($value, '.'));
        }

        return preg_match('/^\d+$/', $value) === 1 ? $value : null;
    }

    private function dateValue(?string $value): ?string
    {
        if ($value === null || in_array(mb_strtoupper(trim($value), 'UTF-8'), self::EMPTY_VALUES, true)) {
            return null;
        }

        if (is_numeric($value)) {
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
            } catch (\Throwable $exception) {
                throw new RuntimeException("Fecha inválida en el Excel: '{$value}'.", 0, $exception);
            }
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            throw new RuntimeException("Fecha inválida en el Excel: '{$value}'.");
        }

        return date('Y-m-d', $timestamp);
    }

    private function normalizeHeader(string $header): string
    {
        return preg_replace('/[^A-Z0-9]/', '', Str::upper(Str::ascii($header))) ?? '';
    }

    private function stringValue(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return $value === null ? '' : trim((string) $value);
    }

    private function hasData(array $values): bool
    {
        foreach ($values as $value) {
            if ($value !== null && trim($this->stringValue($value)) !== '') {
                return true;
            }
        }

        return false;
    }
}
