<div class="w-full overflow-hidden rounded-xl border border-slate-200/90 bg-white shadow-sm">
    @php
        $currentRole = auth()->user()->role;
        $roleStyles = [
            'superadministrador' => 'border-slate-700 bg-slate-800 text-white',
            'administrador' => 'border-sky-200/80 bg-sky-50 text-sky-700',
            'gerente' => 'border-purple-200/80 bg-purple-50 text-purple-700',
            'proveedor' => 'border-amber-200/80 bg-amber-50 text-amber-700',
            'almacenista' => 'border-emerald-200/80 bg-emerald-50 text-emerald-700',
        ];
    @endphp

    <header class="flex flex-col gap-4 border-b border-slate-100 bg-white px-6 py-6 sm:flex-row sm:items-center sm:justify-between sm:px-8">
        <div class="flex items-start gap-3.5">
            <div class="mt-0.5 shrink-0 rounded-lg border border-[#4A0C12]/10 bg-[#4A0C12]/5 p-2.5 text-[#4A0C12]">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
            </div>
            <div>
                <div class="flex flex-wrap items-center gap-2.5">
                    <h1 class="text-xl font-bold tracking-tight text-slate-900">Consultar usuarios</h1>
                    <span class="inline-flex items-center rounded-full border border-slate-200/80 bg-slate-100 px-2.5 py-0.5 font-mono text-xs font-semibold text-slate-600">
                        {{ $users->total() }} {{ $users->total() === 1 ? 'cuenta' : 'cuentas' }}
                    </span>
                </div>
                <p class="mt-0.5 text-sm text-slate-500">Administra cuentas del personal, roles y estado de actividad.</p>
            </div>
        </div>

        <a href="{{ route('dashboard', ['module' => 'admin.users.register']) }}" wire:navigate
            class="inline-flex cursor-pointer items-center justify-center gap-2 self-start rounded-lg bg-[#4A0C12] px-4 py-2.5 text-xs font-medium text-white shadow-sm shadow-[#4A0C12]/20 transition hover:bg-[#3A090E] sm:self-auto">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
            </svg>
            Nuevo usuario
        </a>
    </header>

    <div class="flex flex-col justify-between gap-3 border-b border-slate-200/80 bg-slate-50/80 px-6 py-4 md:flex-row md:items-center sm:px-8">
        <form wire:submit.prevent="applyFilters" class="flex flex-1 flex-wrap items-center gap-3">
            <label class="relative min-w-[220px] flex-1 md:max-w-sm">
                <span class="sr-only">Buscar por nombre o correo</span>
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </span>
                <input type="search" wire:model.defer="filterName" placeholder="Buscar por nombre o correo..."
                    class="w-full rounded-lg border border-slate-300 bg-white py-2 pl-9 pr-3.5 text-xs text-slate-800 transition placeholder:text-slate-400 focus:border-[#4A0C12] focus:outline-none focus:ring-2 focus:ring-[#4A0C12]/15">
            </label>

            <label>
                <span class="sr-only">Filtrar por rol</span>
                <select wire:model.defer="filterRole"
                    class="cursor-pointer rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-xs font-medium text-slate-700 transition focus:border-[#4A0C12] focus:outline-none focus:ring-2 focus:ring-[#4A0C12]/15">
                    <option value="">Todos los roles</option>
                    <option value="superadministrador">Superadministrador</option>
                    <option value="administrador">Administrador</option>
                    <option value="gerente">Gerente</option>
                    <option value="almacenista">Almacenista</option>
                    <option value="proveedor">Proveedor</option>
                </select>
            </label>

            <button type="submit"
                class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg bg-[#4A0C12] px-3.5 py-2 text-xs font-medium text-white transition hover:bg-[#3A090E]">
                Buscar
            </button>
        </form>

        <p class="text-[11px] text-slate-500">
            Mostrando <strong class="font-mono text-slate-700">{{ $users->count() }}</strong> {{ $users->count() === 1 ? 'usuario' : 'usuarios' }} en esta página
        </p>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full border-collapse text-left">
            <thead>
                <tr class="border-b border-slate-200 bg-slate-50/50 text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                    <th scope="col" class="py-3.5 pl-6 pr-3">Usuario</th>
                    <th scope="col" class="px-3 py-3.5">Correo electrónico</th>
                    <th scope="col" class="px-3 py-3.5">Rol asignado</th>
                    <th scope="col" class="px-3 py-3.5">Estado</th>
                    <th scope="col" class="py-3.5 pl-3 pr-6 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200/70 bg-white text-sm">
                @forelse($users as $user)
                    @php
                        $email = $user->email ?? '';
                        if ($currentRole === 'administrador' && $user->role === 'superadministrador') {
                            if (str_contains($email, '@')) {
                                [$local, $domain] = explode('@', $email, 2);
                                $maskedEmail = mb_substr($local, 0, 1).str_repeat('*', max(3, mb_strlen($local) - 1)).'@'.$domain;
                            } else {
                                $maskedEmail = 'Oculto';
                            }
                        } else {
                            $maskedEmail = $email;
                        }

                        $initials = collect(preg_split('/\s+/', trim($user->name)))
                            ->filter()
                            ->take(2)
                            ->map(fn ($part) => mb_substr($part, 0, 1))
                            ->implode('');

                        $canManageUser = $user->id !== auth()->id()
                            && $user->role !== 'superadministrador'
                            && ($currentRole === 'superadministrador' || $user->role !== 'administrador');
                    @endphp
                    <tr wire:key="user-{{ $user->id }}" @class([
                        'transition-colors hover:bg-slate-50/90',
                        'bg-slate-50/40 opacity-60' => ! $user->activo,
                    ])>
                        <td class="whitespace-nowrap py-3.5 pl-6 pr-3">
                            <div class="flex items-center gap-3">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-slate-200/80 bg-slate-100 text-xs font-bold uppercase text-slate-600">
                                    {{ $initials }}
                                </span>
                                <span class="font-semibold text-slate-900">{{ $user->name }}</span>
                            </div>
                        </td>
                        <td class="whitespace-nowrap px-3 py-3.5 font-mono text-xs text-slate-600">{{ $maskedEmail }}</td>
                        <td class="whitespace-nowrap px-3 py-3.5">
                            <span @class([
                                'inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium',
                                $roleStyles[$user->role] ?? 'border-slate-200 bg-slate-100 text-slate-700',
                            ])>{{ ucfirst($user->role) }}</span>
                        </td>
                        <td class="whitespace-nowrap px-3 py-3.5">
                            @if($user->activo)
                                <span class="inline-flex items-center gap-1.5 text-xs font-medium text-emerald-700">
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                    Activo
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-400">
                                    <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span>
                                    Deshabilitado
                                </span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap py-3.5 pl-3 pr-6 text-right">
                            @if($canManageUser)
                                <div class="inline-flex items-center gap-2">
                                    <button type="button" wire:click="editUser({{ $user->id }})"
                                        class="inline-flex cursor-pointer items-center gap-1 rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50">
                                        Editar
                                    </button>
                                    @if($user->activo)
                                        <button type="button" x-data
                                            @click.prevent="Swal.fire({
                                                title: '¿Estás seguro?',
                                                text: 'Esta acción deshabilitará al usuario.',
                                                icon: 'warning',
                                                showCancelButton: true,
                                                confirmButtonColor: '#4A0C12',
                                                cancelButtonColor: '#64748b',
                                                confirmButtonText: 'Sí, deshabilitar',
                                                cancelButtonText: 'Cancelar'
                                            }).then((result) => {
                                                if (result.isConfirmed) $wire.disableUser({{ $user->id }})
                                            })"
                                            class="inline-flex cursor-pointer items-center gap-1 rounded-lg border border-rose-200/70 bg-rose-50/70 px-2.5 py-1.5 text-xs font-medium text-rose-600 transition hover:border-rose-600 hover:bg-rose-600 hover:text-white">
                                            Deshabilitar
                                        </button>
                                    @else
                                        <button type="button" wire:click="enableUser({{ $user->id }})"
                                            class="inline-flex cursor-pointer items-center gap-1 rounded-lg border border-emerald-200/70 bg-emerald-50 px-2.5 py-1.5 text-xs font-medium text-emerald-700 transition hover:border-emerald-600 hover:bg-emerald-600 hover:text-white">
                                            Habilitar
                                        </button>
                                    @endif
                                </div>
                            @else
                                <span title="Cuenta protegida: no puedes modificar este usuario"
                                    class="inline-flex select-none items-center gap-1.5 rounded-lg border border-slate-200/70 bg-slate-100 px-2.5 py-1 text-[11px] font-medium text-slate-400">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                    </svg>
                                    Cuenta protegida
                                </span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center">
                            <p class="text-sm font-semibold text-slate-900">No se encontraron usuarios</p>
                            <p class="mt-1 text-xs text-slate-500">Ninguna cuenta coincide con los criterios de búsqueda actuales.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <footer class="flex flex-col items-center justify-between gap-4 border-t border-slate-200/80 bg-slate-50/70 px-6 py-4 sm:flex-row sm:px-8">
        <div class="w-full">
            {{ $users->onEachSide(1)->links('livewire::tailwind', ['paginationLabel' => 'Paginación de usuarios']) }}
        </div>
    </footer>

    @if($editId)
        <div wire:keydown.escape.window="cancelEdit" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm">
            <section role="dialog" aria-modal="true" aria-labelledby="edit-user-title"
                class="w-full max-w-md overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl">
                <header class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                    <h2 id="edit-user-title" class="text-base font-bold text-slate-900">Editar usuario</h2>
                    <button type="button" wire:click="cancelEdit" aria-label="Cerrar"
                        class="cursor-pointer text-slate-400 transition hover:text-slate-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </header>

                <div class="space-y-4 p-6">
                    @if(session('error'))
                        <div role="alert" class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-center text-sm font-medium text-rose-800">
                            {{ session('error') }}
                        </div>
                    @endif

                    <div>
                        <label for="edit-user-name" class="mb-1 block text-xs font-medium text-slate-700">Nombre completo</label>
                        <input id="edit-user-name" type="text" wire:model="editName"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs text-slate-800 focus:border-[#4A0C12] focus:outline-none focus:ring-2 focus:ring-[#4A0C12]/15">
                        <x-input-error :messages="$errors->get('editName')" class="mt-1" />
                    </div>
                    <div>
                        <label for="edit-user-email" class="mb-1 block text-xs font-medium text-slate-700">Correo electrónico</label>
                        <input id="edit-user-email" type="email" wire:model="editEmail"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs text-slate-800 focus:border-[#4A0C12] focus:outline-none focus:ring-2 focus:ring-[#4A0C12]/15">
                        <x-input-error :messages="$errors->get('editEmail')" class="mt-1" />
                    </div>
                    <div>
                        <label for="edit-user-role" class="mb-1 block text-xs font-medium text-slate-700">Rol asignado</label>
                        <select id="edit-user-role" wire:model="editRole"
                            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs text-slate-800 focus:border-[#4A0C12] focus:outline-none focus:ring-2 focus:ring-[#4A0C12]/15">
                            @if($currentRole === 'superadministrador')
                                <option value="administrador">Administrador</option>
                                <option value="superadministrador">Superadministrador</option>
                            @endif
                            <option value="gerente">Gerente</option>
                            <option value="proveedor">Proveedor</option>
                            <option value="almacenista">Almacenista</option>
                        </select>
                        <x-input-error :messages="$errors->get('editRole')" class="mt-1" />
                    </div>

                    <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-3">
                        <button type="button" wire:click="cancelEdit"
                            class="cursor-pointer rounded-lg px-4 py-2 text-xs font-medium text-slate-600 transition hover:bg-slate-100">
                            Cancelar
                        </button>
                        <button type="button" wire:click="updateUser" wire:loading.attr="disabled" wire:target="updateUser"
                            class="cursor-pointer rounded-lg bg-[#4A0C12] px-4 py-2 text-xs font-medium text-white shadow-sm transition hover:bg-[#3A090E] disabled:cursor-wait disabled:opacity-70">
                            Guardar cambios
                        </button>
                    </div>
                </div>
            </section>
        </div>
    @endif
</div>
