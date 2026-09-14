<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-lg font-semibold text-ink-900">Solicitudes de cambio</h1>
                <p class="text-sm text-ink-500">{{ $solicitudes->total() }} en total</p>
            </div>
            @can('create', \App\Models\SolicitudCambio::class)
                <a href="{{ route('solicitudes.create') }}" class="btn btn-primary">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                    Nueva solicitud
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="space-y-4">
        <div class="card overflow-hidden">
            <div class="table-wrap border-0">
                <table class="dtable">
                    <thead>
                        <tr>
                            <th>Consecutivo</th>
                            <th>Nombre del cambio</th>
                            @if ($vistaGlobal ?? false)<th>Planta</th>@endif
                            <th>Clasificación</th>
                            <th>Estado</th>
                            <th>Fecha</th>
                            <th class="text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($solicitudes as $solicitud)
                            <tr class="hover:bg-ink-50">
                                <td class="font-mono text-xs text-ink-500">{{ $solicitud->consecutivo }}</td>
                                <td class="font-medium text-ink-900">{{ $solicitud->nombre_cambio }}</td>
                                @if ($vistaGlobal ?? false)<td class="text-ink-500">{{ $solicitud->planta?->nombre ?? '—' }}</td>@endif
                                <td>
                                    @if ($solicitud->evaluacion?->clasificacion)
                                        <span class="badge badge-brand">{{ $solicitud->evaluacion->clasificacion->value }}</span>
                                    @else
                                        <span class="text-ink-300">—</span>
                                    @endif
                                </td>
                                <td><span class="badge {{ $solicitud->estado->badgeClass() }}">{{ $solicitud->estado->etiqueta() }}</span></td>
                                <td class="text-ink-500">{{ $solicitud->fecha?->format('d/m/Y') }}</td>
                                <td class="text-right whitespace-nowrap">
                                    <a class="font-medium text-brand-700 hover:text-brand-800" href="{{ route('solicitudes.show', $solicitud) }}">Ver</a>
                                    @if ($solicitud->estado->esEditable())
                                        <span class="text-ink-300">·</span>
                                        <a class="font-medium text-brand-700 hover:text-brand-800" href="{{ route('solicitudes.edit', $solicitud) }}">Editar</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-12 text-center">
                                    <p class="text-sm font-medium text-ink-500">No hay solicitudes registradas</p>
                                    @can('create', \App\Models\SolicitudCambio::class)
                                        <a href="{{ route('solicitudes.create') }}" class="btn btn-secondary btn-sm mt-3">Crear la primera</a>
                                    @endcan
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div>{{ $solicitudes->links() }}</div>
    </div>
</x-app-layout>
