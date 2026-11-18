<div>
    @if($module === 'admin.users')
        @if(auth()->user() && in_array(auth()->user()->role, ['administrador','superadministrador']))
            <livewire:admin.users />
        @else
            <div class="bg-red-100 text-red-800 p-4 rounded">No tienes permiso para acceder a este módulo.</div>
        @endif
    @elseif($module === 'admin.users.register')
        @if(auth()->user() && in_array(auth()->user()->role, ['administrador','superadministrador']))
            @livewire('pages.auth.register')
        @else
            <div class="bg-red-100 text-red-800 p-4 rounded">No tienes permiso para acceder a este módulo.</div>
        @endif
    @elseif($module === 'admin.users.list')
        @if(auth()->user() && in_array(auth()->user()->role, ['administrador','superadministrador']))
            @livewire('admin.user-list')
        @else
            <div class="bg-red-100 text-red-800 p-4 rounded">No tienes permiso para acceder a este módulo.</div>
        @endif
    @elseif($module === 'admin.inventory.ingreso')
        <livewire:admin.inventory-ingreso />
    @elseif($module === 'admin.inventory.egreso')
        <livewire:admin.inventory-egreso />
    @elseif($module === 'admin.inventory.consulta')
        <livewire:admin.inventory-consulta />
    @elseif($module === 'admin.inventory.types')
        <livewire:admin.inventory-types />
    @elseif($module === 'admin.units.registro')
        <livewire:admin.units section="registro" :editing-id="$unitId" />
    @elseif($module === 'admin.units.consulta')
        <livewire:admin.units section="consulta" />
    @elseif($module === 'admin.profile')
        <div class="max-w-6xl">
            @php
                $profileUser = auth()->user();
                $profileInitials = collect(preg_split('/\s+/u', trim($profileUser->name)))
                    ->filter()
                    ->take(2)
                    ->map(fn ($part) => mb_substr($part, 0, 1))
                    ->join('');
            @endphp

            <div
                x-data="{
                    displayName: @js($profileUser->name),
                    displayEmail: @js($profileUser->email),
                    emailVerified: @js($profileUser->hasVerifiedEmail()),
                    initials: @js($profileInitials)
                }"
                x-on:profile-updated.window="
                    displayName = $event.detail.name;
                    displayEmail = $event.detail.email;
                    emailVerified = $event.detail.verified;
                    initials = displayName.trim().split(/\s+/).filter(Boolean).slice(0, 2).map(part => part.charAt(0)).join('').toUpperCase() || 'U';
                "
                class="space-y-6"
            >
                <header class="mb-8 flex flex-col gap-4 border-b border-zinc-200 pb-6 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 class="text-2xl font-semibold tracking-tight text-zinc-900 sm:text-3xl">Perfil de usuario</h1>
                        <p class="mt-1 text-sm text-zinc-500">Administra tu identidad personal, correo electrónico y credenciales de acceso.</p>
                    </div>
                </header>

                <section class="flex flex-col justify-between gap-5 rounded-2xl border border-[#E4E4E7] bg-white p-5 sm:flex-row sm:items-center sm:p-6">
                    <div class="flex min-w-0 items-center gap-4">
                        <div x-text="initials" class="flex h-14 w-14 shrink-0 select-none items-center justify-center rounded-2xl bg-[#09090B] text-base font-semibold tracking-tight text-white"></div>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 x-text="displayName" class="truncate text-base font-semibold text-zinc-900 sm:text-lg"></h2>
                                <span x-show="emailVerified" x-cloak class="inline-flex items-center gap-1 rounded-md border border-emerald-200/80 bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-700">
                                    <span class="material-icons text-[13px]" aria-hidden="true">verified</span>
                                    Correo verificado
                                </span>
                                <span x-show="!emailVerified" x-cloak class="inline-flex items-center gap-1 rounded-md border border-amber-200/80 bg-amber-50 px-2 py-0.5 text-[11px] font-medium text-amber-700">
                                    <span class="material-icons text-[13px]" aria-hidden="true">schedule</span>
                                    Verificación pendiente
                                </span>
                            </div>
                            <p x-text="displayEmail" class="mt-1 truncate font-mono text-xs text-zinc-500"></p>
                        </div>
                    </div>
                </section>

                <main class="grid grid-cols-1 items-stretch gap-6 md:grid-cols-2">
                    <livewire:profile.update-profile-information-form />
                    <livewire:profile.update-password-form />
                </main>
            </div>
        </div>
    @elseif($module === 'admin.history')
        <livewire:admin.history />
    @elseif($module === 'admin.reports')
        <livewire:admin.reports />
    @elseif($module === 'admin.movements')
        <livewire:admin.movements-report />
    @elseif($module === 'admin.migration')
        @if(auth()->user() && in_array(auth()->user()->role, ['administrador','superadministrador']))
            <livewire:admin.migration />
        @else
            <div class="bg-red-100 text-red-800 p-4 rounded">No tienes permiso para acceder a este módulo.</div>
        @endif
    @elseif($module === 'provider.purchase_orders')
        @if(auth()->user() && auth()->user()->role === 'proveedor')
            <div class="mb-6">
                <h2 class="page-title mb-1">Órdenes de compra</h2>
                <p class="page-sub">Consulta las órdenes asignadas a tu cuenta.</p>
            </div>
                <livewire:provider.purchase-orders />
        @else
            <div class="bg-red-100 text-red-800 p-4 rounded">No tienes permiso para acceder a este módulo.</div>
        @endif
    @elseif($module === 'admin.purchase_orders')
        @if(auth()->user() && in_array(auth()->user()->role, ['administrador', 'superadministrador', 'gerente']))
            <livewire:admin.purchase-orders />
        @else
            <div class="bg-red-100 text-red-800 p-4 rounded">No tienes permiso para acceder a este módulo.</div>
        @endif
    @elseif($module === 'admin.purchase_orders.consultar')
        @if(auth()->user() && in_array(auth()->user()->role, ['administrador','superadministrador']))
                <livewire:admin.purchase-orders-consulta />
        @else
            <div class="bg-red-100 text-red-800 p-4 rounded">No tienes permiso para acceder a este módulo.</div>
        @endif
    <!-- 'Resumen' removed for gerente; only 'consultar' remains -->
    @elseif($module === 'gerente.purchase_orders.consultar')
        @if(auth()->user() && auth()->user()->role === 'gerente')
            <livewire:gerente.purchase-orders-consulta />
        @else
            <div class="bg-red-100 text-red-800 p-4 rounded">No tienes permiso para acceder a este módulo.</div>
        @endif
    @elseif($module === 'admin.settings')
        <livewire:admin.settings />
    @elseif($module === 'admin.settings.registro')
        <livewire:admin.settings-registro />
    @elseif($module === 'gerente.solicitudes')
        @if(auth()->user() && auth()->user()->role === 'gerente')
            <livewire:gerente.solicitudes />
        @else
            <div class="bg-red-100 text-red-800 p-4 rounded">No tienes permiso para acceder a este módulo.</div>
        @endif
    @elseif($module === 'gerente.eliminaciones')
        @if(auth()->user() && auth()->user()->role === 'gerente')
            <livewire:gerente.eliminaciones />
        @else
            <div class="bg-red-100 text-red-800 p-4 rounded">No tienes permiso para acceder a este módulo.</div>
        @endif
    @elseif($module === 'almacenista.solicitar_repuestos')
        @if(auth()->user() && auth()->user()->role === 'almacenista')
            <livewire:almacenista.solicitar-repuestos />
        @else
            <div class="bg-red-100 text-red-800 p-4 rounded">No tienes permiso para acceder a este módulo.</div>
        @endif
    @elseif($module === 'almacenista.solicitudes')
        @if(auth()->user() && auth()->user()->role === 'almacenista')
            <livewire:almacenista.solicitudes />
        @else
            <div class="bg-red-100 text-red-800 p-4 rounded">No tienes permiso para acceder a este módulo.</div>
        @endif
    @elseif($module === 'wiki.ayuda')
        @if(auth()->user())
            <div class="mb-6">
                <h2 class="page-title mb-1">Wiki ayuda</h2>
                <p class="page-sub">Guías de uso del sistema.</p>
            </div>
                <livewire:wiki.ayuda />
        @else
            <div class="bg-red-100 text-red-800 p-4 rounded">Inicie sesión para ver la Wiki.</div>
        @endif
    @else
        <livewire:home />
    @endif
</div>
