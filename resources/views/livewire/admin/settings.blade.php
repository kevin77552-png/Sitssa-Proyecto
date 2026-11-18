<section class="card max-w-3xl space-y-5">
    <div>
        <h2 class="page-title">Configuración general</h2>
        <p class="mt-1 text-sm text-gray-600">Consulta los parámetros administrativos del sistema.</p>
    </div>

    <div class="rounded-lg border border-gray-200 bg-stone-50 p-4">
        <h3 class="font-semibold text-gray-900">Administradores activos</h3>
        <p class="mt-1 text-sm text-gray-600">
            Límite configurado: <span class="font-semibold">{{ $maxAdministrators }}</span>
        </p>
        @if($canManageSettings)
            <button type="button" wire:click="openRegistrationSettings" class="btn-primary mt-4">
                Administrar este límite
            </button>
        @else
            <p class="mt-3 text-xs text-gray-500">Solo un superadministrador puede modificar este parámetro.</p>
        @endif
    </div>
</section>
