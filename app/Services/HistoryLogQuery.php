<?php

namespace App\Services;

use App\Models\Log;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class HistoryLogQuery
{
    public static function build(array $filters, bool $ignoreDateFilters = false): Builder
    {
        $query = Log::query();

        if (($filters['entityFilter'] ?? '') !== '') {
            $query->where('entidad', $filters['entityFilter']);
        }

        if (($filters['userFilter'] ?? '') !== '') {
            $query->where('user_id', $filters['userFilter']);
        }

        if (($filters['actionFilter'] ?? '') !== '') {
            $query->where('accion', $filters['actionFilter']);
        }

        if (trim((string) ($filters['search'] ?? '')) !== '') {
            $search = '%'.trim((string) $filters['search']).'%';
            $query->where(function (Builder $matches) use ($search): void {
                $matches->where('descripcion', 'like', $search)
                    ->orWhere('entidad_resumen', 'like', $search)
                    ->orWhere('actor_name', 'like', $search)
                    ->orWhereHas('user', fn (Builder $userQuery) => $userQuery->where('name', 'like', $search));
            });
        }

        if (! $ignoreDateFilters) {
            if (($filters['dateFrom'] ?? '') !== '') {
                $query->where('created_at', '>=', $filters['dateFrom'].' 00:00:00');
            }

            if (($filters['dateTo'] ?? '') !== '') {
                $query->where('created_at', '<', Carbon::parse($filters['dateTo'])->addDay()->startOfDay());
            }
        }

        return $query;
    }
}
