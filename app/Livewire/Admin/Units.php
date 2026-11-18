<?php

namespace App\Livewire\Admin;

use App\Models\Log;
use App\Models\Vehiculo;
use App\Services\UnitsSpreadsheet;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class Units extends Component
{
    use WithFileUploads;
    use WithPagination;

    #[Locked]
    public string $section = 'consulta';

    #[Locked]
    public ?int $editingId = null;

    public string $placa = '';

    public string $externalId = '';

    public string $tipo = '';

    public string $modelo = '';

    public string $descripcion = '';

    public string $estado = 'activa';

    public string $search = '';

    public string $statusFilter = '';

    public $file;

    public array $previewPlan = [];

    public ?array $previewCounts = null;

    public ?string $importMessage = null;

    public ?int $historyUnitId = null;

    public function mount(string $section = 'consulta', ?int $editingId = null): void
    {
        $this->authorizeAdministrator();
        abort_unless(in_array($section, ['registro', 'consulta'], true), 404);
        $this->section = $section;
        if ($editingId !== null) {
            abort_unless($section === 'registro', 404);
            $this->loadUnit($editingId);
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function save(): void
    {
        $this->authorizeAdministrator();
        abort_unless($this->section === 'registro', 404);
        $validated = $this->validate([
            'placa' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9][A-Za-z0-9 \-]*$/', function (string $attribute, mixed $value, \Closure $fail): void {
                if (filled($value) && Vehiculo::query()
                    ->whereRaw('upper(placa) = ?', [mb_strtoupper(trim($value))])
                    ->when($this->editingId, fn ($query) => $query->where('id', '<>', $this->editingId))
                    ->exists()) {
                    $fail('La placa ya está registrada.');
                }
            }],
            'externalId' => ['nullable', 'string', 'max:64', function (string $attribute, mixed $value, \Closure $fail): void {
                if (filled($value) && Vehiculo::query()
                    ->whereRaw('upper(external_id) = ?', [mb_strtoupper(trim($value))])
                    ->when($this->editingId, fn ($query) => $query->where('id', '<>', $this->editingId))
                    ->exists()) {
                    $fail('El código externo ya está asignado a otra unidad.');
                }
            }],
            'tipo' => 'nullable|string|max:40',
            'modelo' => 'nullable|string|max:60',
            'descripcion' => 'nullable|string|max:255',
            'estado' => ['required', Rule::in(array_keys(Vehiculo::ESTADOS))],
        ], [
            'placa.required' => 'La placa es obligatoria.',
            'placa.regex' => 'La placa solo puede contener letras, números, espacios y guiones.',
            'estado.in' => 'Selecciona un estado válido.',
        ]);

        $data = [
            'placa' => mb_strtoupper(trim($validated['placa'])),
            'external_id' => filled($validated['externalId'] ?? null) ? trim($validated['externalId']) : null,
            'tipo' => filled($validated['tipo'] ?? null) ? mb_strtoupper(trim($validated['tipo'])) : null,
            'modelo' => filled($validated['modelo'] ?? null) ? mb_strtoupper(trim($validated['modelo'])) : null,
            'descripcion' => filled($validated['descripcion'] ?? null) ? trim($validated['descripcion']) : null,
            'estado' => $validated['estado'],
        ];
        $changes = null;

        $wasEditing = $this->editingId !== null;
        if (! $wasEditing) {
            $unit = Vehiculo::create($data + ['source' => 'manual']);
            $action = 'crear_unidad';
            $description = 'Registro de unidad '.$unit->placa;
        } else {
            $unit = Vehiculo::query()->findOrFail($this->editingId);
            $changes = [];
            foreach ($data as $field => $newValue) {
                if ((string) ($unit->{$field} ?? '') !== (string) ($newValue ?? '')) {
                    $changes[$field] = ['anterior' => $unit->{$field}, 'nuevo' => $newValue];
                }
            }
            $unit->update($data);
            $action = 'actualizar_unidad';
            $description = 'Actualización de unidad '.$unit->placa;
        }

        Log::create([
            'accion' => $action,
            'entidad' => 'unidades',
            'entidad_id' => $unit->id,
            'descripcion' => $description,
            'user_id' => Auth::id(),
            'entidad_resumen' => 'Unidad '.$unit->placa,
            'cambios' => $changes,
        ]);

        $this->resetForm();
        session()->flash('units-message', 'Unidad guardada correctamente.');
        if ($wasEditing) {
            $this->dispatch('showModule', 'admin.units.consulta');
        }
    }

    public function edit(int $id): void
    {
        $this->authorizeAdministrator();
        if ($this->section === 'consulta') {
            Vehiculo::query()->findOrFail($id);
            $this->dispatch('editUnit', $id);

            return;
        }

        $this->loadUnit($id);
    }

    private function loadUnit(int $id): void
    {
        $unit = Vehiculo::query()->findOrFail($id);
        $this->editingId = $unit->id;
        $this->placa = $unit->placa;
        $this->externalId = $unit->external_id ?? '';
        $this->tipo = $unit->tipo ?? '';
        $this->modelo = $unit->modelo ?? '';
        $this->descripcion = $unit->descripcion ?? '';
        $this->estado = $unit->estado;
    }

    public function toggleActive(int $id): void
    {
        $this->authorizeAdministrator();
        abort_unless($this->section === 'consulta', 404);
        $unit = Vehiculo::query()->findOrFail($id);
        $previousState = $unit->estado;
        $unit->update(['estado' => $previousState === 'activa' ? 'inactiva' : 'activa']);

        Log::create([
            'accion' => 'cambiar_estado_unidad',
            'entidad' => 'vehiculo',
            'entidad_id' => $unit->id,
            'descripcion' => 'Cambio de estado de unidad '.$unit->placa.': '.$previousState.' a '.$unit->estado,
            'user_id' => Auth::id(),
            'entidad_resumen' => 'Unidad '.$unit->placa,
            'cambios' => ['estado' => ['anterior' => $previousState, 'nuevo' => $unit->estado]],
        ]);

        session()->flash('units-message', $unit->estado === 'activa' ? 'Unidad activada.' : 'Unidad desactivada.');
    }

    public function cancelEdit(): void
    {
        $this->authorizeAdministrator();
        $wasEditing = $this->editingId !== null;
        $this->resetForm();
        if ($wasEditing) {
            $this->dispatch('showModule', 'admin.units.consulta');
        }
    }

    public function showHistory(int $id): void
    {
        $this->authorizeAdministrator();
        abort_unless($this->section === 'consulta', 404);
        Vehiculo::query()->findOrFail($id);
        $this->historyUnitId = $id;
    }

    public function closeHistory(): void
    {
        $this->authorizeAdministrator();
        abort_unless($this->section === 'consulta', 404);
        $this->historyUnitId = null;
    }

    public function analyzeImport(): void
    {
        $this->authorizeAdministrator();
        abort_unless($this->section === 'registro', 404);
        $this->validate([
            'file' => 'required|file|mimes:xlsx,csv,txt|max:10240',
        ], [
            'file.required' => 'Selecciona un archivo para importar.',
            'file.mimes' => 'El archivo debe ser XLSX o CSV.',
            'file.max' => 'El archivo no puede superar los 10 MB.',
        ]);

        $service = app(UnitsSpreadsheet::class);
        try {
            $rows = $service->read($this->file->getRealPath(), $this->file->getClientOriginalExtension());
        } catch (\RuntimeException $exception) {
            $this->addError('file', $exception->getMessage());

            return;
        }

        $this->previewPlan = $service->plan($rows);
        $this->previewCounts = collect($this->previewPlan)->countBy('action')->all();
        $this->importMessage = null;
    }

    public function applyImport(): void
    {
        $this->authorizeAdministrator();
        abort_unless($this->section === 'registro', 404);
        $this->validate([
            'file' => 'required|file|mimes:xlsx,csv,txt|max:10240',
        ]);

        $service = app(UnitsSpreadsheet::class);
        try {
            $rows = $service->read($this->file->getRealPath(), $this->file->getClientOriginalExtension());
        } catch (\RuntimeException $exception) {
            $this->addError('file', $exception->getMessage());

            return;
        }

        $counts = $service->apply($service->plan($rows));
        Log::create([
            'accion' => 'importar_unidades',
            'entidad' => 'vehiculo',
            'entidad_id' => 0,
            'descripcion' => 'Importación de unidades: '.$counts['creadas'].' creadas, '.$counts['actualizadas'].' actualizadas, '.$counts['sin_cambios'].' sin cambios y '.$counts['errores'].' errores.',
            'user_id' => Auth::id(),
            'entidad_resumen' => 'Importación del catálogo de unidades',
            'cambios' => $counts,
        ]);

        $this->importMessage = 'Importación finalizada: '.$counts['creadas'].' creadas, '.$counts['actualizadas'].' actualizadas, '.$counts['sin_cambios'].' sin cambios y '.$counts['errores'].' errores.';
        $this->previewPlan = [];
        $this->previewCounts = null;
        $this->reset('file');
        $this->resetPage();
    }

    public function downloadTemplate()
    {
        abort_unless($this->section === 'registro', 404);

        return $this->downloadSpreadsheet('plantilla-unidades.xlsx', 'template');
    }

    public function export()
    {
        abort_unless($this->section === 'consulta', 404);

        return $this->downloadSpreadsheet('unidades.xlsx', 'export');
    }

    public function exportCsv()
    {
        abort_unless($this->section === 'consulta', 404);

        return $this->downloadSpreadsheet('unidades.csv', 'exportCsv');
    }

    public function render()
    {
        $this->authorizeAdministrator();
        if ($this->section === 'registro') {
            return view('livewire.admin.units-register', [
                'statuses' => Vehiculo::ESTADOS,
            ]);
        }

        $units = Vehiculo::query()
            ->withCount(['salidas'])
            ->when($this->search !== '', function ($query): void {
                $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim($this->search)).'%';
                $query->where(function ($nested) use ($term): void {
                    $nested->where('placa', 'like', $term)
                        ->orWhere('external_id', 'like', $term)
                        ->orWhere('descripcion', 'like', $term);
                });
            })
            ->when($this->statusFilter !== '', fn ($query) => $query->where('estado', $this->statusFilter))
            ->orderBy('placa')
            ->paginate(10);

        return view('livewire.admin.units-consulta', [
            'units' => $units,
            'statuses' => Vehiculo::ESTADOS,
            'historyUnit' => $this->historyUnitId
                ? Vehiculo::query()->with(['salidas.item', 'salidas.user'])->findOrFail($this->historyUnitId)
                : null,
        ]);
    }

    private function downloadSpreadsheet(string $filename, string $method)
    {
        $this->authorizeAdministrator();
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $path = sys_get_temp_dir().'\\'.Str::uuid().'.'.$extension;
        app(UnitsSpreadsheet::class)->{$method}($path);

        return response()->download($path, $filename)->deleteFileAfterSend(true);
    }

    private function resetForm(): void
    {
        $this->reset([
            'editingId', 'placa', 'externalId', 'tipo', 'modelo', 'descripcion',
        ]);
        $this->estado = 'activa';
        $this->resetValidation();
    }

    private function authorizeAdministrator(): void
    {
        abort_unless(in_array(Auth::user()?->role, ['administrador', 'superadministrador'], true), 403);
    }
}
