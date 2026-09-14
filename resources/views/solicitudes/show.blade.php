<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <a href="{{ route('solicitudes.index') }}" class="btn btn-ghost -ml-2 p-2">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M15 18l-6-6 6-6"/></svg>
                </a>
                <div>
                    <div class="flex items-center gap-2">
                        <span class="font-mono text-xs text-ink-400">{{ $solicitud->consecutivo }}</span>
                        <span class="badge {{ $solicitud->estado->badgeClass() }}">{{ $solicitud->estado->etiqueta() }}</span>
                        @if ($solicitud->evaluacion?->clasificacion)
                            <span class="badge badge-brand">{{ $solicitud->evaluacion->clasificacion->value }}</span>
                        @endif
                    </div>
                    <h1 class="text-lg font-semibold text-ink-900">{{ $solicitud->nombre_cambio }}</h1>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('solicitudes.exportar', $solicitud) }}" class="btn btn-secondary">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 3v12m0 0 4-4m-4 4-4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>
                    Exportar PDF
                </a>
                @if ($solicitud->estado->esEditable())
                    <a href="{{ route('solicitudes.edit', $solicitud) }}" class="btn btn-primary">Editar</a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="mx-auto max-w-5xl space-y-5">
        {{-- Decision actions --}}
        @if (in_array($solicitud->estado, [\App\Enums\EstadoSolicitud::EnEvaluacion, \App\Enums\EstadoSolicitud::EnVerificacion], true))
            <div class="card card-pad">
                <h2 class="mb-3 text-sm font-semibold text-ink-900">
                    {{ $solicitud->estado === \App\Enums\EstadoSolicitud::EnEvaluacion ? 'Decisión — aprobación inicial' : 'Decisión — seguimiento y cierre' }}
                </h2>
                <form method="POST" action="{{ route('solicitudes.decision', $solicitud) }}" class="flex flex-wrap items-end gap-2"
                      data-confirm="Se registrará la decisión sobre esta solicitud." data-confirm-title="Registrar decisión">
                    @csrf
                    <div class="flex-1 min-w-[16rem]">
                        <label class="field-label">Comentario</label>
                        <input type="text" name="comentario" class="input" placeholder="Opcional al aprobar, obligatorio al devolver">
                    </div>
                    <button name="accion" value="aprobar" class="btn btn-success">Aprobar</button>
                    <button name="accion" value="devolver" class="btn btn-warning">Devolver</button>
                </form>
            </div>
        @endif
        @can('gestionarImplementacion', $solicitud)
            @if ($solicitud->estado === \App\Enums\EstadoSolicitud::Aprobado)
                <form method="POST" action="{{ route('solicitudes.implementar', $solicitud) }}"
                      data-confirm="La solicitud pasará a «En implementación»." data-confirm-title="Iniciar implementación">
                    @csrf
                    <button class="btn btn-primary">Iniciar implementación</button>
                </form>
            @elseif ($solicitud->estado === \App\Enums\EstadoSolicitud::Implementado)
                <form method="POST" action="{{ route('solicitudes.enviarVerificacion', $solicitud) }}"
                      data-confirm="Se enviará a los aprobadores de seguimiento y cierre." data-confirm-title="Enviar a seguimiento y cierre">
                    @csrf
                    <button class="btn btn-success">Enviar a seguimiento y cierre</button>
                </form>
            @endif
        @endcan

        @if (session('errores_cierre'))
            <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                <p class="font-semibold">No se puede cerrar todavía:</p>
                <ul class="list-disc pl-5">
                    @foreach (session('errores_cierre') as $b)<li>{{ $b }}</li>@endforeach
                </ul>
            </div>
        @endif

        {{-- Override de aprobador (solo administrador) --}}
        @if (auth()->user()?->hasRole('administrador') && $solicitud->estado->esEditable())
            <div class="card card-pad">
                <h2 class="mb-3 text-sm font-semibold text-ink-900">Aprobador asignado
                    @if ($solicitud->aprobador_override)<span class="badge badge-amber">Manual</span>@else<span class="badge badge-gray">Automático · {{ $solicitud->clasificacionVigente()?->value ?? 'sin clasificar' }}</span>@endif
                </h2>
                <form method="POST" action="{{ route('solicitudes.aprobador', $solicitud) }}" class="flex flex-wrap items-end gap-2">
                    @csrf @method('PATCH')
                    <div class="flex-1 min-w-[16rem]">
                        <label class="field-label">Sustituir aprobador</label>
                        <select name="aprobador_asignado_id" class="select">
                            @foreach (\App\Models\User::orderBy('name')->get(['id', 'name']) as $u)
                                <option value="{{ $u->id }}" @selected($solicitud->aprobador_asignado_id === $u->id)>{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button class="btn btn-secondary">Guardar aprobador</button>
                </form>
            </div>
        @endif

        {{-- Section 1 --}}
        <section class="card card-pad">
            <div class="mb-4 flex items-center gap-3"><span class="section-num">1</span><h2 class="text-sm font-semibold text-ink-900">Resumen del cambio</h2></div>
            <dl class="grid grid-cols-1 gap-x-6 gap-y-3 text-sm sm:grid-cols-3">
                @foreach ([
                    'Consecutivo' => $solicitud->consecutivo,
                    'Responsable / líder del cambio' => $solicitud->responsable?->name,
                    'Aprobador asignado' => $solicitud->aprobadores->pluck('name')->join(', ')
                        ?: $solicitud->aprobadorAsignado?->name,
                    'Fecha' => $solicitud->fecha?->format('d/m/Y'),
                    'Solicitante / cargo' => $solicitud->solicitante_cargo,
                    'Área / Proceso' => $solicitud->area_proceso,
                    'Tipo de cambio' => $solicitud->tipo_cambio,
                    'Fecha requerida' => optional($solicitud->fecha_requerida)->format('d/m/Y'),
                    'Costo estimado' => $solicitud->costo_estimado ? \Illuminate\Support\Number::currency(round((float) $solicitud->costo_estimado), in: 'COP', locale: 'es_CO') : null,
                    '¿Requiere comité?' => $solicitud->requiere_comite ? 'Sí' : 'No',
                ] as $k => $v)
                    <div>
                        <dt class="field-label">{{ $k }}</dt>
                        <dd class="text-ink-800">{{ $v ?: '—' }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>

        {{-- Section 2 --}}
        <section class="card card-pad">
            <div class="mb-4 flex items-center gap-3"><span class="section-num">2</span><h2 class="text-sm font-semibold text-ink-900">Descripción simple</h2></div>
            <dl class="space-y-3 text-sm">
                @foreach (['Situación actual' => $solicitud->situacion_actual, 'Qué cambiará' => $solicitud->que_cambiara, 'Resultado esperado' => $solicitud->resultado_esperado] as $k => $v)
                    <div><dt class="field-label">{{ $k }}</dt><dd class="text-ink-800">{{ $v ?: '—' }}</dd></div>
                @endforeach
            </dl>
        </section>

        {{-- Section 3 --}}
        <section class="card card-pad">
            <div class="mb-4 flex items-center gap-3"><span class="section-num">3</span><h2 class="text-sm font-semibold text-ink-900">Riesgos asociados</h2></div>
            <div class="table-wrap">
                <table class="dtable">
                    <thead><tr><th>Proceso</th><th>Riesgo</th><th class="text-center">P</th><th class="text-center">I</th><th class="text-center">NR</th><th>Nivel</th></tr></thead>
                    <tbody>
                        @forelse ($solicitud->riesgosAsociados()->orderBy('proceso_nombre')->get() as $r)
                            <tr>
                                <td>{{ $r->proceso_nombre }}</td>
                                <td>{{ $r->riesgo_texto }}</td>
                                <td class="text-center tabular-nums">{{ $r->probabilidad ?? '—' }}</td>
                                <td class="text-center tabular-nums">{{ $r->impacto ?? '—' }}</td>
                                <td class="text-center font-semibold tabular-nums">{{ $r->nr ?? '—' }}</td>
                                <td>
                                    @if ($r->nivel)
                                        <span class="badge {{ match ($r->nivel->value) { 'Bajo' => 'badge-green', 'Medio' => 'badge-amber', default => 'badge-red' } }}">{{ $r->nivel->value }}</span>
                                    @else — @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-4 text-center text-ink-400">Sin riesgos asociados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        @if ($solicitud->aprobado_at)
            <p class="text-xs text-ink-400">Aprobada el {{ $solicitud->aprobado_at->format('d/m/Y H:i') }}. {{ $solicitud->decision_comentario }}</p>
        @endif
    </div>
</x-app-layout>
