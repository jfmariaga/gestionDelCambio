<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · {{ config('app.name', 'Gestión del Cambio') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans">
<div class="flex min-h-screen items-center justify-center bg-ink-100 px-4 py-10">
    <div class="w-full max-w-md text-center">
        <div class="mb-6 flex items-center justify-center gap-3">
            <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-700 text-white">
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 12a9 9 0 1 1-3-6.7"/><polyline points="21 3 21 9 15 9"/>
                </svg>
            </span>
            <div class="text-left leading-tight">
                <div class="text-base font-bold text-ink-900">Gestión del Cambio</div>
                <div class="text-xs text-ink-400">Formato FOSIG-02</div>
            </div>
        </div>

        <div class="card card-pad">
            <p class="text-sm font-semibold text-brand-700">Error {{ $code }}</p>
            <h1 class="mt-1 text-xl font-bold text-ink-900">{{ $title }}</h1>
            <p class="mt-2 text-sm text-ink-500">{{ $message }}</p>

            <a href="{{ url('/') }}"
               class="mt-6 inline-flex items-center justify-center rounded-lg bg-brand-700 px-4 py-2 text-sm font-medium text-white hover:bg-brand-800">
                Volver al inicio
            </a>
        </div>

        <p class="mt-4 text-center text-xs text-ink-400">Sistema interno · uso autorizado</p>
    </div>
</div>
</body>
</html>
