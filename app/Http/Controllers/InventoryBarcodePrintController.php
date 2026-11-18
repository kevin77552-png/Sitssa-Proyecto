<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class InventoryBarcodePrintController extends Controller
{
    public function __invoke(Request $request): View
    {
        abort_unless(
            in_array(Auth::user()?->role, ['administrador', 'superadministrador', 'almacenista'], true),
            403
        );

        $rawIds = $request->query('ids');
        abort_unless(is_string($rawIds) && $rawIds !== '', 404);

        $validated = validator(
            ['ids' => explode(',', $rawIds)],
            [
                'ids' => ['required', 'array', 'min:1', 'max:50'],
                'ids.*' => ['required', 'integer', 'distinct', 'exists:inventory_items,id'],
            ],
        )->validate();

        $itemsById = InventoryItem::query()
            ->whereIn('id', $validated['ids'])
            ->whereNotNull('sigicov')
            ->where('sigicov', '<>', '')
            ->get(['id', 'sigicov', 'descripcion'])
            ->keyBy('id');

        abort_unless($itemsById->count() === count($validated['ids']), 404);

        $items = collect($validated['ids'])
            ->map(fn ($id) => $itemsById->get($id))
            ->values();

        return view('admin.inventory-barcode-sheet', [
            'items' => $items,
        ]);
    }
}
