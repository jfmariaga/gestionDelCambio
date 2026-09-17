<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Gestión del Cambio') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans">
<div x-data="{ sidebar: false }" class="min-h-screen lg:flex">

    {{-- Backdrop (móvil) --}}
    <div x-show="sidebar" x-transition.opacity x-cloak
         class="fixed inset-0 z-30 bg-ink-900/40 lg:hidden" @click="sidebar = false"></div>

    {{-- Sidebar: fijo en pantallas grandes, permanece al hacer scroll --}}
    <aside :class="sidebar ? 'translate-x-0' : '-translate-x-full'"
           class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col border-r border-ink-200 bg-white
                  transition-transform duration-200
                  lg:sticky lg:top-0 lg:h-screen lg:translate-x-0 lg:self-start">
        <div class="flex h-16 flex-none items-center gap-2.5 border-b border-ink-200 px-5">
            <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-brand-700 text-white">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 12a9 9 0 1 1-3-6.7"/><polyline points="21 3 21 9 15 9"/>
                </svg>
            </span>
            <div class="leading-tight">
                <div class="text-sm font-bold text-ink-900">Gestión del Cambio</div>
                <div class="text-[11px] text-ink-400">FOSIG-02</div>
            </div>
        </div>

        <nav class="flex-1 space-y-1 overflow-y-auto p-3 text-sm">
            @php($nav = [
                ['route' => 'dashboard', 'pattern' => 'dashboard', 'label' => 'Inicio', 'icon' => 'M3 12l9-9 9 9M5 10v10h14V10'],
                ['route' => 'solicitudes.index', 'pattern' => 'solicitudes.*', 'label' => 'Solicitudes de cambio', 'icon' => 'M9 12h6M9 16h6M9 8h6M5 4h14v16H5z'],
            ])
            @foreach ($nav as $item)
                <a href="{{ route($item['route']) }}"
                   class="flex items-center gap-3 rounded-lg px-3 py-2 font-medium transition-colors
                          {{ request()->routeIs($item['pattern']) ? 'bg-brand-50 text-brand-800' : 'text-ink-600 hover:bg-ink-100 hover:text-ink-900' }}">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $item['icon'] }}"/></svg>
                    {{ $item['label'] }}
                </a>
            @endforeach

            @if (auth()->user()?->hasRole('administrador'))
                <div class="px-3 pb-1 pt-4 text-[11px] font-semibold uppercase tracking-wide text-ink-400">Administración</div>
                <a href="{{ route('admin.usuarios.index') }}"
                   class="flex items-center gap-3 rounded-lg px-3 py-2 font-medium transition-colors
                          {{ request()->routeIs('admin.usuarios.*') ? 'bg-brand-50 text-brand-800' : 'text-ink-600 hover:bg-ink-100 hover:text-ink-900' }}">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    Usuarios y roles
                </a>
                <a href="{{ route('admin.catalogo.preguntas.index') }}"
                   class="flex items-center gap-3 rounded-lg px-3 py-2 font-medium transition-colors
                          {{ request()->routeIs('admin.catalogo.*') ? 'bg-brand-50 text-brand-800' : 'text-ink-600 hover:bg-ink-100 hover:text-ink-900' }}">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                    Catálogo
                </a>
            @endif
        </nav>

        <div class="flex-none border-t border-ink-200 p-3">
            <div class="flex items-center gap-3 rounded-lg px-2 py-2">
                <div class="flex h-9 w-9 flex-none items-center justify-center rounded-full bg-ink-200 text-sm font-semibold text-ink-600">
                    {{ Str::of(auth()->user()->name)->substr(0, 1)->upper() }}
                </div>
                <div class="min-w-0 flex-1 leading-tight">
                    <div class="truncate text-sm font-medium text-ink-800">{{ auth()->user()->name }}</div>
                    <div class="truncate text-[11px] text-ink-400">
                        {{ auth()->user()->roles->pluck('name')->map(fn ($r) => Str::headline($r))->join(', ') ?: 'Sin rol' }}
                    </div>
                </div>
            </div>
            <div class="mt-1 flex gap-1">
                <a href="{{ route('profile.edit') }}" class="btn btn-ghost btn-sm flex-1">Perfil</a>
                <form method="POST" action="{{ route('logout') }}" class="flex-1">
                    @csrf
                    <button class="btn btn-ghost btn-sm w-full text-rose-600 hover:bg-rose-50">Salir</button>
                </form>
            </div>
        </div>
    </aside>

    {{-- Contenido --}}
    <div class="flex min-w-0 flex-1 flex-col">
        <header class="sticky top-0 z-20 border-b border-ink-200 bg-white/80 backdrop-blur">
            <div class="flex items-center gap-4 px-4 py-3 sm:px-6 lg:px-8">
                <button @click="sidebar = !sidebar" class="btn btn-ghost -ml-2 p-2 lg:hidden">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <div class="min-w-0 flex-1">
                    @isset($header)
                        {{ $header }}
                    @else
                        <h1 class="text-lg font-semibold text-ink-900">{{ config('app.name') }}</h1>
                    @endisset
                </div>

                <livewire:notificaciones.campana />

                @php($plantaActiva = session('planta_id') ? \App\Models\Planta::find(session('planta_id')) : null)
                @if ($plantaActiva)
                    <form method="POST" action="{{ route('plantas.activa') }}" class="hidden sm:block">
                        @csrf @method('PATCH')
                        <label class="sr-only">Planta activa</label>
                        <select name="planta_id" onchange="this.form.submit()"
                                class="select select-sm w-40 text-sm" title="Planta / sede activa">
                            @foreach (\App\Models\Planta::activas()->ordenadas()->get() as $p)
                                <option value="{{ $p->id }}" @selected($p->id === $plantaActiva->id)>{{ $p->nombre }}</option>
                            @endforeach
                        </select>
                    </form>
                @endif
            </div>
        </header>

        <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
            <div class="mx-auto min-w-0 max-w-[110rem]">
                {{ $slot }}
            </div>
        </main>
    </div>
</div>

@if (session('status') || session('error') || session('errores_cierre') || session('errores_implementacion') || session('errores_verificacion') || $errors->any())
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            @if (session('status'))
                window.toast('success', @json(session('status')));
            @endif
            @if (session('error'))
                window.toast('error', @json(session('error')));
            @endif
            @if (session('errores_cierre'))
                window.aviso('No se puede cerrar la solicitud',
                    '<ul style="text-align:left;margin:0;padding-left:1.1rem">' +
                    @json(collect(session('errores_cierre'))->map(fn ($e) => '<li>'.e($e).'</li>')->implode('')) +
                    '</ul>');
            @endif
            @if (session('errores_implementacion'))
                window.aviso('No se puede marcar como implementado',
                    '<ul style="text-align:left;margin:0;padding-left:1.1rem">' +
                    @json(collect(session('errores_implementacion'))->map(fn ($e) => '<li>'.e($e).'</li>')->implode('')) +
                    '</ul>');
            @endif
            @if (session('errores_verificacion'))
                window.aviso('No se puede enviar a verificación',
                    '<ul style="text-align:left;margin:0;padding-left:1.1rem">' +
                    @json(collect(session('errores_verificacion'))->map(fn ($e) => '<li>'.e($e).'</li>')->implode('')) +
                    '</ul>');
            @endif
            @if ($errors->any())
                window.aviso('Revise los datos enviados',
                    '<ul style="text-align:left;margin:0;padding-left:1.1rem">' +
                    @json(collect($errors->all())->map(fn ($e) => '<li>'.e($e).'</li>')->implode('')) +
                    '</ul>');
            @endif
        });
    </script>
@endif
</body>
</html>
