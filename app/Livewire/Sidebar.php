<?php

namespace App\Livewire;

use App\Models\SpareRequest;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Sidebar extends Component
{
    public $selected = null;

    #[Locked]
    public bool $standalonePage = false;

    protected $listeners = [
        'showModule' => 'setSelected',
        'editUnit' => 'openUnitRegistration',
    ];

    public function mount(bool $standalonePage = false): void
    {
        $this->standalonePage = $standalonePage;

        $module = request()->query('module');
        if (is_string($module)) {
            $this->selected = $module;
        }
    }

    public function setSelected($module)
    {
        $this->selected = $module;
    }

    public function openUnitRegistration(int $unitId): void
    {
        $this->selected = 'admin.units.registro';
    }

    public function select($module)
    {
        $this->selected = $module;
        if ($this->standalonePage) {
            $this->redirectRoute('dashboard', ['module' => $module]);

            return;
        }

        $this->dispatch('showModule', $module);
    }

    public function goHome(): void
    {
        $this->selected = null;
        if ($this->standalonePage) {
            $this->redirectRoute('dashboard');

            return;
        }

        $this->dispatch('showHome');
    }

    public function render()
    {
        $pendingRequests = 0;
        if (auth()->user()?->role === 'gerente') {
            $pendingRequests = SpareRequest::query()
                ->where('status', 'pending')
                ->where(function ($query) {
                    $query->whereNull('tipo')->orWhere('tipo', '<>', 'eliminacion');
                })
                ->count();
        }

        return view('livewire.sidebar', [
            'selected' => $this->selected,
            'pendingRequests' => $pendingRequests,
        ]);
    }
}
