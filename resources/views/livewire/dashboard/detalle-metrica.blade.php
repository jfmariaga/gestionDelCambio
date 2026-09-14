@php
    use App\Domain\GestionCambio\MetricasDashboard;
@endphp

<div>
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
        @foreach (MetricasDashboard::ETIQUETAS as $clave => $etiqueta)
            <button type="button" wire:click="abrir('{{ $clave }}')"
                    class="card card-pad w-full text-left transition hover:ring-2 hover:ring-brand-300 focus:outline-none focus:ring-2 focus:ring-brand-400">
                <div class="text-xs font-medium uppercase tracking-wide text-ink-400">{{ $etiqueta }}</div>
                <div class="mt-2 text-3xl font-bold {{ MetricasDashboard::ACENTOS[$clave] }}">{{ $metricas[$clave] ?? 0 }}</div>
            </button>
        @endforeach
    </div>

    @if ($metricaActual)
        <div x-data @keydown.escape.window="$wire.cerrar()"
             class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true">
            <div class="absolute inset-0 bg-ink-900/40" wire:click="cerrar"></div>

            <div class="relative flex max-h-[85vh] w-full max-w-4xl flex-col overflow-hidden rounded-xl bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-ink-200 px-5 py-4">
                    <div>
                        <h2 class="text-sm font-semibold text-ink-900">{{ $this->etiqueta($metricaActual) }}</h2>
                        <p class="text-xs text-ink-400">
                            {{ $this->items->count() }} {{ Str::plural('resultado', $this->items->count()) }}
                        </p>
                    </div>
                    <button type="button" wire:click="cerrar" class="btn btn-ghost p-2" aria-label="Cerrar">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="overflow-y-auto">
                    <table class="dtable">
                        <thead>
                            @if ($this->esDeSolicitudes)
                                <tr>
                                    <th>Consecutivo</th>
                                    <th>Nombre del cambio</th>
                                    <th>Líder del cambio</th>
                                    <th>Estado</th>
                                    <th>Fecha</th>
                                </tr>
                            @else
                                <tr>
                                    <th>Acción</th>
                                    <th>Plan / Solicitud</th>
                                    <th>Responsable</th>
                                    <th>Estado</th>
                                    <th>Fecha</th>
                                </tr>
                            @endif
                        </thead>
                        <tbody>
                            @forelse ($this->items as $item)
                                @if ($this->esDeSolicitudes)
                                    <tr class="hover:bg-ink-50">
                                        <td class="font-mono text-xs text-ink-500">{{ $item->consecutivo }}</td>
                                        <td class="font-medium text-ink-900">
                                            <a href="{{ route('solicitudes.show', $item) }}" class="hover:text-brand-700">{{ $item->nombre_cambio }}</a>
                                        </td>
                                        <td class="text-ink-600">{{ $item->autor?->name ?? '—' }}</td>
                                        <td><span class="badge {{ $item->estado->badgeClass() }}">{{ $item->estado->etiqueta() }}</span></td>
                                        <td class="text-ink-500">{{ $item->fecha?->format('d/m/Y') }}</td>
                                    </tr>
                                @else
                                    <tr class="hover:bg-ink-50">
                                        <td class="font-medium text-ink-900">{{ $item->descripcion }}</td>
                                        <td class="text-ink-600">
                                            @if ($item->solicitud)
                                                <a href="{{ route('solicitudes.show', $item->solicitud) }}" class="hover:text-brand-700">
                                                    <span class="font-mono text-xs text-ink-400">{{ $item->solicitud->consecutivo }}</span>
                                                    {{ $item->solicitud->nombre_cambio }}
                                                </a>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="text-ink-600">{{ $item->responsableUsuario?->name ?? ($item->responsable ?: 'Sin asignar') }}</td>
                                        <td><span class="badge {{ $item->estado->badgeClass() }}">{{ $item->estado->etiqueta() }}</span></td>
                                        <td class="text-ink-500">{{ $item->fecha?->format('d/m/Y') }}</td>
                                    </tr>
                                @endif
                            @empty
                                <tr>
                                    <td colspan="5" class="py-10 text-center text-sm text-ink-400">No hay resultados para esta métrica con los filtros actuales.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</div>
