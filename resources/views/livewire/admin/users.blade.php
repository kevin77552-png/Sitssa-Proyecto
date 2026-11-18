<div class="w-full">
    <h2 class="page-title mb-1">Administración de usuarios</h2>
    <div class="mb-4">
        <h3 class="text-lg font-semibold mb-2 text-blue-700">Registrar nuevo usuario</h3>
        @livewire('pages.auth.register')
    </div>
    <div class="overflow-x-auto">
        <h3 class="text-lg font-semibold mb-2 text-blue-700">Usuarios registrados</h3>
        @livewire('admin.user-list')
    </div>
</div>
