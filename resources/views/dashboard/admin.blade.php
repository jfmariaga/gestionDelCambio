<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-lg font-semibold text-ink-900">Hola, {{ Str::of(auth()->user()->name)->before(' ') }}</h1>
                <p class="text-sm text-ink-500">Panel de métricas · Gestión del cambio</p>
            </div>
            @can('create', \App\Models\SolicitudCambio::class)
                <a href="{{ route('solicitudes.create') }}" class="btn btn-primary">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                    Nueva solicitud
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="space-y-6">
        {{-- Filtros --}}
        <form method="GET" action="{{ route('dashboard') }}" class="card card-pad">
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-8">
                <div>
                    <label class="field-label">Responsable</label>
                    <select name="responsable_id" class="select">
                        <option value="">Todos</option>
                        @foreach ($usuarios as $u)
                            <option value="{{ $u->id }}" @selected(($filtros['responsable_id'] ?? null) == $u->id)>{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="field-label">Líder del cambio</label>
                    <select name="lider_id" class="select">
                        <option value="">Todos</option>
                        @foreach ($usuarios as $u)
                            <option value="{{ $u->id }}" @selected(($filtros['lider_id'] ?? null) == $u->id)>{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="field-label">Proceso</label>
                    <select name="proceso" class="select">
                        <option value="">Todos</option>
                        @foreach ($procesos as $p)
                            <option value="{{ $p }}" @selected(($filtros['proceso'] ?? null) === $p)>{{ $p }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="field-label">Tipo de cambio</label>
                    <select name="tipo_cambio" class="select">
                        <option value="">Todos</option>
                        @foreach ($tipos_cambio as $valor => $etiqueta)
                            <option value="{{ $valor }}" @selected(($filtros['tipo_cambio'] ?? null) === $valor)>{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="field-label">Prioridad</label>
                    <select name="clasificacion" class="select">
                        <option value="">Todas</option>
                        @foreach ($clasificaciones as $c)
                            <option value="{{ $c->value }}" @selected(($filtros['clasificacion'] ?? null) === $c->value)>{{ $c->value }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="field-label">Estado</label>
                    <select name="estado" class="select">
                        <option value="">Todos</option>
                        @foreach ($estados as $e)
                            <option value="{{ $e->value }}" @selected(($filtros['estado'] ?? null) === $e->value)>{{ $e->etiqueta() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="field-label">Fecha desde</label>
                    <input type="date" name="fecha_desde" class="input" value="{{ $filtros['fecha_desde'] ?? '' }}">
                </div>
                <div>
                    <label class="field-label">Fecha hasta</label>
                    <input type="date" name="fecha_hasta" class="input" value="{{ $filtros['fecha_hasta'] ?? '' }}">
                </div>
            </div>
            <div class="mt-4 flex items-center gap-3">
                <button class="btn btn-primary btn-sm">Filtrar</button>
                @if (array_filter($filtros))
                    <a href="{{ route('dashboard') }}" class="text-sm font-medium text-ink-500 hover:text-ink-700">Limpiar filtros</a>
                @endif
            </div>
        </form>

        {{-- Métricas: cada tarjeta abre el detalle de esos registros al hacer clic --}}
        <livewire:dashboard.detalle-metrica :metricas="$metricas" :filtros="$filtros" />

        <div class="grid gap-6 lg:grid-cols-2">
            {{-- Tabla por responsable --}}
            <div class="card overflow-hidden">
                <div class="border-b border-ink-200 px-5 py-4 sm:px-6">
                    <h2 class="text-sm font-semibold text-ink-900">Cumplimiento por responsable</h2>
                </div>
                <div class="table-wrap border-0">
                    <table class="dtable">
                        <thead>
                            <tr>
                                <th>Responsable</th>
                                <th class="text-right">Total</th>
                                <th class="text-right">Completadas</th>
                                <th class="text-right">En proceso</th>
                                <th class="text-right">Pendientes</th>
                                <th class="text-right">Vencidas</th>
                                <th class="text-right">% cumplimiento</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($tablaResponsables as $fila)
                                <tr class="hover:bg-ink-50">
                                    <td class="font-medium text-ink-900">{{ $fila['nombre'] }}</td>
                                    <td class="text-right">{{ $fila['total'] }}</td>
                                    <td class="text-right">{{ $fila['completadas'] }}</td>
                                    <td class="text-right">{{ $fila['en_proceso'] }}</td>
                                    <td class="text-right">{{ $fila['pendientes'] }}</td>
                                    <td class="text-right {{ $fila['vencidas'] > 0 ? 'text-rose-600 font-semibold' : '' }}">{{ $fila['vencidas'] }}</td>
                                    <td class="text-right">{{ $fila['cumplimiento'] }}%</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="py-8 text-center text-sm text-ink-400">No hay acciones registradas con estos filtros.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Evolución mensual --}}
            <div class="card card-pad">
                <h2 class="mb-4 text-sm font-semibold text-ink-900">Evolución mensual: programadas vs. cerradas</h2>
                <canvas id="grafico-evolucion" height="220"></canvas>
            </div>
        </div>
    </div>

    <script>
        const datosEvolucion = @json($evolucionMensual);

            function iniciarGraficoEvolucion() {
                const el = document.getElementById('grafico-evolucion');
                if (!el || !window.Chart) return;

                new Chart(el, {
                    type: 'bar',
                    data: {
                        labels: datosEvolucion.map((m) => m.mes),
                        datasets: [
                            { label: 'Programadas', data: datosEvolucion.map((m) => m.programadas), backgroundColor: '#0ea5e9' },
                            { label: 'Cerradas', data: datosEvolucion.map((m) => m.cerradas), backgroundColor: '#0f766e' },
                        ],
                    },
                    options: {
                        responsive: true,
                        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
                        plugins: { legend: { position: 'bottom' } },
                    },
                });
            }

        document.addEventListener('DOMContentLoaded', iniciarGraficoEvolucion);
    </script>
</x-app-layout>
