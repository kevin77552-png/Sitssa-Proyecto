<?php

namespace App\Livewire\Admin\Imports;

use App\Models\ImportBatch;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    public function render()
    {
        $this->authorizeAdministrator();
        $schemaReady = Schema::hasTable('import_batches') && Schema::hasTable('import_rows');

        return view('livewire.admin.imports.index', [
            'schemaReady' => $schemaReady,
            'batches' => $schemaReady
                ? ImportBatch::query()->with('user')->latest()->limit(50)->get()
                : collect(),
        ]);
    }

    private function authorizeAdministrator(): void
    {
        abort_unless(in_array(auth()->user()?->role, ['administrador', 'superadministrador'], true), 403);
    }
}
