<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';
    public string $role = 'almacenista';
    public bool $success = false;
    public int $successVersion = 0;

    public function resetForm(): void
    {
        $this->reset();
        $this->resetValidation();
    }

    public function register(): void
    {
        $this->success = false;

        $currentUser = Auth::user();

        if (! $currentUser) {
            $this->role = 'almacenista';
        }

        $assignableRoles = match ($currentUser?->role) {
            'superadministrador' => ['administrador', 'superadministrador', 'gerente', 'proveedor', 'almacenista'],
            'administrador' => ['gerente', 'proveedor', 'almacenista'],
            default => ['almacenista'],
        };

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'role' => ['required', Rule::in($assignableRoles)],
            'password' => [
                'required',
                'string',
                'confirmed',
                Rules\Password::defaults(),
                'regex:/[A-Z]/',
                'regex:/[^A-Za-z0-9]/',
            ],
        ], [
            'password.regex' => 'La contraseña debe contener al menos una letra mayúscula y un carácter especial.',
        ]);

        if ($validated['role'] === 'administrador') {
            $limit = (int) \App\Models\Setting::getValue('max_administradores', 2);
            $adminCount = User::where('role', 'administrador')->where('activo', true)->count();

            if ($adminCount >= $limit) {
                $this->addError('role', "No se pueden crear más de {$limit} usuarios con rol administrador activos.");

                return;
            }
        }

        $validated['password'] = Hash::make($validated['password']);

        event(new Registered($user = User::create($validated)));

        $this->success = true;
        $this->successVersion++;
        $this->reset(['name', 'email', 'password', 'password_confirmation', 'role']);
    }
}; ?>

@php
    $roleOptions = [
        'almacenista' => [
            'label' => 'Almacenista',
            'description' => 'Ingreso de repuestos, consulta de stock, despachos y solicitudes.',
        ],
        'gerente' => [
            'label' => 'Gerente',
            'description' => 'Revisión y aprobación de solicitudes y órdenes de compra.',
        ],
        'proveedor' => [
            'label' => 'Proveedor',
            'description' => 'Consulta de órdenes de compra asignadas a su cuenta.',
        ],
        'administrador' => [
            'label' => 'Administrador',
            'description' => 'Auditoría de inventario, aprobaciones y gestión de usuarios.',
        ],
        'superadministrador' => [
            'label' => 'Superadministrador',
            'description' => 'Control total del sistema y configuración de accesos.',
        ],
    ];
    $availableRoles = auth()->user()?->role === 'superadministrador'
        ? ['administrador', 'superadministrador', 'gerente', 'proveedor', 'almacenista']
        : ['gerente', 'proveedor', 'almacenista'];
@endphp

<div @class([
    'w-full overflow-hidden rounded-xl border border-slate-200/90 bg-white shadow-sm' => auth()->check(),
    'w-full' => auth()->guest(),
])>
    @auth
        <header class="flex flex-col gap-4 border-b border-slate-100 bg-white px-6 py-6 sm:flex-row sm:items-center sm:px-8">
            <div class="flex items-start gap-3.5">
                <div class="mt-0.5 shrink-0 rounded-lg border border-[#4A0C12]/10 bg-[#4A0C12]/5 p-2.5 text-[#4A0C12]">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                    </svg>
                </div>
                <div>
                    <h1 class="text-xl font-bold tracking-tight text-slate-900">Registrar usuario</h1>
                    <p class="mt-0.5 text-sm text-slate-500">Crea una cuenta de acceso para el personal y asigna sus permisos.</p>
                </div>
            </div>
        </header>
    @else
        <div class="mb-5">
            <h1 class="text-xl font-bold tracking-tight text-slate-900">Crear cuenta</h1>
            <p class="mt-1 text-sm text-slate-500">Completa tus datos para registrarte.</p>
        </div>
    @endauth

    <form wire:submit="register" @class([
        'space-y-7 p-6 sm:p-8' => auth()->check(),
        'space-y-5' => auth()->guest(),
    ])>
        <section>
            <div class="mb-4 flex items-center gap-2">
                <span class="h-4 w-1.5 rounded-full bg-[#4A0C12]"></span>
                <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-500">Datos de la cuenta</h2>
            </div>

            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                <div>
                    <label for="register-name" class="mb-1.5 block text-sm font-medium text-slate-700">
                        Nombre completo <span class="text-[#4A0C12]">*</span>
                    </label>
                    <div class="relative">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                        </div>
                        <input wire:model="name" type="text" id="register-name" name="name" required autofocus autocomplete="name" placeholder="Ej. Félix Sánchez" maxlength="255"
                            class="w-full rounded-lg border border-slate-300 bg-slate-50/50 py-2.5 pl-10 pr-3.5 text-sm text-slate-800 placeholder:text-slate-400 transition focus:border-[#4A0C12] focus:bg-white focus:outline-none focus:ring-4 focus:ring-[#4A0C12]/10">
                    </div>
                    <x-input-error :messages="$errors->get('name')" class="mt-1.5" />
                </div>

                <div>
                    <label for="register-email" class="mb-1.5 block text-sm font-medium text-slate-700">
                        Correo electrónico <span class="text-[#4A0C12]">*</span>
                    </label>
                    <div class="relative">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <input wire:model="email" type="email" id="register-email" name="email" required autocomplete="username" placeholder="usuario@empresa.com" maxlength="255"
                            class="w-full rounded-lg border border-slate-300 bg-slate-50/50 py-2.5 pl-10 pr-3.5 text-sm text-slate-800 placeholder:text-slate-400 transition focus:border-[#4A0C12] focus:bg-white focus:outline-none focus:ring-4 focus:ring-[#4A0C12]/10">
                    </div>
                    <x-input-error :messages="$errors->get('email')" class="mt-1.5" />
                </div>
            </div>
        </section>

        @auth
            <hr class="border-slate-100">

            <section>
                <div class="mb-3 flex items-center gap-2">
                    <span class="h-4 w-1.5 rounded-full bg-[#4A0C12]"></span>
                    <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-500">
                        Rol y nivel de acceso <span class="text-[#4A0C12]">*</span>
                    </h2>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3" role="radiogroup" aria-label="Rol y nivel de acceso">
                    @foreach($availableRoles as $roleValue)
                        <label wire:key="register-role-{{ $roleValue }}" @class([
                            'relative flex cursor-pointer select-none flex-col rounded-xl p-3.5 transition',
                            'border-2 border-[#4A0C12] bg-[#4A0C12]/5' => $role === $roleValue,
                            'border border-slate-200 bg-white hover:border-slate-300' => $role !== $roleValue,
                        ])>
                            <input wire:model.live="role" type="radio" name="role" value="{{ $roleValue }}" class="sr-only">
                            <span class="mb-1 flex items-center justify-between gap-2">
                                <span class="text-sm font-bold text-slate-900">{{ $roleOptions[$roleValue]['label'] }}</span>
                                <span @class([
                                    'role-indicator h-4 w-4 shrink-0 rounded-full bg-white',
                                    'border-4 border-[#4A0C12]' => $role === $roleValue,
                                    'border border-slate-300' => $role !== $roleValue,
                                ])></span>
                            </span>
                            <span class="text-[11px] leading-relaxed text-slate-500">{{ $roleOptions[$roleValue]['description'] }}</span>
                        </label>
                    @endforeach
                </div>
                <x-input-error :messages="$errors->get('role')" class="mt-2" />
            </section>
        @endauth

        <hr class="border-slate-100">

        <section>
            <div class="mb-4 flex items-center gap-2">
                <span class="h-4 w-1.5 rounded-full bg-[#4A0C12]"></span>
                <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-500">Credenciales de seguridad</h2>
            </div>

            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                <div x-data="{ showPassword: false }">
                    <label for="register-password" class="mb-1.5 block text-sm font-medium text-slate-700">
                        Contraseña <span class="text-[#4A0C12]">*</span>
                    </label>
                    <div class="relative">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                            </svg>
                        </div>
                        <input wire:model="password" :type="showPassword ? 'text' : 'password'" id="register-password" name="password" required autocomplete="new-password" placeholder="Mínimo 8 caracteres"
                            class="w-full rounded-lg border border-slate-300 bg-slate-50/50 py-2.5 pl-10 pr-10 text-sm text-slate-800 placeholder:text-slate-400 transition focus:border-[#4A0C12] focus:bg-white focus:outline-none focus:ring-4 focus:ring-[#4A0C12]/10">
                        <button type="button" @click="showPassword = !showPassword" :aria-label="showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'"
                            class="absolute inset-y-0 right-0 flex cursor-pointer items-center pr-3 text-slate-400 hover:text-slate-700">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                        </button>
                    </div>
                    <p class="mt-1.5 text-[11px] text-slate-400">Debe incluir al menos una mayúscula y un carácter especial.</p>
                    <x-input-error :messages="$errors->get('password')" class="mt-1.5" />
                </div>

                <div x-data="{ showPassword: false }">
                    <label for="register-password-confirmation" class="mb-1.5 block text-sm font-medium text-slate-700">
                        Confirmar contraseña <span class="text-[#4A0C12]">*</span>
                    </label>
                    <div class="relative">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <input wire:model="password_confirmation" :type="showPassword ? 'text' : 'password'" id="register-password-confirmation" name="password_confirmation" required autocomplete="new-password" placeholder="Repite la contraseña"
                            class="w-full rounded-lg border border-slate-300 bg-slate-50/50 py-2.5 pl-10 pr-10 text-sm text-slate-800 placeholder:text-slate-400 transition focus:border-[#4A0C12] focus:bg-white focus:outline-none focus:ring-4 focus:ring-[#4A0C12]/10">
                        <button type="button" @click="showPassword = !showPassword" :aria-label="showPassword ? 'Ocultar confirmación' : 'Mostrar confirmación'"
                            class="absolute inset-y-0 right-0 flex cursor-pointer items-center pr-3 text-slate-400 hover:text-slate-700">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                        </button>
                    </div>
                    <x-input-error :messages="$errors->get('password_confirmation')" class="mt-1.5" />
                </div>
            </div>
        </section>

        <div @class([
            'flex flex-col-reverse items-center justify-end gap-3 border-t border-slate-100 pt-6 sm:flex-row' => auth()->check(),
            'flex flex-col-reverse items-center justify-end gap-3 sm:flex-row' => auth()->guest(),
        ])>
            <button type="button" wire:click="resetForm"
                class="w-full cursor-pointer rounded-lg px-4 py-2.5 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 sm:w-auto">
                Limpiar formulario
            </button>
            <button type="submit" wire:loading.attr="disabled" wire:target="register"
                class="inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-lg bg-[#4A0C12] px-6 py-2.5 text-sm font-medium text-white shadow-sm shadow-[#4A0C12]/20 transition hover:bg-[#3A090E] active:bg-[#30070B] disabled:cursor-wait disabled:opacity-70 sm:w-auto">
                <svg wire:loading.remove wire:target="register" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                </svg>
                <span wire:loading.remove wire:target="register">{{ auth()->check() ? 'Crear usuario' : 'Registrarse' }}</span>
                <span wire:loading wire:target="register">Registrando...</span>
            </button>
        </div>

        @guest
            <div class="text-right">
                <a class="text-sm text-slate-600 underline hover:text-slate-900" href="{{ route('login') }}" wire:navigate>
                    ¿Ya tienes una cuenta?
                </a>
            </div>
        @endguest
    </form>

    @if($success)
        <div wire:key="registration-success-{{ $successVersion }}" x-data="{ visible: true }" x-show="visible" x-transition x-init="setTimeout(() => visible = false, 4000)" role="status"
            class="fixed bottom-5 right-5 z-50 flex w-full max-w-sm items-start gap-3 rounded-xl border border-slate-800 bg-slate-900 p-4 text-white shadow-lg">
            <div class="mt-0.5 shrink-0 rounded-lg bg-[#4A0C12] p-1.5 text-white">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div class="flex-1 text-sm">
                <p class="font-semibold text-white">Usuario creado correctamente</p>
                <p class="mt-1 text-xs text-slate-300">La cuenta ya puede acceder al sistema.</p>
            </div>
        </div>
    @endif
</div>
