<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        $this->form->authenticate();

        Session::regenerate();

        $this->dispatch('login-success');
        // La redirección se hará desde JS tras el SweetAlert
    }
}; ?>

<div>
    <h1 class="text-center text-xl font-bold text-gray-900">Iniciar sesión</h1>
    <p class="mb-6 mt-1 text-center text-sm text-gray-500">Accede con tu correo y contraseña.</p>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form wire:submit="login" class="space-y-4">
        <div>
            <label for="email" class="mb-1 block text-sm font-medium text-gray-700">Correo electrónico</label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                    <span class="material-icons text-[20px] text-gray-400">email</span>
                </span>
                <input wire:model="form.email" id="email" type="email" required autofocus autocomplete="username" maxlength="120"
                    class="field w-full pl-10 focus:border-brand-600 focus:ring-brand-600">
            </div>
            <x-input-error :messages="$errors->get('form.email')" class="mt-1" />
        </div>

        <div x-data="{ show: false }">
            <label for="password" class="mb-1 block text-sm font-medium text-gray-700">Contraseña</label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                    <span class="material-icons text-[20px] text-gray-400">lock</span>
                </span>
                <input wire:model="form.password" id="password" :type="show ? 'text' : 'password'" required
                    autocomplete="current-password" minlength="8" maxlength="64"
                    class="field w-full pl-10 pr-10 focus:border-brand-600 focus:ring-brand-600">
                <button type="button" @click="show = !show" aria-label="Mostrar contraseña"
                    class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600">
                    <span class="material-icons text-[20px]" x-text="show ? 'visibility_off' : 'visibility'"></span>
                </button>
            </div>
            <x-input-error :messages="$errors->get('form.password')" class="mt-1" />
        </div>

        <div class="flex items-center justify-between gap-3 text-sm">
            <label for="remember" class="inline-flex items-center gap-2 text-gray-600">
                <input wire:model="form.remember" id="remember" type="checkbox"
                    class="rounded border-stone-300 text-brand-600 focus:ring-brand-600">
                Recuérdame
            </label>
            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" wire:navigate class="font-medium text-brand-600 hover:underline">
                    ¿Olvidaste tu contraseña?
                </a>
            @endif
        </div>

        <button type="submit" wire:loading.attr="disabled" wire:target="login" class="btn-primary w-full disabled:opacity-60">
            <span wire:loading.remove wire:target="login">Iniciar sesión</span>
            <span wire:loading wire:target="login">Ingresando…</span>
        </button>
    </form>
</div>
