<?php

namespace App\Livewire\Admin;

use App\Models\Setting;
use Livewire\Component;

class Settings extends Component
{
    public function openRegistrationSettings(): void
    {
        abort_unless(auth()->user()?->role === 'superadministrador', 403);
        $this->dispatch('showModule', 'admin.settings.registro');
    }

    public function render()
    {
        return view('livewire.admin.settings', [
            'maxAdministrators' => (int) Setting::getValue('max_administradores', 2),
            'canManageSettings' => auth()->user()?->role === 'superadministrador',
        ]);
    }
}
