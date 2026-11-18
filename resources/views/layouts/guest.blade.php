<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <link rel="icon" type="image/png" href="{{ asset('images/sittsa%20logo%20small.png') }}">

        <title>Gestión de Almacén</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
        <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="min-h-screen flex">
            <div class="w-full lg:w-1/2 flex items-center justify-center bg-white px-6 py-10">
                <div class="w-full max-w-md">
                    <a href="/" class="flex justify-center mb-6">
                        <img src="{{ asset('images/sittsa logo estandar.png') }}" alt="SITSSA" class="h-48 w-48 object-contain">
                    </a>
                    {{ $slot }}
                    <p class="mt-8 text-center text-xs text-gray-500">© {{ date('Y') }} SITSSA. Todos los derechos reservados.</p>
                </div>
            </div>

            <div class="hidden lg:flex lg:w-1/2 relative overflow-hidden items-end bg-[#2A0A0E]"
                 style="background-image: url('{{ asset('images/sitssa-home.jpg') }}'); background-size: cover; background-position: 62% center;">
                <div class="absolute inset-0 bg-gradient-to-t from-[#1E060A]/90 via-brand-900/55 to-[#140608]/35"></div>
                <div class="relative p-14">
                    <div class="w-12 h-1 rounded bg-[#E5334D] mb-4"></div>
                    <h2 class="text-4xl font-bold leading-tight text-white max-w-md drop-shadow">
                        Sistema de Gestión de Almacenes
                    </h2>
                </div>
            </div>
        </div>
    </body>
</html>
