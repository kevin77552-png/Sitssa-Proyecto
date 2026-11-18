<?php

namespace App\Services;

use App\Models\Vehiculo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

class UnitsSpreadsheet
{
    public const MAX_ROWS = 2000;

    public const HEADERS = [
        'placa',
        'codigo_externo',
        'tipo',
        'modelo',
        'descripcion',
        'estado',
    ];

    private const ALIASES = [
        'placa' => ['PLACA'],
        'codigo_externo' => ['CODIGOEXTERNO', 'EXTERNALID', 'IDEXTERNO', 'CODEXTERNO'],
        'tipo' => ['TIPO'],
        'modelo' => ['MODELO'],
        'descripcion' => ['DESCRIPCION'],
        'estado' => ['ESTADO'],
    ];

    public function read(string $path, string $extension): array
    {
        $reader = strtolower($extension) === 'xlsx' ? new XlsxReader() : $this->csvReader($path);
        $reader->open($path);
        $rows = [];
        $headerMap = null;

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $number => $row) {
                    $cells = array_map(fn ($cell) => $this->cell($cell), $row->toArray());

                    if ($headerMap === null) {
                        if (count(array_filter($cells, fn ($cell) => $cell !== null)) === 0) {
                            continue;
                        }

                        $headerMap = $this->mapHeaders($cells);
                        continue;
                    }

                    $data = ['line' => (int) $number];
                    foreach ($headerMap as $field => $index) {
                        $data[$field] = $cells[$index] ?? null;
                    }

                    if (count(array_filter(array_diff_key($data, ['line' => true]), fn ($value) => $value !== null)) === 0) {
                        continue;
                    }
                    if (count($rows) >= self::MAX_ROWS) {
                        throw new \RuntimeException('El archivo tiene más de '.self::MAX_ROWS.' filas. Divídelo en partes.');
                    }

                    $rows[] = $data;
                }

                break;
            }
        } finally {
            $reader->close();
        }

        if ($headerMap === null) {
            throw new \RuntimeException('El archivo está vacío.');
        }

        return $rows;
    }

    public function plan(array $rows): array
    {
        $existing = Vehiculo::query()->get();
        $byPlate = $existing->keyBy(fn ($unit) => mb_strtoupper(trim($unit->placa)));
        $byExternal = $existing->filter(fn ($unit) => filled($unit->external_id))
            ->keyBy(fn ($unit) => mb_strtoupper(trim($unit->external_id)));
        $seenPlates = $seenExternal = [];
        $plan = [];

        foreach ($rows as $row) {
            $item = [
                'line' => (int) ($row['line'] ?? 0),
                'placa' => null,
                'action' => 'error',
                'reason' => null,
                'changes' => [],
                'id' => null,
                'data' => [],
            ];
            $plate = isset($row['placa']) ? mb_strtoupper(trim($row['placa'])) : null;
            $item['placa'] = $plate;

            if ($plate === null || $plate === '') {
                $plan[] = $this->fail($item, 'Falta la placa.');
                continue;
            }
            if (mb_strlen($plate) > 20 || ! preg_match('/^[A-Z0-9][A-Z0-9 \-]*$/u', $plate)) {
                $plan[] = $this->fail($item, 'Placa inválida (letras, números, espacios o guiones; máximo 20).');
                continue;
            }
            if (isset($seenPlates[$plate])) {
                $plan[] = $this->fail($item, 'Placa repetida en el archivo (se usa la primera fila).');
                continue;
            }
            $seenPlates[$plate] = true;

            $external = $this->nullableCell($row['codigo_externo'] ?? null);
            $externalKey = $external !== null ? mb_strtoupper($external) : null;
            if ($externalKey !== null && isset($seenExternal[$externalKey])) {
                $plan[] = $this->fail($item, 'Código externo repetido en el archivo.');
                continue;
            }
            if ($externalKey !== null) {
                $seenExternal[$externalKey] = true;
            }

            $status = null;
            if (($row['estado'] ?? null) !== null) {
                $status = $this->parseStatus((string) $row['estado']);
                if ($status === null) {
                    $plan[] = $this->fail($item, 'Estado no reconocido (usa activa o inactiva).');
                    continue;
                }
            }

            $unit = null;
            if ($external !== null && $byExternal->has($external)) {
                $unit = $byExternal->get($externalKey);
                $ownerByPlate = $byPlate->get($plate);
                if ($ownerByPlate && $ownerByPlate->id !== $unit->id) {
                    $plan[] = $this->fail($item, "Conflicto: la placa {$plate} pertenece a otra unidad.");
                    continue;
                }
            } else {
                $unit = $byPlate->get($plate);
                if ($unit && $external !== null && filled($unit->external_id) && $unit->external_id !== $external) {
                    $plan[] = $this->fail($item, 'La placa ya tiene otro código externo.');
                    continue;
                }
            }

            if (($external !== null && mb_strlen($external) > 64)
                || (isset($row['tipo']) && mb_strlen($row['tipo']) > 40)
                || (isset($row['modelo']) && mb_strlen($row['modelo']) > 60)
                || (isset($row['descripcion']) && mb_strlen($row['descripcion']) > 255)) {
                $plan[] = $this->fail($item, 'Uno o más campos superan la longitud permitida.');
                continue;
            }

            $data = array_filter([
                'placa' => $plate,
                'external_id' => $external,
                'tipo' => isset($row['tipo']) ? mb_strtoupper(trim($row['tipo'])) : null,
                'modelo' => isset($row['modelo']) ? mb_strtoupper(trim($row['modelo'])) : null,
                'descripcion' => $this->nullableCell($row['descripcion'] ?? null),
                'estado' => $status,
            ], fn ($value) => $value !== null);

            if ($unit) {
                $changes = [];
                foreach ($data as $field => $newValue) {
                    $oldValue = $unit->{$field};
                    if ((string) $oldValue !== (string) $newValue) {
                        $changes[$field] = [$oldValue, $newValue];
                    }
                }
                $item['id'] = $unit->id;
                $item['changes'] = $changes;
                $item['data'] = $data;
                $item['action'] = $changes === [] ? 'sin_cambios' : 'actualizar';
            } else {
                if (! isset($data['descripcion'])) {
                    $description = trim(($data['tipo'] ?? '').' '.($data['modelo'] ?? ''));
                    if ($description !== '') {
                        $data['descripcion'] = $description;
                    }
                }
                $data['estado'] ??= 'activa';
                $item['data'] = $data;
                $item['action'] = 'crear';
            }

            $plan[] = $item;
        }

        return $plan;
    }

    public function apply(array $plan): array
    {
        $counts = ['creadas' => 0, 'actualizadas' => 0, 'sin_cambios' => 0, 'errores' => 0];

        DB::transaction(function () use ($plan, &$counts): void {
            foreach ($plan as $item) {
                switch ($item['action']) {
                    case 'crear':
                        Vehiculo::create($item['data'] + ['source' => 'manual']);
                        $counts['creadas']++;
                        break;
                    case 'actualizar':
                        Vehiculo::query()->whereKey($item['id'])->firstOrFail()->update($item['data']);
                        $counts['actualizadas']++;
                        break;
                    case 'sin_cambios':
                        $counts['sin_cambios']++;
                        break;
                    default:
                        $counts['errores']++;
                }
            }
        });

        return $counts;
    }

    public function export(string $path): void
    {
        $writer = new XlsxWriter();
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName('Unidades');
        $writer->addRow(Row::fromValues(self::HEADERS, (new Style())->setFontBold()));

        Vehiculo::query()->orderBy('placa')->chunk(500, function ($units) use ($writer): void {
            foreach ($units as $unit) {
                $writer->addRow(Row::fromValues([
                    $this->safe($unit->placa),
                    $this->safe($unit->external_id),
                    $this->safe($unit->tipo),
                    $this->safe($unit->modelo),
                    $this->safe($unit->descripcion),
                    $unit->estado,
                ]));
            }
        });

        $writer->close();
    }

    public function exportCsv(string $path): void
    {
        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new \RuntimeException('No se pudo crear el archivo CSV de unidades.');
        }

        try {
            if (fwrite($handle, "\xEF\xBB\xBF") === false) {
                throw new \RuntimeException('No se pudo escribir el archivo CSV de unidades.');
            }
            $this->writeCsvRow($handle, self::HEADERS);

            Vehiculo::query()->orderBy('placa')->chunk(500, function ($units) use ($handle): void {
                foreach ($units as $unit) {
                    $this->writeCsvRow($handle, [
                        $this->safe($unit->placa),
                        $this->safe($unit->external_id),
                        $this->safe($unit->tipo),
                        $this->safe($unit->modelo),
                        $this->safe($unit->descripcion),
                        $unit->estado,
                    ]);
                }
            });
        } finally {
            fclose($handle);
        }
    }

    public function template(string $path): void
    {
        $writer = new XlsxWriter();
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName('Unidades');
        $writer->addRow(Row::fromValues(self::HEADERS, (new Style())->setFontBold()));
        $writer->addNewSheetAndMakeItCurrent()->setName('Instrucciones');

        foreach ([
            'Llena la hoja "Unidades": una fila por unidad. Solo la placa es obligatoria.',
            'placa: letras, números, espacios o guiones (máximo 20). Se guarda en mayúsculas.',
            'codigo_externo: identificador de la unidad en otro sistema (opcional, único).',
            'tipo y modelo: texto libre (por ejemplo 12 MTS y ZK6118HGA).',
            'descripcion: opcional. Si la dejas vacía se arma con tipo y modelo.',
            'estado: activa o inactiva (si la dejas vacía, una unidad nueva queda activa).',
            'Al importar se crean las unidades nuevas y se actualizan las existentes (por código externo o placa).',
            'Las unidades que no aparezcan en el archivo NO se tocan y nunca se borran.',
        ] as $line) {
            $writer->addRow(Row::fromValues([$line]));
        }

        $writer->close();
    }

    private function fail(array $item, string $reason): array
    {
        $item['action'] = 'error';
        $item['reason'] = $reason;

        return $item;
    }

    private function parseStatus(string $value): ?string
    {
        return match (mb_strtoupper(Str::ascii($value))) {
            'ACTIVA', 'ACTIVO', 'SI', '1' => 'activa',
            'INACTIVA', 'INACTIVO', 'NO', '0', 'BAJA' => 'inactiva',
            default => null,
        };
    }

    private function mapHeaders(array $cells): array
    {
        $normalized = array_map(
            fn ($cell) => strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', Str::ascii((string) $cell))),
            $cells
        );
        $map = [];
        foreach (self::ALIASES as $field => $aliases) {
            foreach ($normalized as $index => $header) {
                if (in_array($header, $aliases, true)) {
                    $map[$field] = $index;
                    break;
                }
            }
        }

        if (! isset($map['placa'])) {
            throw new \RuntimeException('No se encontró la columna "placa" en el encabezado del archivo.');
        }

        return $map;
    }

    private function cell(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            $value = $value->format('Y-m-d');
        }
        if (is_float($value) && floor($value) === $value) {
            $value = (int) $value;
        }

        return $this->nullableCell($value);
    }

    private function nullableCell(mixed $value): ?string
    {
        $text = trim((string) preg_replace('/[\s\x{00A0}]+/u', ' ', (string) ($value ?? '')));

        return $text === '' ? null : $text;
    }

    private function csvReader(string $path): CsvReader
    {
        $firstLine = (string) strtok((string) file_get_contents($path, false, null, 0, 4096), "\n");
        $options = new CsvOptions();
        $options->FIELD_DELIMITER = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';

        return new CsvReader($options);
    }

    private function safe(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        return in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }

    private function writeCsvRow($handle, array $values): void
    {
        if (fputcsv($handle, $values, ',', '"', '') === false) {
            throw new \RuntimeException('No se pudo escribir una fila del archivo CSV de unidades.');
        }
    }
}
