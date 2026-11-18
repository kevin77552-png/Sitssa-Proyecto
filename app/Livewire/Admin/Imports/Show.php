<?php

namespace App\Livewire\Admin\Imports;

use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\InventoryType;
use App\Services\Import\ImportBatchService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use RuntimeException;

#[Layout('layouts.app')]
class Show extends Component
{
    use WithPagination;

    public ImportBatch $batch;

    public string $rowStatus = 'all';

    public string $search = '';

    public ?string $actionError = null;

    public function setRowType(int $rowId, string $typeId): void
    {
        $this->authorizeAdministrator();
        $this->actionError = null;

        if ($this->batch->status !== 'ready') {
            throw new RuntimeException('La clasificación solo puede cambiarse antes de aplicar el lote.');
        }

        $row = $this->batch->rows()->whereKey($rowId)->where('status', 'new')->firstOrFail();
        $selectedTypeId = $typeId === '' ? null : filter_var($typeId, FILTER_VALIDATE_INT);
        if ($selectedTypeId === false) {
            $this->addError("rowTypes.{$rowId}", 'Selecciona un tipo válido del catálogo.');

            return;
        }
        if ($selectedTypeId !== null && ! InventoryType::query()->whereKey($selectedTypeId)->where('is_active', true)->exists()) {
            $this->addError("rowTypes.{$rowId}", 'El tipo seleccionado ya no está activo.');

            return;
        }

        $row->update(['inventory_type_id' => $selectedTypeId]);
        $this->resetValidation("rowTypes.{$rowId}");
    }

    public function mount(ImportBatch $batch): void
    {
        $this->authorizeAdministrator();
        $this->batch = $batch;
    }

    public function apply(ImportBatchService $service): void
    {
        $this->authorizeAdministrator();
        $this->actionError = null;

        try {
            if ($this->batch->status !== 'ready' || (int) $this->batch->new_rows < 1) {
                throw new RuntimeException('No hay filas nuevas para aplicar.');
            }
            $service->queueApply($this->batch->fresh(), (int) Auth::id());
            session()->flash('message', 'Se inició la aplicación. El inventario solo cambia después de esta confirmación.');
        } catch (RuntimeException $exception) {
            $this->actionError = $exception->getMessage();
        }
    }

    public function revert(ImportBatchService $service): void
    {
        $this->authorizeAdministrator();
        $this->actionError = null;

        try {
            $service->queueRevert($this->batch->fresh(), (int) Auth::id());
            $this->batch->refresh();
            session()->flash('message', 'La solicitud de reversión fue recibida. Se verificará un respaldo antes de modificar el inventario.');
        } catch (RuntimeException $exception) {
            $this->actionError = $exception->getMessage();
        }
    }

    public function retry(ImportBatchService $service): void
    {
        $this->authorizeAdministrator();
        $this->actionError = null;

        try {
            $service->retryApply($this->batch->fresh(), (int) Auth::id());
            $this->batch->refresh();
            session()->flash('message', 'El lote está listo para aplicar nuevamente. Revisa el resumen y confirma Aplicar.');
        } catch (RuntimeException $exception) {
            $this->actionError = $exception->getMessage();
        }
    }

    public function updatedRowStatus(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $this->authorizeAdministrator();
        $this->batch->refresh();

        $statusCounts = $this->batch->rows()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');
        $incompleteLocationCount = $this->batch->rows()
            ->where(function ($locations): void {
                foreach (['pasillo', 'estante', 'peldaño'] as $field) {
                    $locations->orWhere("payload->{$field}", 'NO DISPONIBLE');
                }
            })
            ->count();
        $untypedNewRows = $this->batch->rows()
            ->where('status', 'new')
            ->whereNull('inventory_type_id')
            ->count();
        return view('livewire.admin.imports.show', [
            'rows' => $this->batch->rows()
                ->with(['inventoryType', 'suggestedInventoryType'])
                ->when($this->rowStatus === 'incomplete_location', function ($query): void {
                    $query->where(function ($locations): void {
                        foreach (['pasillo', 'estante', 'peldaño'] as $field) {
                            $locations->orWhere("payload->{$field}", 'NO DISPONIBLE');
                        }
                    });
                })
                ->when($this->rowStatus === 'invalid', fn ($query) => $query->whereIn('status', ['invalida', 'sin_sigicov']))
                ->when(! in_array($this->rowStatus, ['all', 'invalid', 'incomplete_location'], true), fn ($query) => $query->where('status', $this->rowStatus))
                ->when(trim($this->search) !== '', function ($query): void {
                    $search = '%'.trim($this->search).'%';
                    $query->where(function ($matches) use ($search): void {
                        $matches->where('sigicov', 'like', $search)
                            ->orWhere('payload->descripcion', 'like', $search)
                            ->orWhere('payload->pasillo', 'like', $search);
                    });
                })
                ->orderBy('excel_row')
                ->paginate(50),
            'rowStatusCounts' => [
                'all' => (int) $this->batch->rows_read,
                'new' => (int) ($statusCounts['new'] ?? 0),
                'existente' => (int) ($statusCounts['existente'] ?? 0),
                'repetido' => (int) ($statusCounts['repetido'] ?? 0),
                'invalid' => (int) ($statusCounts['invalida'] ?? 0) + (int) ($statusCounts['sin_sigicov'] ?? 0),
                'importado' => (int) ($statusCounts['importado'] ?? 0),
                'incomplete_location' => $incompleteLocationCount,
            ],
            'inventoryTypes' => InventoryType::query()->where('is_active', true)->orderBy('name')->get(),
            'untypedNewRows' => $untypedNewRows,
        ]);
    }

    private function authorizeAdministrator(): void
    {
        abort_unless(in_array(auth()->user()?->role, ['administrador', 'superadministrador'], true), 403);
    }
}
