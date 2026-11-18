<?php

namespace App\Livewire;

use App\Models\Vehiculo;
use Livewire\Attributes\Locked;
use Livewire\Component;

class DynamicModule extends Component
{
    private const MODULE_ROLES = [
        'admin.users' => ['administrador', 'superadministrador'],
        'admin.users.register' => ['administrador', 'superadministrador'],
        'admin.users.list' => ['administrador', 'superadministrador'],
        'admin.inventory.ingreso' => ['administrador', 'superadministrador', 'almacenista'],
        'admin.inventory.egreso' => ['administrador', 'superadministrador', 'almacenista'],
        'admin.inventory.consulta' => ['administrador', 'superadministrador', 'almacenista'],
        'admin.inventory.types' => ['administrador', 'superadministrador'],
        'admin.units.registro' => ['administrador', 'superadministrador'],
        'admin.units.consulta' => ['administrador', 'superadministrador'],
        'admin.profile' => ['administrador', 'superadministrador', 'gerente', 'almacenista', 'proveedor'],
        'admin.history' => ['administrador', 'superadministrador'],
        'admin.reports' => ['administrador', 'superadministrador', 'gerente'],
        'admin.movements' => ['administrador', 'superadministrador', 'gerente', 'almacenista'],
        'admin.migration' => ['administrador', 'superadministrador'],
        'provider.purchase_orders' => ['proveedor'],
        'admin.purchase_orders' => ['administrador', 'superadministrador', 'gerente'],
        'admin.purchase_orders.consultar' => ['administrador', 'superadministrador'],
        'gerente.purchase_orders.consultar' => ['gerente'],
        'admin.settings' => ['administrador', 'superadministrador'],
        'admin.settings.registro' => ['superadministrador'],
        'gerente.solicitudes' => ['gerente'],
        'gerente.eliminaciones' => ['gerente'],
        'almacenista.solicitar_repuestos' => ['almacenista'],
        'almacenista.solicitudes' => ['almacenista'],
        'wiki.ayuda' => ['administrador', 'superadministrador', 'gerente', 'almacenista', 'proveedor'],
    ];

    public $module = null;

    #[Locked]
    public ?int $unitId = null;

    protected $listeners = ['showModule', 'showHome', 'editUnit'];

    public function showModule($module): void
    {
        abort_unless(is_string($module) && isset(self::MODULE_ROLES[$module]), 404);
        $this->authorizeModule($module);
        $this->module = $module;
        $this->unitId = null;
    }

    public function editUnit(int $id): void
    {
        $this->authorizeModule('admin.units.registro');
        Vehiculo::query()->findOrFail($id);
        $this->module = 'admin.units.registro';
        $this->unitId = $id;
    }

    public function showHome(): void
    {
        $this->module = null;
        $this->unitId = null;
    }

    public function mount(): void
    {
        $module = request()->query('module');
        if ($module !== null) {
            $this->showModule($module);
        }
    }

    public function render()
    {
        if ($this->module !== null) {
            $this->authorizeModule($this->module);
        }

        return view('livewire.dynamic-module', [
            'module' => $this->module,
            'unitId' => $this->unitId,
        ]);
    }

    private function authorizeModule(string $module): void
    {
        abort_unless(
            in_array(auth()->user()?->role, self::MODULE_ROLES[$module] ?? [], true),
            403
        );
    }
}
