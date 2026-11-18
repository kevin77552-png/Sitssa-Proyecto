<div
    x-data="{
        open: localStorage.getItem('sidebarOpen') === null
            ? window.innerWidth >= 1024
            : localStorage.getItem('sidebarOpen') === 'true',
        submenu: null
    }"
    x-init="$watch('open', value => {
        localStorage.setItem('sidebarOpen', value);
        if (!value) submenu = null;
    })"
    :class="open ? 'w-64' : 'w-[72px]'"
    class="sticky top-0 z-40 flex h-screen min-h-screen shrink-0 flex-col gap-1 overflow-visible bg-brand-900 p-3 text-white transition-[width] duration-300 ease-in-out"
>
    <button
        @click="open = !open"
        :aria-expanded="open"
        aria-controls="primary-sidebar-navigation"
        :aria-label="open ? 'Contraer menú' : 'Expandir menú'"
        :title="open ? 'Contraer menú' : 'Expandir menú'"
        class="absolute -right-3 top-5 z-50 grid h-6 w-6 place-items-center rounded-full border border-stone-200 bg-white text-brand-900 shadow-sm transition hover:bg-stone-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-600"
    >
        <svg class="h-4 w-4 transition-transform duration-300" :class="open ? '' : 'rotate-180'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
        </svg>
    </button>

    <button type="button" wire:click="goHome"
        aria-label="SITSSA, ir al inicio"
        class="mt-1 mb-[10px] flex w-full shrink-0 items-center justify-center rounded-2xl bg-white/10 ring-1 ring-white/15 transition-all duration-300 hover:bg-white/15 focus:outline-none focus-visible:ring-2 focus-visible:ring-white"
        :class="open ? 'px-3 py-3.5' : 'px-1.5 py-2.5'">
        <img x-show="open" x-cloak src="{{ asset('images/sittsa-logo-blanco.png') }}" alt="" class="h-auto w-[104px]">
        <img x-show="!open" x-cloak src="{{ asset('images/sittsa-emblema-blanco.png') }}" alt="" class="h-auto w-9">
    </button>

    <nav id="primary-sidebar-navigation" aria-label="Navegación principal" class="flex min-h-0 flex-1 flex-col gap-1 overflow-y-auto overflow-x-hidden">
        <button type="button" wire:click="goHome"
            class="nav-item {{ request()->routeIs('dashboard') && ! $selected ? 'nav-item-active' : '' }}"
            :class="!open && 'justify-center px-0'"
            :title="!open ? 'Inicio' : ''"
            @if(request()->routeIs('dashboard') && ! $selected) aria-current="page" @endif>
            <span class="material-icons text-[20px]" aria-hidden="true">home</span>
            <span x-show="open" x-transition.opacity class="flex-1 truncate">Inicio</span>
        </button>

        @if(auth()->user() && in_array(auth()->user()->role, ['administrador', 'superadministrador']))
            <div>
                <button @click="if (!open) open = true; submenu = submenu === 'admin_inv' ? null : 'admin_inv'"
                    :aria-expanded="open && submenu === 'admin_inv'"
                    class="nav-item {{ $selected && \Illuminate\Support\Str::startsWith($selected, 'admin.inventory') && $selected !== 'admin.inventory.types' ? 'nav-item-active' : '' }}"
                    :class="!open && 'justify-center px-0'"
                    :title="!open ? 'Inventario' : ''">
                    <span class="material-icons text-[20px]" aria-hidden="true">inventory_2</span>
                    <span x-show="open" x-transition.opacity class="flex-1 truncate">Inventario</span>
                    <svg x-show="open" class="h-4 w-4 shrink-0 transition-transform" :class="submenu === 'admin_inv' ? 'rotate-90' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                </button>
                <div x-show="submenu === 'admin_inv' && open" x-transition class="ml-5 mt-1 flex flex-col gap-1 border-l border-white/20 pl-3">
                    <button wire:click="select('admin.inventory.ingreso')" class="nav-sub {{ $selected === 'admin.inventory.ingreso' ? 'nav-sub-active' : '' }}">Ingreso</button>
                    <button wire:click="select('admin.inventory.egreso')" class="nav-sub {{ $selected === 'admin.inventory.egreso' ? 'nav-sub-active' : '' }}">Salida</button>
                    <button wire:click="select('admin.inventory.consulta')" class="nav-sub {{ $selected === 'admin.inventory.consulta' ? 'nav-sub-active' : '' }}">Consultar</button>
                </div>
            </div>
            <div>
                <button @click="if (!open) open = true; submenu = submenu === 'units' ? null : 'units'"
                    :aria-expanded="open && submenu === 'units'"
                    class="nav-item {{ $selected && \Illuminate\Support\Str::startsWith($selected, 'admin.units.') ? 'nav-item-active' : '' }}"
                    :class="!open && 'justify-center px-0'"
                    :title="!open ? 'Unidades' : ''">
                    <span class="material-icons text-[20px]" aria-hidden="true">directions_bus</span>
                    <span x-show="open" x-transition.opacity class="flex-1 truncate">Unidades</span>
                    <svg x-show="open" class="h-4 w-4 shrink-0 transition-transform" :class="submenu === 'units' ? 'rotate-90' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                </button>
                <div x-show="submenu === 'units' && open" x-transition class="ml-5 mt-1 flex flex-col gap-1 border-l border-white/20 pl-3">
                    <button wire:click="select('admin.units.registro')" class="nav-sub {{ $selected === 'admin.units.registro' ? 'nav-sub-active' : '' }}">Registro</button>
                    <button wire:click="select('admin.units.consulta')" class="nav-sub {{ $selected === 'admin.units.consulta' ? 'nav-sub-active' : '' }}">Consulta</button>
                </div>
            </div>
            <div>
                <button @click="if (!open) open = true; submenu = submenu === 'users' ? null : 'users'"
                    :aria-expanded="open && submenu === 'users'"
                    class="nav-item {{ in_array($selected, ['admin.users.register', 'admin.users.list', 'admin.users'], true) ? 'nav-item-active' : '' }}"
                    :class="!open && 'justify-center px-0'"
                    :title="!open ? 'Usuarios' : ''">
                    <span class="material-icons text-[20px]" aria-hidden="true">group</span>
                    <span x-show="open" x-transition.opacity class="flex-1 truncate">Usuarios</span>
                    <svg x-show="open" class="h-4 w-4 shrink-0 transition-transform" :class="submenu === 'users' ? 'rotate-90' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                </button>
                <div x-show="submenu === 'users' && open" x-transition class="ml-5 mt-1 flex flex-col gap-1 border-l border-white/20 pl-3">
                    <button wire:click="select('admin.users.register')" class="nav-sub {{ $selected === 'admin.users.register' ? 'nav-sub-active' : '' }}">Registrar usuario</button>
                    <button wire:click="select('admin.users.list')" class="nav-sub {{ $selected === 'admin.users.list' ? 'nav-sub-active' : '' }}">Consultar usuarios</button>
                </div>
            </div>
        @endif

        @if(auth()->user() && auth()->user()->role === 'almacenista')
            <div>
                <button @click="if (!open) open = true; submenu = submenu === 'inv' ? null : 'inv'"
                    :aria-expanded="open && submenu === 'inv'"
                    class="nav-item {{ $selected && \Illuminate\Support\Str::startsWith($selected, 'admin.inventory') ? 'nav-item-active' : '' }}"
                    :class="!open && 'justify-center px-0'"
                    :title="!open ? 'Inventario' : ''">
                    <span class="material-icons text-[20px]" aria-hidden="true">inventory_2</span>
                    <span x-show="open" x-transition.opacity class="flex-1 truncate">Inventario</span>
                    <svg x-show="open" class="h-4 w-4 shrink-0 transition-transform" :class="submenu === 'inv' ? 'rotate-90' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                </button>
                <div x-show="submenu === 'inv' && open" x-transition class="ml-5 mt-1 flex flex-col gap-1 border-l border-white/20 pl-3">
                    <button wire:click="select('admin.inventory.ingreso')" class="nav-sub {{ $selected === 'admin.inventory.ingreso' ? 'nav-sub-active' : '' }}">Ingreso</button>
                    <button wire:click="select('admin.inventory.egreso')" class="nav-sub {{ $selected === 'admin.inventory.egreso' ? 'nav-sub-active' : '' }}">Salida</button>
                    <button wire:click="select('admin.inventory.consulta')" class="nav-sub {{ $selected === 'admin.inventory.consulta' ? 'nav-sub-active' : '' }}">Consultar</button>
                </div>
            </div>
            <button wire:click="select('almacenista.solicitar_repuestos')"
                class="nav-item {{ $selected === 'almacenista.solicitar_repuestos' ? 'nav-item-active' : '' }}"
                :class="!open && 'justify-center px-0'"
                :title="!open ? 'Solicitar repuestos' : ''">
                <span class="material-icons text-[20px]" aria-hidden="true">add_shopping_cart</span>
                <span x-show="open" x-transition.opacity class="flex-1 truncate">Solicitar repuestos</span>
            </button>
            <button wire:click="select('almacenista.solicitudes')"
                class="nav-item {{ $selected === 'almacenista.solicitudes' ? 'nav-item-active' : '' }}"
                :class="!open && 'justify-center px-0'"
                :title="!open ? 'Consultar solicitudes' : ''">
                <span class="material-icons text-[20px]" aria-hidden="true">list_alt</span>
                <span x-show="open" x-transition.opacity class="flex-1 truncate">Consultar solicitudes</span>
            </button>
        @endif

        @if(auth()->user() && in_array(auth()->user()->role, ['administrador', 'superadministrador']))
            <button wire:click="select('admin.history')"
                class="nav-item {{ $selected === 'admin.history' ? 'nav-item-active' : '' }}"
                :class="!open && 'justify-center px-0'"
                :title="!open ? 'Histórico general' : ''">
                <span class="material-icons text-[20px]" aria-hidden="true">history</span>
                <span x-show="open" x-transition.opacity class="flex-1 truncate">Histórico general</span>
            </button>
            <div>
                <button @click="if (!open) open = true; submenu = submenu === 'migration' ? null : 'migration'"
                    :aria-expanded="open && submenu === 'migration'"
                    class="nav-item {{ $selected === 'admin.migration' || request()->routeIs('imports.*') ? 'nav-item-active' : '' }}"
                    :class="!open && 'justify-center px-0'"
                    :title="!open ? 'Migración' : ''">
                    <span class="material-icons text-[20px]" aria-hidden="true">sync_alt</span>
                    <span x-show="open" x-transition.opacity class="flex-1 truncate">Migración</span>
                    <svg x-show="open" class="h-4 w-4 shrink-0 transition-transform" :class="submenu === 'migration' ? 'rotate-90' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                </button>
                <div x-show="submenu === 'migration' && open" x-transition class="ml-5 mt-1 flex flex-col gap-1 border-l border-white/20 pl-3">
                    <button wire:click="select('admin.migration')" class="nav-sub {{ $selected === 'admin.migration' ? 'nav-sub-active' : '' }}">Exportación</button>
                    <a href="{{ route('imports.index') }}" class="nav-sub {{ request()->routeIs('imports.*') ? 'nav-sub-active' : '' }}">Importaciones</a>
                </div>
            </div>
        @endif

        @if(auth()->user() && in_array(auth()->user()->role, ['administrador', 'superadministrador', 'gerente'], true))
            <button wire:click="select('admin.reports')"
                class="nav-item {{ $selected === 'admin.reports' ? 'nav-item-active' : '' }}"
                :class="!open && 'justify-center px-0'"
                :title="!open ? 'Reportes' : ''">
                <span class="material-icons text-[20px]" aria-hidden="true">bar_chart</span>
                <span x-show="open" x-transition.opacity class="flex-1 truncate">Reportes</span>
            </button>
        @endif

        @if(auth()->user() && in_array(auth()->user()->role, ['administrador', 'superadministrador', 'gerente', 'almacenista'], true))
            <button wire:click="select('admin.movements')"
                class="nav-item {{ $selected === 'admin.movements' ? 'nav-item-active' : '' }}"
                :class="!open && 'justify-center px-0'"
                :title="!open ? 'Movimientos' : ''">
                <span class="material-icons text-[20px]" aria-hidden="true">swap_vert</span>
                <span x-show="open" x-transition.opacity class="flex-1 truncate">Movimientos</span>
            </button>
        @endif

        @if(auth()->user())
            <button wire:click="select('wiki.ayuda')"
                class="nav-item {{ $selected === 'wiki.ayuda' ? 'nav-item-active' : '' }}"
                :class="!open && 'justify-center px-0'"
                :title="!open ? 'Wiki Ayuda' : ''">
                <span class="material-icons text-[20px]" aria-hidden="true">menu_book</span>
                <span x-show="open" x-transition.opacity class="flex-1 truncate">Wiki Ayuda</span>
            </button>
        @endif

        @if(auth()->user() && auth()->user()->role === 'proveedor')
            <button wire:click="select('provider.purchase_orders')"
                class="nav-item {{ $selected === 'provider.purchase_orders' ? 'nav-item-active' : '' }}"
                :class="!open && 'justify-center px-0'"
                :title="!open ? 'Órdenes de compra' : ''">
                <span class="material-icons text-[20px]" aria-hidden="true">receipt_long</span>
                <span x-show="open" x-transition.opacity class="flex-1 truncate">Órdenes de compra</span>
            </button>
        @endif

        @if(auth()->user() && auth()->user()->role === 'gerente')
            <div>
                <button @click="if (!open) open = true; submenu = submenu === 'oc' ? null : 'oc'"
                    :aria-expanded="open && submenu === 'oc'"
                    class="nav-item {{ in_array($selected, ['admin.purchase_orders', 'gerente.purchase_orders.consultar'], true) ? 'nav-item-active' : '' }}"
                    :class="!open && 'justify-center px-0'"
                    :title="!open ? 'Órdenes de compra' : ''">
                    <span class="material-icons text-[20px]" aria-hidden="true">receipt_long</span>
                    <span x-show="open" x-transition.opacity class="flex-1 truncate">Órdenes de compra</span>
                    <svg x-show="open" class="h-4 w-4 shrink-0 transition-transform" :class="submenu === 'oc' ? 'rotate-90' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                </button>
                <div x-show="submenu === 'oc' && open" x-transition class="ml-5 mt-1 flex flex-col gap-1 border-l border-white/20 pl-3">
                    <button wire:click="select('admin.purchase_orders')" class="nav-sub {{ $selected === 'admin.purchase_orders' ? 'nav-sub-active' : '' }}">Crear pedido</button>
                    <button wire:click="select('gerente.purchase_orders.consultar')" class="nav-sub {{ $selected === 'gerente.purchase_orders.consultar' ? 'nav-sub-active' : '' }}">Consultar</button>
                </div>
            </div>
            <button wire:click="select('gerente.solicitudes')"
                class="nav-item {{ $selected === 'gerente.solicitudes' ? 'nav-item-active' : '' }}"
                :class="!open && 'justify-center px-0'"
                :title="!open ? 'Solicitudes' : ''">
                <span class="material-icons text-[20px]" aria-hidden="true">assignment</span>
                <span x-show="open" x-transition.opacity class="flex-1 truncate">Solicitudes</span>
                @if($pendingRequests > 0)
                    <span class="rounded-full bg-amber-400 px-1.5 py-0.5 text-[10px] font-bold text-amber-950"
                          aria-label="{{ $pendingRequests }} {{ $pendingRequests === 1 ? 'solicitud pendiente' : 'solicitudes pendientes' }}">
                        {{ $pendingRequests > 99 ? '99+' : $pendingRequests }}
                    </span>
                @endif
            </button>
            <button wire:click="select('gerente.eliminaciones')"
                class="nav-item {{ $selected === 'gerente.eliminaciones' ? 'nav-item-active' : '' }}"
                :class="!open && 'justify-center px-0'"
                :title="!open ? 'Eliminaciones' : ''">
                <span class="material-icons text-[20px]" aria-hidden="true">delete_forever</span>
                <span x-show="open" x-transition.opacity class="flex-1 truncate">Eliminaciones</span>
            </button>
        @endif

        @if(auth()->user() && in_array(auth()->user()->role, ['administrador', 'superadministrador']))
            <div>
                <button @click="if (!open) open = true; submenu = submenu === 'admin_oc' ? null : 'admin_oc'"
                    :aria-expanded="open && submenu === 'admin_oc'"
                    class="nav-item {{ in_array($selected, ['admin.purchase_orders', 'admin.purchase_orders.consultar'], true) ? 'nav-item-active' : '' }}"
                    :class="!open && 'justify-center px-0'"
                    :title="!open ? 'Órdenes de compra' : ''">
                    <span class="material-icons text-[20px]" aria-hidden="true">receipt_long</span>
                    <span x-show="open" x-transition.opacity class="flex-1 truncate">Órdenes de compra</span>
                    <svg x-show="open" class="h-4 w-4 shrink-0 transition-transform" :class="submenu === 'admin_oc' ? 'rotate-90' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                </button>
                <div x-show="submenu === 'admin_oc' && open" x-transition class="ml-5 mt-1 flex flex-col gap-1 border-l border-white/20 pl-3">
                    <button wire:click="select('admin.purchase_orders')" class="nav-sub {{ $selected === 'admin.purchase_orders' ? 'nav-sub-active' : '' }}">Crear pedido</button>
                    <button wire:click="select('admin.purchase_orders.consultar')" class="nav-sub {{ $selected === 'admin.purchase_orders.consultar' ? 'nav-sub-active' : '' }}">Consultar</button>
                </div>
            </div>
        @endif

        <div class="mt-auto flex flex-col gap-1 pt-6">
            @if(auth()->user() && in_array(auth()->user()->role, ['administrador', 'superadministrador']))
                <div>
                    <button @click="if (!open) open = true; submenu = submenu === 'settings' ? null : 'settings'"
                        :aria-expanded="open && submenu === 'settings'"
                        class="nav-item {{ in_array($selected, ['admin.settings', 'admin.settings.registro', 'admin.inventory.types'], true) ? 'nav-item-active' : '' }}"
                        :class="!open && 'justify-center px-0'"
                        :title="!open ? 'Configuración' : ''">
                        <span class="material-icons text-[20px]" aria-hidden="true">settings</span>
                        <span x-show="open" x-transition.opacity class="flex-1 truncate">Configuración</span>
                        <svg x-show="open" class="h-4 w-4 shrink-0 transition-transform" :class="submenu === 'settings' ? 'rotate-90' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                    <div x-show="submenu === 'settings' && open" x-transition class="ml-5 mt-1 flex flex-col gap-1 border-l border-white/20 pl-3">
                        <button wire:click="select('admin.settings')" class="nav-sub {{ $selected === 'admin.settings' ? 'nav-sub-active' : '' }}">Configuración general</button>
                        <button wire:click="select('admin.inventory.types')" class="nav-sub {{ $selected === 'admin.inventory.types' ? 'nav-sub-active' : '' }}">Tipos de inventario</button>
                        @if(auth()->user()->role === 'superadministrador')
                            <button wire:click="select('admin.settings.registro')" class="nav-sub {{ $selected === 'admin.settings.registro' ? 'nav-sub-active' : '' }}">Registro</button>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </nav>
</div>
