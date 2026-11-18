<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component
{
    public string $name = '';
    public string $email = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
        ]);

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $this->dispatch(
            'profile-updated',
            name: $user->name,
            email: $user->email,
            verified: $user->hasVerifiedEmail()
        );
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function sendVerification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }
}; ?>

<section class="flex flex-col justify-between overflow-hidden rounded-2xl border border-[#E4E4E7] bg-white transition duration-200">
    <form wire:submit="updateProfileInformation" class="flex flex-1 flex-col justify-between">
        <div class="p-6">
            <header class="mb-5 border-b border-zinc-100 pb-5">
                <h2 class="text-base font-semibold text-zinc-900">Información personal</h2>
                <p class="mt-1 text-xs leading-relaxed text-zinc-500">
                    Actualiza tu nombre visible y el correo electrónico asociado a tu cuenta.
                </p>
            </header>

            <div class="space-y-4">
                <div>
                    <label for="name" class="mb-1.5 block text-xs font-medium text-zinc-700">Nombre completo</label>
                    <input wire:model="name" id="name" name="name" type="text" required autofocus autocomplete="name"
                        class="block w-full rounded-xl border border-[#E4E4E7] bg-white px-3.5 py-2.5 text-sm text-zinc-900 transition-colors placeholder:text-zinc-400 focus:border-zinc-900 focus:outline-none focus:ring-2 focus:ring-zinc-900/10">
                    <x-input-error class="mt-1.5" :messages="$errors->get('name')" />
                </div>

                <div>
                    <label for="email" class="mb-1.5 block text-xs font-medium text-zinc-700">Correo electrónico</label>
                    <input wire:model="email" id="email" name="email" type="email" required autocomplete="username"
                        class="block w-full rounded-xl border border-[#E4E4E7] bg-white px-3.5 py-2.5 text-sm text-zinc-900 transition-colors placeholder:text-zinc-400 focus:border-zinc-900 focus:outline-none focus:ring-2 focus:ring-zinc-900/10">
                    <x-input-error class="mt-1.5" :messages="$errors->get('email')" />

                    @if (auth()->user() instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! auth()->user()->hasVerifiedEmail())
                        <div class="mt-3 rounded-xl border border-amber-200/70 bg-amber-50/70 p-3.5">
                            <p class="text-xs leading-relaxed text-amber-900">
                                Tu correo electrónico no está verificado.
                                <button type="button" wire:click="sendVerification" class="ml-1 font-medium underline underline-offset-2 hover:text-amber-950">
                                    Reenviar correo de verificación
                                </button>
                            </p>
                            @if (session('status') === 'verification-link-sent')
                                <p role="status" class="mt-2 text-xs font-medium text-emerald-700">
                                    Se envió un nuevo enlace de verificación.
                                </p>
                            @endif
                        </div>
                    @endif
                </div>

                <div class="flex items-start gap-2.5 rounded-xl border border-zinc-200/70 bg-zinc-50 p-3.5">
                    <span class="material-icons mt-0.5 h-4 w-4 shrink-0 text-zinc-400" aria-hidden="true">info</span>
                    <p class="text-xs leading-relaxed text-zinc-500">
                        Este correo se utiliza para iniciar sesión, recibir alertas y recuperar el acceso.
                    </p>
                </div>
            </div>
        </div>

        <footer class="flex flex-wrap items-center justify-end gap-3 border-t border-zinc-100 bg-zinc-50/80 px-6 py-4">
            <x-action-message class="text-xs text-emerald-700" on="profile-updated">Cambios guardados.</x-action-message>
            <button type="submit" wire:loading.attr="disabled" wire:target="updateProfileInformation"
                class="inline-flex cursor-pointer items-center justify-center rounded-xl bg-[#09090B] px-4 py-2 text-xs font-semibold text-white transition hover:bg-zinc-800 active:scale-[0.99] disabled:cursor-wait disabled:opacity-60">
                <span wire:loading.remove wire:target="updateProfileInformation">Guardar cambios</span>
                <span wire:loading wire:target="updateProfileInformation">Guardando...</span>
            </button>
        </footer>
    </form>
</section>
