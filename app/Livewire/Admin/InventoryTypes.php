<?php

namespace App\Livewire\Admin;

use App\Models\InventoryType;
use Illuminate\Validation\Rule;
use Livewire\Component;

class InventoryTypes extends Component
{
    public ?int $editingId = null;

    public string $name = '';

    public string $keywordsText = '';

    public function mount(): void
    {
        $this->authorizeAdministrator();
    }

    public function save(): void
    {
        $this->authorizeAdministrator();
        $validated = $this->validate([
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('inventory_types', 'name')->ignore($this->editingId),
            ],
            'keywordsText' => 'nullable|string|max:2000',
        ], [
            'name.required' => 'El nombre del tipo es obligatorio.',
            'name.unique' => 'Ya existe un tipo con ese nombre.',
        ]);

        $keywords = collect(preg_split('/[,;\r\n]+/', $validated['keywordsText'] ?? ''))
            ->map(fn (string $keyword): string => trim($keyword))
            ->filter()
            ->unique(fn (string $keyword): string => mb_strtolower($keyword))
            ->values()
            ->all();

        $attributes = [
            'name' => trim($validated['name']),
            'keywords' => $keywords,
        ];
        if ($this->editingId === null) {
            InventoryType::create($attributes);
        } else {
            InventoryType::query()->findOrFail($this->editingId)->update($attributes);
        }

        $this->resetForm();
        session()->flash('inventory-type-message', 'Tipo guardado correctamente.');
    }

    public function edit(int $id): void
    {
        $this->authorizeAdministrator();
        $type = InventoryType::findOrFail($id);
        $this->editingId = $type->id;
        $this->name = $type->name;
        $this->keywordsText = implode(', ', $type->keywords ?? []);
    }

    public function toggleActive(int $id): void
    {
        $this->authorizeAdministrator();
        $type = InventoryType::findOrFail($id);
        $type->update(['is_active' => ! $type->is_active]);
        session()->flash('inventory-type-message', $type->is_active
            ? 'Tipo activado.'
            : 'Tipo desactivado; las asignaciones históricas se conservan.');
    }

    public function cancelEdit(): void
    {
        $this->authorizeAdministrator();
        $this->resetForm();
    }

    public function render()
    {
        $this->authorizeAdministrator();

        return view('livewire.admin.inventory-types', [
            'types' => InventoryType::query()
                ->withCount('inventoryItems')
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get(),
        ]);
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'keywordsText']);
        $this->resetValidation();
    }

    private function authorizeAdministrator(): void
    {
        abort_unless(in_array(auth()->user()?->role, ['administrador', 'superadministrador'], true), 403);
    }
}
