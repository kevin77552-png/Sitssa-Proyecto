@php $role = auth()->user()->role ?? null; @endphp
<div x-data="{ q: '' }" class="card max-w-4xl">
    <label for="help-search" class="sr-only">Buscar en la ayuda</label>
    <input id="help-search" x-model="q" type="search" placeholder="¿Qué necesitas hacer? Ej: registrar salida" class="field mb-4 w-full">
    <div class="space-y-4">
        <!-- Almacenista -->
        @if(in_array($role, ['superadministrador','administrador','gerente','almacenista']))
        <div x-data="{ open: {{ $role === 'almacenista' ? 'true' : 'false' }} }" x-show="!q || $el.textContent.toLowerCase().includes(q.toLowerCase())" class="overflow-hidden rounded-lg border border-stone-200">
            <button @click="open = !open" class="flex w-full items-center justify-between bg-stone-50 px-4 py-3 text-left hover:bg-stone-100">
                <div class="flex items-center gap-3">
                    <span class="material-icons text-brand-900" aria-hidden="true">engineering</span>
                    <span class="font-semibold">Almacenista (Gestión Diaria del Inventario)</span>
                </div>
                <svg :class="open ? 'transform rotate-90' : ''" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-600 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
            </button>
            <div x-show="open" x-transition class="p-5 text-[15px] leading-7 text-gray-700">
                <p class="text-sm text-gray-600">Usted es responsable de registrar todos los movimientos físicos y mantener la exactitud del inventario.</p>

                <h3 class="mt-5 font-semibold text-gray-900">1. Consulta y Ubicación de Repuestos</h3>
                <p>Propósito: Encontrar repuestos y verificar datos. Puede buscar por <strong>SIGICOV</strong>, descripción o ubicación (Pasillo / Estante / Peldaño).</p>

                <h3 class="mt-5 font-semibold text-gray-900">2. Registrar Ingresos (Entrada de Stock)</h3>
                <p>Flujo: <span class="inline-block rounded bg-stone-100 px-2 py-0.5 text-sm font-medium">Acciones Rápidas → Registrar Entrada</span></p>
                <ol class="ml-5 list-decimal list-outside">
                    <li>Registrar cantidad recibida y número de factura o referencia.</li>
                    <li>Guardar: esto AUMENTA el stock disponible.</li>
                </ol>

                <h3 class="mt-5 font-semibold text-gray-900">3. Registrar Egresos (Salida de Stock)</h3>
                <p>Flujo: <span class="inline-block rounded bg-stone-100 px-2 py-0.5 text-sm font-medium">Acciones Rápidas → Registrar Salida</span></p>
                <ol class="ml-5 list-decimal list-outside">
                    <li>Registrar la cantidad entregada, ID del vehículo y Número de Orden de Trabajo (OT).</li>
                    <li>Guardar: esto DISMINUYE el stock disponible.</li>
                </ol>
                <div class="mt-2 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">Advertencia: el sistema impedirá una salida si el stock actual es insuficiente.</div>

                <h3 class="mt-5 font-semibold text-gray-900">4. Solicitud de Baja de Repuestos</h3>
                <p>Flujo: <span class="inline-block rounded bg-stone-100 px-2 py-0.5 text-sm font-medium">Acciones Rápidas → Solicitar Baja/Eliminación</span></p>
                <p>Especifique cantidad y motivo; la baja requiere aprobación del Gerente para que el stock se ajuste oficialmente.</p>

                <h3 class="mt-5 font-semibold text-gray-900">5. Ver Stock Actual</h3>
                <p>El sistema calcula el stock en tiempo real:</p>
                <pre class="bg-gray-100 p-2 rounded"><code>Stock Actual = Σ(Entradas) - Σ(Salidas + Bajas)</code></pre>
            </div>
        </div>
        @endif

        <!-- Gerente -->
        @if(in_array($role, ['superadministrador','administrador','gerente']))
        <div x-data="{ open: {{ $role === 'gerente' ? 'true' : 'false' }} }" x-show="!q || $el.textContent.toLowerCase().includes(q.toLowerCase())" class="overflow-hidden rounded-lg border border-stone-200">
            <button @click="open = !open" class="flex w-full items-center justify-between bg-stone-50 px-4 py-3 text-left hover:bg-stone-100">
                <div class="flex items-center gap-3">
                    <span class="material-icons text-brand-900" aria-hidden="true">supervisor_account</span>
                    <span class="font-semibold">Gerente (Control y Supervisión)</span>
                </div>
                <svg :class="open ? 'transform rotate-90' : ''" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-600 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
            </button>
            <div x-show="open" x-transition class="p-5 text-[15px] leading-7 text-gray-700">
                <p class="text-sm text-gray-600">Responsable de supervisión financiera del inventario y aprobación de pérdidas.</p>

                <h3 class="mt-5 font-semibold text-gray-900">1. Aprobación de Bajas</h3>
                <p>Flujo: <em>Dashboard → Notificaciones/Alertas</em>. Revise solicitudes y <strong>Aprobar</strong> o <strong>Rechazar</strong> añadiendo comentario justificativo. Al aprobar, el repuesto se retira del inventario.</p>

                <h3 class="mt-5 font-semibold text-gray-900">2. Revisión de Reportes</h3>
                <p>Acceda a listados de stock bajo y reportes de movimientos para análisis operativo.</p>
            </div>
        </div>
        @endif

        <!-- Proveedor -->
        @if(in_array($role, ['superadministrador','administrador','proveedor']))
        <div x-data="{ open: {{ $role === 'proveedor' ? 'true' : 'false' }} }" x-show="!q || $el.textContent.toLowerCase().includes(q.toLowerCase())" class="overflow-hidden rounded-lg border border-stone-200">
            <button @click="open = !open" class="flex w-full items-center justify-between bg-stone-50 px-4 py-3 text-left hover:bg-stone-100">
                <div class="flex items-center gap-3">
                    <span class="material-icons text-brand-900" aria-hidden="true">local_shipping</span>
                    <span class="font-semibold">Proveedor</span>
                </div>
                <svg :class="open ? 'transform rotate-90' : ''" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-600 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
            </button>
            <div x-show="open" x-transition class="p-5 text-[15px] leading-7 text-gray-700">
                <p class="text-sm text-gray-600">Funciones principales: visualizar y gestionar las Órdenes de Compra (OC) que le asigna la organización, confirmar envíos, y subir la documentación asociada (facturas, guías).</p>

                <h3 class="mt-5 font-semibold text-gray-900">1. Ver Órdenes de Compra</h3>
                <p>Menú: <em>Orden de compra</em>. Una OC puede incluir varios repuestos, cada uno con su cantidad. Puede filtrar por estado y revisar todas las partidas antes de preparar el pedido.</p>

                <h3 class="mt-5 font-semibold text-gray-900">2. Confirmar despacho/entrega</h3>
                <ol class="ml-5 list-decimal list-outside">
                    <li>En la OC, marcar el estado como <strong>Enviado</strong> o <strong>Entregado</strong> según corresponda.</li>
                    <li>Agregar número de guía y fecha de envío/entrega.</li>
                    <li>Agregar observaciones si hay cambios en cantidades o fechas.</li>
                </ol>

                <h3 class="mt-5 font-semibold text-gray-900">3. Subir facturas</h3>
                <p>Proceso recomendado para adjuntar la factura a una OC:</p>
                <ol class="ml-5 list-decimal list-outside">
                    <li>Abra la OC correspondiente y localice el botón <strong>Adjuntar factura</strong> o <strong>Subir documento</strong>.</li>
                    <li>Seleccione el archivo de la factura completa (PDF preferible; también se aceptan JPG/PNG si es imagen). Se adjunta una sola factura a la OC, aunque contenga varios repuestos.</li>
                    <li>Haga clic en <strong>Subir factura</strong>. El documento queda vinculado a la OC; el ingreso de cada repuesto al inventario se realiza manualmente.</li>
                </ol>
                <p class="text-sm text-gray-600 mt-2">Consejos: use nombres de archivo claros (p. ej. <code>OC-1234_Factura_Proveedor.pdf</code>), y adjunte comprobantes de envío si están disponibles.</p>

                <h3 class="mt-5 font-semibold text-gray-900">4. Comunicaciones y discrepancias</h3>
                <p>Si hay discrepancias en cantidades o daños, registre un comentario en la OC y adjunte evidencia (fotos, reportes). Contacte al almacén o gerente según el caso.</p>
            </div>
        </div>
        @endif

        <!-- Administrador -->
        @if(in_array($role, ['superadministrador','administrador']))
        <div x-data="{ open: {{ in_array($role, ['administrador', 'superadministrador'], true) ? 'true' : 'false' }} }" x-show="!q || $el.textContent.toLowerCase().includes(q.toLowerCase())" class="overflow-hidden rounded-lg border border-stone-200">
            <button @click="open = !open" class="flex w-full items-center justify-between bg-stone-50 px-4 py-3 text-left hover:bg-stone-100">
                <div class="flex items-center gap-3">
                    <span class="material-icons text-brand-900" aria-hidden="true">settings</span>
                    <span class="font-semibold">Administrador (Mantenimiento del Sistema y Datos)</span>
                </div>
                <svg :class="open ? 'transform rotate-90' : ''" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-600 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
            </button>
            <div x-show="open" x-transition class="p-5 text-[15px] leading-7 text-gray-700">
                <p class="text-sm text-gray-600">Gestor de datos maestros y auditoría.</p>

                <h3 class="mt-5 font-semibold text-gray-900">1. Gestión de Repuestos (Maestro)</h3>
                <p>Solo el Administrador puede crear, editar o desactivar permanentemente registros de repuestos.</p>

                <h3 class="mt-5 font-semibold text-gray-900">2. Logs de Actividad (Auditoría)</h3>
                <p>Consulte quién realizó acciones críticas (ingresos, bajas, creación de cuentas) con fecha y hora en la sección de Historial.</p>

                <h3 class="mt-5 font-semibold text-gray-900">3. Gestión de Usuarios</h3>
                <p>Crear y gestionar cuentas operativas. Al asignar rol <strong>administrador</strong>, verifique el límite configurado en <em>Configuración → Registro</em>.</p>
            </div>
        </div>
        @endif

        <!-- Superusuario -->
        @if($role === 'superadministrador')
        <div x-data="{ open: {{ $role === 'superadministrador' ? 'true' : 'false' }} }" x-show="!q || $el.textContent.toLowerCase().includes(q.toLowerCase())" class="overflow-hidden rounded-lg border border-stone-200">
            <button @click="open = !open" class="flex w-full items-center justify-between bg-stone-50 px-4 py-3 text-left hover:bg-stone-100">
                <div class="flex items-center gap-3">
                    <span class="material-icons text-brand-900" aria-hidden="true">verified_user</span>
                    <span class="font-semibold">Superusuario (Seguridad Máxima)</span>
                </div>
                <svg :class="open ? 'transform rotate-90' : ''" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-600 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
            </button>
            <div x-show="open" x-transition class="p-5 text-[15px] leading-7 text-gray-700">
                <p class="text-sm text-gray-600">Rol reservado para gestión de seguridad de alto nivel.</p>

                <h3 class="mt-5 font-semibold text-gray-900">1. Gestión de Administradores</h3>
                <p>El superusuario es el único que puede crear/editar/eliminar cuentas con rol <strong>Administrador</strong>. Use esta cuenta solo para tareas de seguridad o emergencias.</p>
            </div>
        </div>
        @endif

        <!-- Consejos Generales -->
        @if(true)
        <div x-data="{ open: false }" x-show="!q || $el.textContent.toLowerCase().includes(q.toLowerCase())" class="overflow-hidden rounded-lg border border-stone-200">
            <button @click="open = !open" class="flex w-full items-center justify-between bg-stone-50 px-4 py-3 text-left hover:bg-stone-100">
                <div class="flex items-center gap-3">
                    <span class="material-icons text-brand-900" aria-hidden="true">lightbulb</span>
                    <span class="font-semibold">Consejos Generales</span>
                </div>
                <svg :class="open ? 'transform rotate-90' : ''" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-600 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
            </button>
            <div x-show="open" x-transition class="p-5 text-[15px] leading-7 text-gray-700">
                <ul class="ml-5 list-disc list-outside text-sm">
                    <li>Adjunte evidencia (fotos, facturas) en registros críticos.</li>
                    <li>Escriba comentarios claros al aprobar/rechazar o modificar registros importantes.</li>
                    <li>Revise periódicamente el `Histórico general` para auditoría.</li>
                    <li>No comparta credenciales; las acciones quedan registradas en logs.</li>
                </ul>
            </div>
        </div>
        @endif
    </div>
</div>
