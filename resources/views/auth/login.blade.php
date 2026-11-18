<x-guest-layout>
    <h1 class="text-center text-xl font-bold text-gray-900">Iniciar sesión</h1>
    <p class="mb-6 mt-1 text-center text-sm text-gray-500">Accede con tu correo y contraseña.</p>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    @if ($errors->any())
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
            <ul class="list-inside list-disc">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <div>
            <label for="email" class="mb-1 block text-sm font-medium text-gray-700">Correo electrónico</label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                    <span class="material-icons text-[20px] text-gray-400" aria-hidden="true">email</span>
                </span>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                    class="field w-full pl-10 focus:border-brand-600 focus:ring-brand-600">
            </div>
        </div>

        <div>
            <label for="password" class="mb-1 block text-sm font-medium text-gray-700">Contraseña</label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                    <span class="material-icons text-[20px] text-gray-400" aria-hidden="true">lock</span>
                </span>
                <input id="password" name="password" type="password" required autocomplete="current-password"
                    class="field w-full pl-10 pr-10 focus:border-brand-600 focus:ring-brand-600">
                <button type="button" aria-label="Mostrar contraseña"
                    onclick="const input = document.getElementById('password'); input.type = input.type === 'password' ? 'text' : 'password'; this.setAttribute('aria-label', input.type === 'password' ? 'Mostrar contraseña' : 'Ocultar contraseña'); this.querySelector('[data-password-icon]').textContent = input.type === 'password' ? 'visibility' : 'visibility_off';"
                    class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600">
                    <span class="material-icons text-[20px]" data-password-icon aria-hidden="true">visibility</span>
                </button>
            </div>
        </div>

        <div class="flex items-center justify-between gap-3 text-sm">
            <label for="remember" class="inline-flex items-center gap-2 text-gray-600">
                <input id="remember" type="checkbox" name="remember" value="1" @checked(old('remember'))
                    class="rounded border-stone-300 text-brand-600 focus:ring-brand-600">
                Recuérdame
            </label>
            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" class="font-medium text-brand-600 hover:underline">
                    ¿Olvidaste tu contraseña?
                </a>
            @endif
        </div>

        <button type="submit" class="btn-primary w-full">
            Iniciar sesión
        </button>
    </form>
</x-guest-layout>
