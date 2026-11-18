<?php

namespace App\Livewire\Almacenista;

use App\Models\SpareRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\Log;
use Livewire\Component;

class Solicitudes extends Component
{
    public string $statusFilter = 'all';

    public function setStatusFilter(string $filter): void
    {
        abort_unless(in_array($filter, ['all', 'pending', 'approved', 'received', 'rejected'], true), 422);

        $this->statusFilter = $filter;
    }

    public function newRequest(): void
    {
        $this->dispatch('showModule', 'almacenista.solicitar_repuestos');
    }

    public function render()
    {
        $baseQuery = SpareRequest::query()
            ->where('almacenista_id', Auth::id())
            ->where(function($q){
                $q->whereNull('tipo')->orWhere('tipo','<>','eliminacion');
            });
        $statusCounts = (clone $baseQuery)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');
        $requests = (clone $baseQuery)
            ->with(['purchaseOrder', 'items'])
            ->when($this->statusFilter !== 'all', fn ($query) => $query->where('status', $this->statusFilter))
            ->orderByDesc('created_at')
            ->get();

        return view('livewire.almacenista.solicitudes', [
            'requests' => $requests,
            'statusCounts' => [
                'all' => (int) $statusCounts->sum(),
                'pending' => (int) ($statusCounts['pending'] ?? 0),
                'approved' => (int) ($statusCounts['approved'] ?? 0),
                'received' => (int) ($statusCounts['received'] ?? 0),
                'rejected' => (int) ($statusCounts['rejected'] ?? 0),
            ],
            'almacenistaName' => Auth::user()?->name,
        ]);
    }

    /**
     * Confirmar que el repuesto fue recibido por el almacenista.
     */
    public function confirmReceived($id)
    {
        $req = SpareRequest::find($id);
        if (!$req) {
            $this->dispatch('notification', type: 'error', message: 'Solicitud no encontrada.');
            return;
        }

        if ($req->almacenista_id !== Auth::id()) {
            $this->dispatch('notification', type: 'error', message: 'No autorizado.');
            return;
        }

        // Sólo permitir confirmar si la solicitud ya fue aprobada por el gerente
        if ($req->status !== 'approved') {
            $this->dispatch('notification', type: 'info', message: 'No se puede confirmar hasta que la solicitud sea aprobada.');
            return;
        }

        $req->status = 'received';
        $req->save();

        // Log
        Log::create([
            'accion' => 'confirmar_recibido',
            'entidad' => 'spare_request',
            'entidad_id' => $req->id,
            'descripcion' => 'Solicitud marcada como recibida por almacenista',
            'user_id' => Auth::id(),
        ]);

        $this->dispatch('notification', type: 'success', message: 'Recibió correctamente el repuesto.');
        $this->dispatch('refresh')->self();
    }

    /**
     * Añadir o actualizar un comentario a la solicitud.
     */
    public function addComment($id, $comment)
    {
        Validator::make(['comment' => $comment], [
            'comment' => 'nullable|string|max:2000'
        ])->validate();

        $req = SpareRequest::find($id);
        if (!$req) {
            $this->dispatch('notification', type: 'error', message: 'Solicitud no encontrada.');
            return;
        }

        if ($req->almacenista_id !== Auth::id()) {
            $this->dispatch('notification', type: 'error', message: 'No autorizado.');
            return;
        }

        $req->comentario = $comment;
        $req->save();

        // Log comentario
        Log::create([
            'accion' => 'comentar_solicitud',
            'entidad' => 'spare_request',
            'entidad_id' => $req->id,
            'descripcion' => 'Comentario: ' . substr($comment, 0, 500),
            'user_id' => Auth::id(),
        ]);

        $this->dispatch('notification', type: 'success', message: 'Comentario guardado.');
        $this->dispatch('refresh')->self();
    }
}
