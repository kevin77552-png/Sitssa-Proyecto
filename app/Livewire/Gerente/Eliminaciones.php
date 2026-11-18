<?php

namespace App\Livewire\Gerente;

use Livewire\Component;
use App\Models\SpareRequest;
use App\Services\InventoryMovementRecorder;
use App\Models\InventoryItem;
use App\Models\Log;
use Illuminate\Support\Facades\DB;

class Eliminaciones extends Component
{
    public $showRejectModal = false;
    public $rejectId = null;
    public $rejectReason = '';
    public $processingId = null;
    public $statusFilter = 'todos';
    public string $search = '';
    public ?int $actionId = null;
    public string $actionType = '';
    public string $auditNote = '';

    public function render()
    {
        $this->authorizeManager();
        abort_unless(in_array($this->statusFilter, ['todos', 'por_aprobar', 'aprobada', 'rechazada'], true), 422);

        $baseQuery = SpareRequest::query()
            ->where('tipo', 'eliminacion')
            ->with(['inventoryItem', 'almacenista']);
        $statusCounts = (clone $baseQuery)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $search = trim($this->search);
        $pendingCount = $statusCounts
            ->reject(fn ($count, $status) => in_array($status, ['aprobada', 'rechazada'], true))
            ->sum();
        $requests = (clone $baseQuery)
            ->when($this->statusFilter === 'por_aprobar', fn ($query) => $query->whereNotIn('status', ['aprobada', 'rechazada']))
            ->when(in_array($this->statusFilter, ['aprobada', 'rechazada'], true), fn ($query) => $query->where('status', $this->statusFilter))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('detalles', 'like', '%'.$search.'%')
                        ->orWhere('descripcion', 'like', '%'.$search.'%')
                        ->orWhere('reject_reason', 'like', '%'.$search.'%')
                        ->orWhereHas('almacenista', fn ($userQuery) => $userQuery->where('name', 'like', '%'.$search.'%'))
                        ->orWhereHas('inventoryItem', function ($itemQuery) use ($search) {
                            $itemQuery->where('sigicov', 'like', '%'.$search.'%')
                                ->orWhere('descripcion', 'like', '%'.$search.'%')
                                ->orWhere('pasillo', 'like', '%'.$search.'%')
                                ->orWhere('estante', 'like', '%'.$search.'%')
                                ->orWhere('peldaño', 'like', '%'.$search.'%');
                        });
                });
            })
            ->orderBy('created_at', 'desc')
            ->get();

        return view('livewire.gerente.eliminaciones', [
            'requests' => $requests,
            'statusCounts' => [
                'todos' => (int) $statusCounts->sum(),
                'por_aprobar' => (int) $pendingCount,
                'aprobada' => (int) ($statusCounts['aprobada'] ?? 0),
                'rechazada' => (int) ($statusCounts['rechazada'] ?? 0),
            ],
        ]);
    }

    public function setStatusFilter(string $filter): void
    {
        abort_unless(in_array($filter, ['todos', 'por_aprobar', 'aprobada', 'rechazada'], true), 422);

        $this->statusFilter = $filter;
    }

    public function openApproveModal(int $id): void
    {
        $this->authorizeManager();
        abort_unless(
            SpareRequest::where('tipo', 'eliminacion')->where('status', 'pendiente')->whereKey($id)->exists(),
            404
        );

        $this->actionId = $id;
        $this->actionType = 'aprobar';
        $this->auditNote = '';
        $this->showRejectModal = true;
        $this->processingId = $id;
    }

    public function approve($id, ?string $auditNote = null)
    {
        $this->authorizeManager();
        $this->processingId = $id;
        $auditNote = trim((string) $auditNote);
        validator(
            ['auditNote' => $auditNote],
            ['auditNote' => 'nullable|string|max:1000']
        )->validate();

        DB::transaction(function () use ($id, $auditNote) {
            $sr = SpareRequest::where('tipo', 'eliminacion')
                ->lockForUpdate()
                ->findOrFail($id);
            abort_unless($sr->status === 'pendiente', 409);

            $item = InventoryItem::lockForUpdate()->findOrFail($sr->inventory_item_id);
            $oldInv = (int) $item->inventario;
            $toRemove = (int) $sr->cantidad;
            abort_unless($toRemove > 0 && $toRemove <= $oldInv, 409);
            $newInv = $oldInv - $toRemove;

            $item->inventario = $newInv;
            $item->save();

            app(InventoryMovementRecorder::class)->record(
                item: $item,
                tipo: 'ajuste',
                delta: -$toRemove,
                stockBefore: $oldInv,
                motivo: "Eliminación aprobada (solicitud {$sr->id})",
                referenciaTipo: 'spare_request',
                referenciaId: (string) $sr->id,
            );

            Log::create([
                'accion' => 'ajustar_inventario_por_eliminacion',
                'entidad' => 'inventory_item',
                'entidad_id' => $item->id,
                'descripcion' => "Solicitud {$sr->id}: inventario {$oldInv} => {$newInv} (se removieron {$toRemove})",
                'user_id' => auth()->id(),
            ]);

            $sr->status = 'aprobada';
            $sr->save();

            Log::create([
                'accion' => 'aprobar_eliminacion',
                'entidad' => 'spare_request',
                'entidad_id' => $sr->id,
                'descripcion' => 'Solicitud de eliminacion aprobada por gerente'.($auditNote !== '' ? '. Nota: '.$auditNote : ''),
                'user_id' => auth()->id(),
            ]);

            if ($newInv === 0) {
                $item->delete();
            }
        });

        session()->flash('success', 'Solicitud aprobada y cantidad restada del inventario.');

        $this->showRejectModal = false;
        $this->actionId = null;
        $this->actionType = '';
        $this->auditNote = '';
        $this->processingId = null;
    }

    public function openRejectModal($id)
    {
        $this->authorizeManager();
        abort_unless(
            SpareRequest::where('tipo', 'eliminacion')->where('status', 'pendiente')->whereKey($id)->exists(),
            404
        );
        $this->rejectId = $id;
        $this->rejectReason = '';
        $this->actionId = $id;
        $this->actionType = 'rechazar';
        $this->auditNote = '';
        $this->showRejectModal = true;
        $this->processingId = $id;
    }

    public function cancelReject()
    {
        $this->authorizeManager();
        $this->showRejectModal = false;
        $this->rejectId = null;
        $this->rejectReason = '';
        $this->actionId = null;
        $this->actionType = '';
        $this->auditNote = '';
        $this->processingId = null;
    }

    public function confirmAction(): void
    {
        $this->authorizeManager();
        abort_unless($this->actionId && in_array($this->actionType, ['aprobar', 'rechazar'], true), 422);

        if ($this->actionType === 'aprobar') {
            $this->approve($this->actionId, $this->auditNote);

            return;
        }

        $this->rejectId = $this->actionId;
        $this->rejectReason = $this->auditNote;
        $this->confirmReject();
    }

    public function confirmReject()
    {
        $this->authorizeManager();
        $this->validate(['rejectReason' => 'required|string|min:5|max:1000']);

        $sr = SpareRequest::where('tipo', 'eliminacion')
            ->where('status', 'pendiente')
            ->findOrFail($this->rejectId);

        $sr->status = 'rechazada';
        $sr->reject_reason = $this->rejectReason;
        $sr->save();

        Log::create([
            'accion' => 'rechazar_eliminacion',
            'entidad' => 'spare_request',
            'entidad_id' => $sr->id,
            'descripcion' => "Motivo: {$this->rejectReason}",
            'user_id' => auth()->id(),
        ]);

        $this->showRejectModal = false;
        $this->rejectId = null;
        $this->rejectReason = '';
        $this->actionId = null;
        $this->actionType = '';
        $this->auditNote = '';
        $this->processingId = null;

        session()->flash('success', 'Solicitud rechazada.');
    }

    private function authorizeManager(): void
    {
        abort_unless(auth()->user()?->role === 'gerente', 403);
    }
}
