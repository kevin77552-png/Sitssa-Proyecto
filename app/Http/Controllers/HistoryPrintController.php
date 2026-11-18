<?php

namespace App\Http\Controllers;

use App\Livewire\Admin\History;
use App\Models\Log;
use App\Models\User;
use App\Services\HistoryLogQuery;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class HistoryPrintController extends Controller
{
    public function __invoke(Request $request): View
    {
        abort_unless(in_array(auth()->user()?->role, ['administrador', 'superadministrador'], true), 403);

        $filters = validator($request->query(), [
            'entityFilter' => ['nullable', 'string', 'max:80'],
            'actionFilter' => ['nullable', Rule::in(array_keys(History::ACTION_LABELS))],
            'userFilter' => ['nullable', 'integer'],
            'dateFrom' => ['nullable', 'date_format:Y-m-d'],
            'dateTo' => ['nullable', 'date_format:Y-m-d'],
            'search' => ['nullable', 'string', 'max:200'],
        ])->validate();

        if (
            filled($filters['dateFrom'] ?? null)
            && filled($filters['dateTo'] ?? null)
            && $filters['dateFrom'] > $filters['dateTo']
        ) {
            throw ValidationException::withMessages([
                'dateTo' => 'La fecha "Hasta" debe ser igual o posterior a "Desde".',
            ]);
        }

        $logs = HistoryLogQuery::build($filters)
            ->with('user')
            ->orderByDesc('created_at')
            ->paginate(100)
            ->withQueryString();

        $filterSummary = [];
        if (filled($filters['entityFilter'] ?? null)) {
            $filterSummary[] = 'Entidad: '.Log::entityLabel($filters['entityFilter']);
        }
        if (filled($filters['actionFilter'] ?? null)) {
            $filterSummary[] = 'Acción: '.History::ACTION_LABELS[$filters['actionFilter']];
        }
        if (filled($filters['userFilter'] ?? null)) {
            $user = User::query()->find($filters['userFilter']);
            $filterSummary[] = 'Usuario: '.($user?->name ?? 'Usuario #'.$filters['userFilter']);
        }
        if (filled($filters['dateFrom'] ?? null)) {
            $filterSummary[] = 'Desde: '.Carbon::parse($filters['dateFrom'])->format('d/m/Y');
        }
        if (filled($filters['dateTo'] ?? null)) {
            $filterSummary[] = 'Hasta: '.Carbon::parse($filters['dateTo'])->format('d/m/Y');
        }
        if (filled($filters['search'] ?? null)) {
            $filterSummary[] = 'Búsqueda: '.$filters['search'];
        }

        return view('admin.history-print', [
            'logs' => $logs,
            'actions' => History::ACTION_LABELS,
            'filterSummary' => $filterSummary,
            'printedAt' => now(),
        ]);
    }
}
