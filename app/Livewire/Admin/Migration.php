<?php

namespace App\Livewire\Admin;

use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Support\SpreadsheetExportValue;
use App\Support\SpreadsheetExportValueBinder;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;

class Migration extends Component
{
    private const SCOPES = [
        'all' => 'completo',
        'available' => 'disponibles',
        'dispatched' => 'despachados',
        'incomplete' => 'incompletos',
    ];

    public string $exportFormat = 'csv';
    public string $scope = 'all';

    public function render()
    {
        $scopeCounts = [
            'all' => InventoryItem::count(),
            'available' => $this->inventoryQuery('available')->count(),
            'dispatched' => $this->inventoryQuery('dispatched')->count(),
            'incomplete' => $this->inventoryQuery('incomplete')->count(),
        ];
        $scopeNames = [
            'all' => 'Inventario completo',
            'available' => 'Repuestos disponibles',
            'dispatched' => 'Repuestos con salidas registradas',
            'incomplete' => 'Registros con ubicación incompleta',
        ];

        return view('livewire.admin.migration', [
            'totalRecords' => $scopeCounts['all'],
            'scopeCounts' => $scopeCounts,
            'scopeNames' => $scopeNames,
            'selectedRecords' => $scopeCounts[$this->scope] ?? 0,
            'scopeSuffix' => self::SCOPES[$this->scope] ?? self::SCOPES['all'],
        ]);
    }

    public function export()
    {
        abort_unless(in_array(auth()->user()?->role, ['administrador', 'superadministrador'], true), 403);
        $validated = $this->validate([
            'exportFormat' => ['required', 'in:csv,xlsx'],
            'scope' => ['required', 'in:all,available,dispatched,incomplete'],
        ]);
        $format = $validated['exportFormat'];
        $scope = $validated['scope'];
        $filename = 'inventario_sittsa_'.self::SCOPES[$scope].'_'.now()->format('Ymd').'.'.$format;

        if ($format === 'csv') {
            $callback = function () use ($scope): void {
                $handle = fopen('php://output', 'w');
                if ($handle === false) {
                    throw new \RuntimeException('No se pudo abrir la salida del archivo CSV.');
                }

                fwrite($handle, "\xEF\xBB\xBF");
                fputcsv($handle, ['item_number', 'sigicov', 'descripcion', 'tipo', 'unidad', 'inventario', 'resp_accesorios', 'pasillo', 'estante', 'peldaño', 'fecha', 'realizado_por']);

                $this->inventoryQuery($scope)->chunk(1000, function ($rows) use ($handle): void {
                    foreach ($rows as $r) {
                        fputcsv($handle, array_map([SpreadsheetExportValue::class, 'forCsv'], [
                            $r->item_number,
                            $r->sigicov,
                            $r->descripcion,
                            $r->inventoryType?->name,
                            $r->unidad,
                            $r->inventario,
                            $r->resp_accesorios,
                            $r->pasillo,
                            $r->estante,
                            $r->{'peldaño'},
                            $r->fecha,
                            $r->realizado_por,
                        ]));
                    }
                });

                fclose($handle);
            };

            return response()->streamDownload($callback, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        $export = new class($this->inventoryQuery($scope)) extends SpreadsheetExportValueBinder implements
            \Maatwebsite\Excel\Concerns\FromCollection,
            \Maatwebsite\Excel\Concerns\WithHeadings,
            \Maatwebsite\Excel\Concerns\ShouldAutoSize,
            \Maatwebsite\Excel\Concerns\WithStyles,
            \Maatwebsite\Excel\Concerns\WithEvents
        {
            public function __construct(private Builder $query)
            {
            }

            public function collection(): \Illuminate\Support\Collection
            {
                return $this->query->get()->map(function ($i) {
                    return [
                        'item_number' => $i->item_number,
                        'sigicov' => $i->sigicov,
                        'descripcion' => $i->descripcion,
                        'tipo' => $i->inventoryType?->name,
                        'unidad' => $i->unidad,
                        'inventario' => $i->inventario,
                        'resp_accesorios' => $i->resp_accesorios,
                        'pasillo' => $i->pasillo,
                        'estante' => $i->estante,
                        'peldaño' => $i->{'peldaño'},
                        'fecha' => $i->fecha,
                        'realizado_por' => $i->realizado_por,
                    ];
                });
            }

            public function headings(): array
            {
                return ['item_number', 'sigicov', 'descripcion', 'tipo', 'unidad', 'inventario', 'resp_accesorios', 'pasillo', 'estante', 'peldaño', 'fecha', 'realizado_por'];
            }

            public function styles(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): array
            {
                return [
                    1 => [
                        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                        'fill' => [
                            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                            'startColor' => ['rgb' => '4A0C12'],
                        ],
                    ],
                ];
            }

            public function registerEvents(): array
            {
                return [
                    \Maatwebsite\Excel\Events\AfterSheet::class => function (\Maatwebsite\Excel\Events\AfterSheet $event): void {
                        $event->sheet->setAutoFilter($event->sheet->calculateWorksheetDimension());
                    },
                ];
            }
        };

        return Excel::download($export, $filename);
    }

    private function inventoryQuery(string $scope): Builder
    {
        return InventoryItem::query()
            ->with('inventoryType')
            ->when($scope === 'available', fn (Builder $query) => $query->whereRaw('CAST(inventario AS INTEGER) > 0'))
            ->when($scope === 'dispatched', fn (Builder $query) => $query->whereIn(
                'id',
                InventoryMovement::query()->select('inventory_item_id')->where('tipo', 'salida')->distinct()
            ))
            ->when($scope === 'incomplete', function (Builder $query): void {
                $query->where(function (Builder $incomplete): void {
                    foreach (['pasillo', 'estante', 'peldaño'] as $field) {
                        $incomplete->orWhere(function (Builder $missing) use ($field): void {
                            $missing->whereNull($field)
                                ->orWhere($field, '')
                                ->orWhereRaw("UPPER({$field}) IN (?, ?, ?, ?)", [
                                    'NO DISPONIBLE',
                                    '0',
                                    '-',
                                    '--',
                                ]);
                        });
                    }
                });
            })
            ->orderByDesc('id');
    }
}
