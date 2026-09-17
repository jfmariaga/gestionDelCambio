<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <div>
                <div class="flex items-center gap-2">
                    <span class="font-mono text-xs text-ink-400">{{ $solicitud->consecutivo }}</span>
                    <span class="badge {{ $solicitud->estado->badgeClass() }}">{{ $solicitud->estado->etiqueta() }}</span>
                </div>
                <h1 class="text-lg font-semibold text-ink-900">{{ $solicitud->nombre_cambio }}</h1>
                <p class="text-xs text-ink-400">Tarea del plan de acción asignada a usted.</p>
            </div>
        </div>
    </x-slot>

    <div class="mx-auto max-w-2xl space-y-4">
        <livewire:solicitud.plan-accion :solicitud="$solicitud" :solo-accion-id="$accion->id" />

        @if (auth()->user()->hasAnyRole(['administrador', 'solicitante', 'aprobador', 'dueno_proceso']))
            <p class="text-center text-xs text-ink-400">
                <a href="{{ route('solicitudes.edit', $solicitud) }}#sec-5" class="text-brand-700 hover:underline">Ver la solicitud completa</a>
            </p>
        @endif
    </div>
</x-app-layout>
