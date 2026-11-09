<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Gestión del Cambio') }}</title>
    <script>
        (function () {
            var stored = localStorage.getItem('theme');
            var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            if (stored === 'dark' || (!stored && prefersDark)) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-canvas dark:bg-canvas-dark">
<div class="min-h-screen lg:grid lg:grid-cols-2">
    {{-- Panel de marca, solo lg+ --}}
    <div class="hidden lg:flex relative flex-col justify-between bg-black text-white p-12 overflow-hidden">
        <div class="absolute -top-24 -right-24 h-96 w-96 rounded-full bg-brand-700/40 blur-3xl"></div>
        <div class="absolute -bottom-32 -left-16 h-96 w-96 rounded-full bg-brand-900/40 blur-3xl"></div>

        <div class="relative flex items-center gap-3">
            <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-brand-600 text-white">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 12a9 9 0 1 1-3-6.7"/><polyline points="21 3 21 9 15 9"/>
                </svg>
            </span>
            <span class="text-lg font-bold">{{ config('app.name', 'Gestión del Cambio') }}</span>
        </div>

        <div class="relative">
            <h1 class="text-3xl font-bold leading-tight text-white">Gestiona el ciclo de cambios de forma simple y confiable.</h1>
            <p class="mt-3 text-sm text-gray-300">Acceso exclusivo para personal autorizado.</p>
        </div>

        <p class="relative text-xs text-gray-400">&copy; {{ date('Y') }} {{ config('app.name', 'Gestión del Cambio') }}.</p>
    </div>

    {{-- Formulario --}}
    <div class="flex flex-col justify-center px-4 py-12 sm:px-6 lg:px-12">
        <div class="mb-8 flex items-center justify-between lg:hidden">
            <div class="flex items-center gap-2">
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-brand-600 text-white">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 12a9 9 0 1 1-3-6.7"/><polyline points="21 3 21 9 15 9"/>
                    </svg>
                </span>
                <span class="text-base font-bold text-ink-900 dark:text-gray-100">{{ config('app.name', 'Gestión del Cambio') }}</span>
            </div>
            <x-theme-toggle />
        </div>

        <div class="w-full max-w-sm mx-auto lg:mx-0">
            <div class="hidden justify-end mb-6 lg:flex">
                <x-theme-toggle />
            </div>

            <div class="bg-surface dark:bg-surface-dark border border-line dark:border-white/10 rounded-3xl shadow-sm p-8">
                {{ $slot }}
            </div>

            <p class="mt-4 text-center text-xs text-ink-secondary dark:text-gray-400">Sistema interno · uso autorizado</p>
        </div>
    </div>
</div>
</body>
</html>
