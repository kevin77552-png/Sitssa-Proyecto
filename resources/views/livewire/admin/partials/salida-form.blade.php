<div
    class="space-y-5"
    x-data="{
        open: false,
        q: '',
        selected: null,
        units: @js($unidades),
        get filtered() {
            const query = this.q.trim().toLowerCase();
            return query === '' ? this.units : this.units.filter(unit => unit.search.includes(query));
        },
        pick(unit) {
            this.selected = unit;
            this.q = unit.placa;
            this.open = false;
            $wire.vehiculo_id = unit.id;
        },
        reset() {
            this.selected = null;
            $wire.vehiculo_id = '';
        }
    }"
>
    <div class="relative" @click.outside="open = false">
        <label for="outgoingVehicleSearch" class="mb-1 block text-sm font-medium text-slate-700">Unidad</label>
        <input
            id="outgoingVehicleSearch"
            type="text"
            x-model="q"
            autocomplete="off"
            @focus="open = true"
            @input="open = true; reset()"
            placeholder="Busca por placa, tipo o modelo"
            aria-autocomplete="list"
            class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:border-brand-600 focus:outline-none focus:ring-1 focus:ring-brand-600"
        >

        <ul
            x-show="open"
            x-cloak
            class="absolute z-10 mt-1 max-h-56 w-full overflow-auto rounded-lg border border-slate-200 bg-white py-1 shadow-lg"
            role="listbox"
        >
            <template x-for="unit in filtered" :key="unit.id">
                <li role="option">
                    <button
                        type="button"
                        @click="pick(unit)"
                        class="flex w-full min-w-0 flex-col gap-0.5 px-3 py-2 text-left text-sm transition hover:bg-slate-50 sm:flex-row sm:items-baseline sm:justify-between sm:gap-3"
                    >
                        <span class="shrink-0 font-medium text-slate-900" x-text="unit.placa"></span>
                        <span class="truncate text-xs text-slate-500" x-text="[unit.desc, unit.tipo, unit.modelo].filter(Boolean).join(' · ')"></span>
                    </button>
                </li>
            </template>
            <li x-show="filtered.length === 0" class="px-3 py-2 text-sm text-slate-500">
                No hay una unidad con ese dato. Pide al administrador que la registre.
            </li>
        </ul>

        @error('vehiculo_id')
            <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
        @enderror
        @if($unidades === [])
            <p class="mt-1 text-xs text-amber-700">No hay unidades activas disponibles en el catálogo.</p>
        @endif
    </div>

    <div x-show="selected" x-cloak class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600">
        <span class="text-xs uppercase tracking-wide text-slate-400">Descripción de la unidad</span>
        <span class="mt-0.5 block font-medium text-slate-800" x-text="selected ? [selected.desc, selected.tipo, selected.modelo].filter(Boolean).join(' · ') : ''"></span>
    </div>

    <div>
        <label for="outgoing-recipient" class="mb-1 block text-sm font-medium text-slate-700">
            Entregado a <span class="text-rose-600">*</span>
        </label>
        <input
            id="outgoing-recipient"
            type="text"
            wire:model.defer="entregadoA"
            maxlength="120"
            autocomplete="name"
            placeholder="Nombre de la persona que recibe"
            class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:border-brand-600 focus:outline-none focus:ring-1 focus:ring-brand-600"
        >
        @error('entregadoA')
            <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="outgoingReasonType" class="mb-1 block text-sm font-medium text-slate-700">Motivo de la entrega</label>
        <select
            id="outgoingReasonType"
            wire:model.live="motivo_tipo"
            class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm text-slate-900 focus:border-brand-600 focus:outline-none focus:ring-1 focus:ring-brand-600"
        >
            <option value="">Selecciona un motivo</option>
            @foreach($motivos as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
        @error('motivo_tipo')
            <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="outgoingReason" class="mb-1 block text-sm font-medium text-slate-700">
            {{ $motivo_tipo === 'otro' ? 'Explica el motivo' : 'Observación (opcional)' }}
            @if($motivo_tipo === 'otro')
                <span class="text-rose-600">*</span>
            @endif
        </label>
        <textarea
            id="outgoingReason"
            wire:model.defer="motivo"
            rows="2"
            maxlength="255"
            placeholder="{{ $motivo_tipo === 'otro' ? 'Describe por qué sale el repuesto' : 'Ej.: cambio programado a los 20.000 km' }}"
            class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:border-brand-600 focus:outline-none focus:ring-1 focus:ring-brand-600"
        ></textarea>
        @error('motivo')
            <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
        @enderror
    </div>

    <p x-show="selected" x-cloak class="text-xs text-slate-500">
        Se descontará <strong>1 unidad</strong> para <strong x-text="selected ? selected.placa : ''"></strong>.
    </p>
</div>
