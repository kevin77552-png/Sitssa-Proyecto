<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;

new class extends Component
{
    public function openProfile(): void
    {
        $this->dispatch('showModule', 'admin.profile');
    }

    public function logout(Logout $logout): void
    {
        $logout();
        $this->redirect(route('login'));
    }
}; ?>

@php
    $nameParts = preg_split('/\s+/', trim(auth()->user()->name));
    $initials = mb_strtoupper(mb_substr($nameParts[0] ?? '', 0, 1) . mb_substr($nameParts[1] ?? '', 0, 1));
@endphp

<nav x-data="{ open: false }" class="border-b border-stone-200 bg-white">
    <div class="flex h-16 items-center justify-end px-8">
        <div class="hidden sm:flex sm:items-center">
            <x-dropdown align="right" width="48">
                <x-slot name="trigger">
                    <button class="flex items-center gap-3 rounded-lg px-2 py-1.5 text-sm text-gray-700 transition hover:bg-stone-50 focus:outline-none">
                        <div class="flex items-center gap-3">
                            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-brand-900 text-xs font-semibold text-white">
                                {{ $initials }}
                            </span>
                            <span class="text-left leading-tight">
                                <span x-data="{{ json_encode(['name' => auth()->user()->name]) }}"
                                    x-text="name"
                                    x-on:profile-updated.window="name = $event.detail.name"
                                    class="block text-sm font-semibold text-gray-900"></span>
                                <span class="block text-xs capitalize text-gray-500">{{ __(auth()->user()->role) }}</span>
                            </span>
                        </div>
                        <svg class="h-4 w-4 fill-current text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </x-slot>

                <x-slot name="content">
                    <button wire:click="openProfile" class="w-full text-start">
                        <x-dropdown-link>{{ __('Perfil') }}</x-dropdown-link>
                    </button>
                    <button wire:click="logout" class="w-full text-start">
                        <x-dropdown-link>{{ __('Cerrar sesión') }}</x-dropdown-link>
                    </button>
                </x-slot>
            </x-dropdown>
        </div>

        <button @click="open = !open" class="rounded p-2 text-gray-500 hover:bg-stone-100 sm:hidden" aria-label="Abrir menú de usuario">
            <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                <path :class="{'hidden': open, 'inline-flex': !open}" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                <path :class="{'hidden': !open, 'inline-flex': open}" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    <div x-show="open" x-cloak class="border-t border-stone-200 px-8 py-3 sm:hidden">
        <div class="mb-3 text-sm text-gray-600">{{ auth()->user()->name }} - {{ __(auth()->user()->role) }}</div>
        <button wire:click="openProfile" class="mr-4 text-sm text-brand-900">{{ __('Perfil') }}</button>
        <button wire:click="logout" class="text-sm text-gray-600">{{ __('Cerrar sesión') }}</button>
    </div>
</nav>
