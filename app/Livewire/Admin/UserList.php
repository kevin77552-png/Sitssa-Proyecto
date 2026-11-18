<?php

namespace App\Livewire\Admin;

use App\Models\Log;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class UserList extends Component
{
    public $filterName = '';

    public $filterRole = '';

    public function applyFilters()
    {
        $this->searchName = $this->filterName;
        $this->searchRole = $this->filterRole;
        $this->resetPage();
    }

    use WithPagination;

    public $editId = null;

    public $editName = '';

    public $editEmail = '';

    public $editRole = '';

    public $searchName = '';

    public $searchRole = '';

    protected $paginationTheme = 'tailwind';

    public function mount()
    {
        $this->authorizeUserManagement();
    }

    public function editUser($id)
    {
        $current = $this->authorizeUserManagement();
        $user = User::findOrFail($id);
        abort_unless($this->canManage($current, $user), 403);

        $this->editId = $user->id;
        $this->editName = $user->name;
        $this->editEmail = $user->email;
        $this->editRole = $user->role;
    }

    public function updateUser()
    {
        $current = $this->authorizeUserManagement();
        abort_unless($this->editId, 403);
        $user = User::findOrFail($this->editId);
        abort_unless($this->canManage($current, $user), 403);

        $assignableRoles = $current->role === 'superadministrador'
            ? ['administrador', 'superadministrador', 'gerente', 'proveedor', 'almacenista']
            : ['gerente', 'proveedor', 'almacenista'];
        $validated = $this->validate([
            'editName' => ['required', 'string', 'max:255'],
            'editEmail' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'editRole' => ['required', Rule::in($assignableRoles)],
        ]);

        if ($validated['editRole'] === 'administrador' && $user->role !== 'administrador') {
            $limit = (int) \App\Models\Setting::getValue('max_administradores', 2);
            $adminCount = User::where('role', 'administrador')->where('activo', true)->count();
            if ($adminCount >= $limit) {
                session()->flash('error', "No se pueden tener más de {$limit} usuarios con rol administrador activos.");

                return;
            }
        }

        $original = $user->only(['name', 'email', 'role', 'activo']);
        $user->name = $validated['editName'];
        $user->email = $validated['editEmail'];
        $user->role = $validated['editRole'];
        $user->save();

        $changes = [];
        foreach (['name', 'email', 'role', 'activo'] as $field) {
            $old = (string) ($original[$field] ?? '');
            $new = (string) $user->{$field};
            if ($old !== $new) {
                $changes[] = [
                    'field' => $field,
                    'old' => $old,
                    'new' => $new,
                ];
            }
        }

        Log::create([
            'accion' => 'editar',
            'entidad' => 'user',
            'entidad_id' => $user->id,
            'descripcion' => 'Edición de datos del usuario.',
            'cambios' => $changes,
            'user_id' => $current->id,
        ]);
        $this->editId = null;
    }

    public function cancelEdit()
    {
        $this->editId = null;
    }

    public function disableUser($id)
    {
        $current = $this->authorizeUserManagement();
        $user = User::findOrFail($id);
        abort_unless($user->id !== $current->id && $this->canManage($current, $user), 403);

        $user->activo = false;
        $user->save();
        $this->logStatusChange('deshabilitar', $user, $current->id);
    }

    public function enableUser($id)
    {
        $current = $this->authorizeUserManagement();
        $user = User::findOrFail($id);
        abort_unless($user->id !== $current->id && $this->canManage($current, $user), 403);

        $user->activo = true;
        $user->save();
        $this->logStatusChange('habilitar', $user, $current->id);
    }

    public function render()
    {
        $this->authorizeUserManagement();
        $query = \App\Models\User::query();
        if ($this->searchName) {
            $query->where('name', 'like', '%'.$this->searchName.'%');
        }
        if ($this->searchRole) {
            $query->where('role', $this->searchRole);
        }
        $users = $query->orderBy('id', 'desc')->paginate(8);

        return view('livewire.admin.user-list', compact('users'));
    }

    private function authorizeUserManagement(): User
    {
        $current = Auth::user();
        abort_unless($current && in_array($current->role, ['administrador', 'superadministrador'], true), 403);

        return $current;
    }

    private function canManage(User $current, User $target): bool
    {
        if ($target->id === $current->id || $target->role === 'superadministrador') {
            return false;
        }

        return $current->role === 'superadministrador'
            || ($current->role === 'administrador' && $target->role !== 'administrador');
    }

    private function logStatusChange(string $action, User $user, int $actorId): void
    {
        Log::create([
            'accion' => $action,
            'entidad' => 'user',
            'entidad_id' => $user->id,
            'descripcion' => "Usuario {$action}: {$user->name}",
            'user_id' => $actorId,
        ]);
    }
}
