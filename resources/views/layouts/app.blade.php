<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <link rel="icon" type="image/png" href="{{ asset('images/sittsa%20logo%20small.png') }}">

        <title>Gestión de Almacén</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Material Icons -->
        <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased" data-login-success="{{ session()->has('login-success') ? 'true' : 'false' }}">
        @php($standalonePage = filled(trim((string) ($slot ?? ''))))
        <div class="flex min-h-screen bg-stone-100">
            <livewire:sidebar :standalone-page="$standalonePage" />
            <div class="min-w-0 flex-1">
                <livewire:layout.navigation />
                <div>
                    <!-- Page Heading -->
                    @if (isset($header))
                        <header class="bg-white">
                            <div class="px-8 py-4">
                                {{ $header }}
                            </div>
                        </header>
                    @endif
                    <!-- Page Content -->
                    <main class="px-8 py-8">
                        @if (! $standalonePage)
                            <livewire:dynamic-module />
                        @endif
                        {{ $slot ?? '' }}
                        @yield('content')
                    </main>
                </div>
            </div>
        </div>
    </body>
</html>
