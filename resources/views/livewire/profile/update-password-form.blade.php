<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;

new class extends Component
{
    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Update the password for the currently authenticated user.
     */
    public function updatePassword(): void
    {
        try {
            $validated = $this->validate([
                'current_password' => ['required', 'string', 'current_password'],
                // Exigir al menos una letra mayúscula y un carácter especial además de las reglas por defecto
                'password' => ['required', 'string', Password::defaults(), 'confirmed', 'regex:/[A-Z]/', 'regex:/[^A-Za-z0-9]/'],
            ], [
                'password.regex' => 'La contraseña debe contener al menos una letra mayúscula y un carácter especial.',
            ]);
        } catch (ValidationException $e) {
            $this->reset('current_password', 'password', 'password_confirmation');

            throw $e;
        }

        Auth::user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        $this->reset('current_password', 'password', 'password_confirmation');

        $this->dispatch('password-updated');
    }
}; ?>

<section class="flex flex-col justify-between overflow-hidden rounded-2xl border border-[#E4E4E7] bg-white transition duration-200">
    <form wire:submit="updatePassword" class="flex flex-1 flex-col justify-between">
        <div class="p-6">
            <header class="mb-5 border-b border-zinc-100 pb-5">
                <h2 class="text-base font-semibold text-zinc-900">Actualizar contraseña</h2>
                <p class="mt-1 text-xs leading-relaxed text-zinc-500">
                    Cambia tu contraseña con las reglas de seguridad vigentes para proteger tu cuenta.
                </p>
            </header>

            <div class="space-y-4">
                <div>
                    <label for="update_password_current_password" class="mb-1.5 block text-xs font-medium text-zinc-700">Contraseña actual</label>
                    <input wire:model="current_password" id="update_password_current_password" name="current_password" type="password" autocomplete="current-password"
                        class="block w-full rounded-xl border border-[#E4E4E7] bg-white px-3.5 py-2.5 text-sm text-zinc-900 transition-colors placeholder:text-zinc-400 focus:border-zinc-900 focus:outline-none focus:ring-2 focus:ring-zinc-900/10">
                    <x-input-error :messages="$errors->get('current_password')" class="mt-1.5" />
                </div>

                <div>
                    <label for="update_password_password" class="mb-1.5 block text-xs font-medium text-zinc-700">Nueva contraseña</label>
                    <input wire:model="password" id="update_password_password" name="password" type="password" autocomplete="new-password"
                        class="block w-full rounded-xl border border-[#E4E4E7] bg-white px-3.5 py-2.5 text-sm text-zinc-900 transition-colors placeholder:text-zinc-400 focus:border-zinc-900 focus:outline-none focus:ring-2 focus:ring-zinc-900/10">
                    <x-input-error :messages="$errors->get('password')" class="mt-1.5" />
                </div>

                <div>
                    <label for="update_password_password_confirmation" class="mb-1.5 block text-xs font-medium text-zinc-700">Confirmar contraseña</label>
                    <input wire:model="password_confirmation" id="update_password_password_confirmation" name="password_confirmation" type="password" autocomplete="new-password"
                        class="block w-full rounded-xl border border-[#E4E4E7] bg-white px-3.5 py-2.5 text-sm text-zinc-900 transition-colors placeholder:text-zinc-400 focus:border-zinc-900 focus:outline-none focus:ring-2 focus:ring-zinc-900/10">
                    <x-input-error :messages="$errors->get('password_confirmation')" class="mt-1.5" />
                </div>
            </div>
        </div>

        <footer class="flex flex-wrap items-center justify-end gap-3 border-t border-zinc-100 bg-zinc-50/80 px-6 py-4">
            <x-action-message class="text-xs text-emerald-700" on="password-updated">Contraseña actualizada.</x-action-message>
            <button type="submit" wire:loading.attr="disabled" wire:target="updatePassword"
                class="inline-flex cursor-pointer items-center justify-center rounded-xl bg-[#09090B] px-4 py-2 text-xs font-semibold text-white transition hover:bg-zinc-800 active:scale-[0.99] disabled:cursor-wait disabled:opacity-60">
                <span wire:loading.remove wire:target="updatePassword">Actualizar contraseña</span>
                <span wire:loading wire:target="updatePassword">Actualizando...</span>
            </button>
        </footer>
    </form>
</section>
