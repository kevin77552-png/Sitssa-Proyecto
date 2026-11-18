<?php

namespace App\Livewire\Almacenista;

use App\Models\SpareRequest;
use App\Models\Log;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class SolicitarRepuestos extends Component
{
    public int $cantidad = 1;
    public string $descripcion = '';
    public string $detalles = '';
    public string $solicitadoPor = '';
    public string $prioridad = 'NORMAL';
    public array $items = [];

    public ?string $successMessage = null;

    public function mount()
    {
        $this->cantidad = 1;
        $this->items = [$this->emptyItem()];
    }

    public function adjustQuantity(int $delta): void
    {
        $quantity = filter_var($this->cantidad, FILTER_VALIDATE_INT);
        $current = $quantity === false ? 1 : $quantity;

        $this->cantidad = max(1, min(999, $current + $delta));
        $this->resetValidation('cantidad');
    }

    public function addItem(): void
    {
        if (count($this->items) >= 20) {
            $this->addError('items', 'Una solicitud puede incluir hasta 20 repuestos.');
            return;
        }

        $this->items[] = $this->emptyItem();
        $this->resetValidation('items');
    }

    public function removeItem(int $index): void
    {
        if (count($this->items) <= 1 || ! array_key_exists($index, $this->items)) {
            return;
        }

        unset($this->items[$index]);
        $this->items = array_values($this->items);
        $this->resetValidation();
    }

    public function adjustItemQuantity(int $index, int $delta): void
    {
        if (! array_key_exists($index, $this->items)) {
            return;
        }

        $quantity = filter_var($this->items[$index]['cantidad'] ?? 1, FILTER_VALIDATE_INT);
        $current = $quantity === false ? 1 : $quantity;
        $this->items[$index]['cantidad'] = max(1, min(999, $current + $delta));
        $this->resetValidation("items.{$index}.cantidad");
    }

    public function clearForm(): void
    {
        $this->reset(['descripcion', 'detalles', 'solicitadoPor', 'prioridad', 'successMessage']);
        $this->cantidad = 1;
        $this->items = [$this->emptyItem()];
        $this->resetValidation();
    }

    public function submit()
    {
        $this->useLegacyItemInputWhenNeeded();
        $validated = $this->validate([
            'items' => 'required|array|min:1|max:20',
            'items.*._key' => 'required|string|max:36',
            'items.*.descripcion' => 'required|string|max:255',
            'items.*.cantidad' => 'required|integer|min:1|max:999',
            'items.*.detalles' => 'nullable|string|max:1000',
            'solicitadoPor' => 'required|string|max:255',
            'prioridad' => 'required|in:NORMAL,URGENTE',
        ]);

        $user = Auth::user();
        $items = collect($validated['items'])
            ->map(fn ($item) => Arr::only($item, ['descripcion', 'cantidad', 'detalles']))
            ->values();
        $requestedBy = $validated['solicitadoPor'];
        $description = $items->count() === 1
            ? $items->first()['descripcion']
            : \Illuminate\Support\Str::limit($items->pluck('descripcion')->implode(' / '), 255);
        $totalQuantity = $items->sum(fn ($item) => (int) $item['cantidad']);

        $spare = DB::transaction(function () use ($items, $description, $totalQuantity, $requestedBy, $user) {
            $spare = SpareRequest::create([
                'cantidad' => $totalQuantity,
                'descripcion' => $description,
                'solicitado_por' => $requestedBy,
                'prioridad' => $this->prioridad,
                'almacenista_id' => $user->id,
            ]);

            $spare->items()->createMany($items->all());

            return $spare;
        });

        // Registrar histórico
        Log::create([
            'accion' => 'crear_solicitud',
            'entidad' => 'spare_request',
            'entidad_id' => $spare->id,
            'descripcion' => 'Solicitud creada: '.$description.'; Solicitada por: '.$spare->solicitado_por,
            'user_id' => $user->id,
        ]);

        $this->clearForm();
        $this->successMessage = 'Solicitud creada correctamente.';

        // Emitir un evento Livewire v3 para notificaciones/actualizaciones front-end
        // (reemplaza dispatchBrowserEvent que puede no existir en esta versión).
        $this->dispatch('spare-request-created', ['id' => $spare->id]);
    }

    public function render()
    {
        $requests = SpareRequest::where('almacenista_id', Auth::id())
            ->with('items')
            ->where(function($q) {
                $q->where('tipo', '!=', 'eliminacion')
                  ->orWhereNull('tipo');
            })
            ->orderBy('created_at', 'desc')
            ->take(8)
            ->get();

        return view('livewire.almacenista.solicitar-repuestos', [
            'requests' => $requests,
            'almacenistaName' => Auth::user() ? Auth::user()->name : null,
        ]);
    }

    private function emptyItem(): array
    {
        return [
            '_key' => (string) Str::uuid(),
            'descripcion' => '',
            'cantidad' => 1,
            'detalles' => '',
        ];
    }

    private function useLegacyItemInputWhenNeeded(): void
    {
        if (
            count($this->items) === 1
            && trim((string) ($this->items[0]['descripcion'] ?? '')) === ''
            && trim($this->descripcion) !== ''
        ) {
            $this->items[0] = [
                '_key' => $this->items[0]['_key'] ?? (string) Str::uuid(),
                'descripcion' => $this->descripcion,
                'cantidad' => $this->cantidad,
                'detalles' => $this->detalles,
            ];
        }
    }
}
