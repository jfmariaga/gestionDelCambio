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
                @if ($solicitud->estado->admiteGestionDePlanYCierre())
                    <a href="{{ route('solicitudes.edit', $solicitud) }}" class="btn btn-primary">Editar</a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="mx-auto max-w-5xl space-y-5">
        {{-- Decision actions --}}
        @php($puedeDecidirInicial = $solicitud->estado === \App\Enums\EstadoSolicitud::EnEvaluacion && auth()->user()->can('decidir', $solicitud))
        @php($puedeDecidirCierre = $solicitud->estado === \App\Enums\EstadoSolicitud::EnVerificacion && auth()->user()->can('decidirCierre', $solicitud))
        @if ($puedeDecidirInicial || $puedeDecidirCierre)
            <div class="card card-pad">
                <h2 class="mb-3 text-sm font-semibold text-ink-900">
                    {{ $puedeDecidirInicial ? 'Decisión — aprobación inicial' : 'Decisión — seguimiento y cierre' }}
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
        @elseif (in_array($solicitud->estado, [\App\Enums\EstadoSolicitud::EnEvaluacion, \App\Enums\EstadoSolicitud::EnVerificacion], true))
            <div class="card card-pad">
                <h2 class="mb-1 text-sm font-semibold text-ink-900">
                    {{ $solicitud->estado === \App\Enums\EstadoSolicitud::EnEvaluacion ? 'Decisión — aprobación inicial' : 'Decisión — seguimiento y cierre' }}
                </h2>
                <p class="text-sm text-ink-400">Esperando la decisión de los aprobadores asignados a esta etapa.</p>
            </div>
        @endif

        {{-- Quién falta por aprobar (solo administrador y el líder de la solicitud) --}}
        @php($esLiderOAdmin = auth()->id() === $solicitud->created_by || auth()->user()->hasRole('administrador'))
        @php($etapaActual = match (true) {
            $solicitud->estado === \App\Enums\EstadoSolicitud::EnEvaluacion => \App\Models\AprobacionSolicitud::ETAPA_INICIAL,
            $solicitud->estado === \App\Enums\EstadoSolicitud::EnVerificacion => \App\Models\AprobacionSolicitud::ETAPA_CIERRE,
            default => null,
        })
        @if ($esLiderOAdmin && $etapaActual)
            @php($aprobaciones = $solicitud->aprobaciones()->where('etapa', $etapaActual)->with('usuario:id,name,email')->get())
            @php($pendientes = $aprobaciones->where('decision', \App\Models\AprobacionSolicitud::PENDIENTE))
            <div class="card card-pad">
                <h2 class="mb-3 text-sm font-semibold text-ink-900">
                    Aprobadores — {{ $etapaActual === \App\Models\AprobacionSolicitud::ETAPA_INICIAL ? 'aprobación inicial' : 'seguimiento y cierre' }}
                    <span class="ml-1 font-normal text-ink-400">({{ $aprobaciones->count() - $pendientes->count() }}/{{ $aprobaciones->count() }} decidieron)</span>
                </h2>
                @if ($pendientes->isEmpty())
                    <p class="text-sm text-ink-400">@if ($aprobaciones->isEmpty()) No hay aprobadores resueltos para esta etapa. @else Todos los aprobadores de esta etapa ya decidieron. @endif</p>
                @else
                    <ul class="space-y-1.5 text-sm">
                        @foreach ($pendientes as $p)
                            <li class="flex items-center gap-2">
                                <span class="badge badge-amber">Pendiente</span>
                                <span class="text-ink-800">{{ $p->usuario?->name }}</span>
                                <span class="text-ink-400">{{ $p->usuario?->email }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif
        @can('gestionarImplementacion', $solicitud)
            @if ($solicitud->estado === \App\Enums\EstadoSolicitud::Aprobado)
                <form method="POST" action="{{ route('solicitudes.implementar', $solicitud) }}"
                      data-confirm="La solicitud pasará a «En implementación»." data-confirm-title="Iniciar implementación">
                    @csrf
                    <button class="btn btn-primary">Iniciar implementación</button>
                </form>
            @elseif ($solicitud->estado === \App\Enums\EstadoSolicitud::EnImplementacion)
                <form method="POST" action="{{ route('solicitudes.marcarImplementado', $solicitud) }}"
                      data-confirm="Se marcará la implementación como completa y se habilitará seguimiento y cierre." data-confirm-title="Marcar como implementado">
                    @csrf
                    <button class="btn btn-primary">Marcar como implementado</button>
                </form>
            @elseif ($solicitud->estado === \App\Enums\EstadoSolicitud::Implementado)
                {{-- El botón "Enviar a cierre" ahora vive en la sección 6 (Seguimiento y cierre) de la página de edición, junto a la nota de cierre. --}}
                <p class="text-sm text-ink-400">
                    Listo para enviar a verificación desde
                    <a href="{{ route('solicitudes.edit', $solicitud) }}#sec-6" class="font-medium text-brand-700 hover:underline">Seguimiento y cierre</a>.
                </p>
            @endif
        @endcan

        @if (session('errores_implementacion'))
            <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                <p class="font-semibold">No se puede marcar como implementado todavía:</p>
                <ul class="list-disc pl-5">
                    @foreach (session('errores_implementacion') as $b)<li>{{ $b }}</li>@endforeach
                </ul>
            </div>
        @endif

        @if (session('errores_verificacion'))
            <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                <p class="font-semibold">No se puede enviar a verificación todavía:</p>
                <ul class="list-disc pl-5">
                    @foreach (session('errores_verificacion') as $b)<li>{{ $b }}</li>@endforeach
                </ul>
            </div>
        @endif

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

        {{-- Section 1: Evaluación y clasificación --}}
        <section class="card card-pad">
            <div class="mb-4 flex items-center gap-3"><span class="section-num">1</span><h2 class="text-sm font-semibold text-ink-900">Evaluación y clasificación del cambio</h2></div>
            @if ($solicitud->evaluacion)
                <div class="mb-3 flex flex-wrap items-center gap-x-6 gap-y-2 rounded-lg bg-ink-50 p-4">
                    <div>
                        <div class="text-xs font-medium uppercase tracking-wide text-ink-400">Suma</div>
                        <div class="text-2xl font-bold tabular-nums text-ink-900">{{ $solicitud->evaluacion->suma }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-medium uppercase tracking-wide text-ink-400">Clasificación</div>
                        <span class="badge {{ match ($solicitud->evaluacion->clasificacion?->value) { 'Menor' => 'badge-green', 'Mayor' => 'badge-amber', 'Crítico' => 'badge-red', default => 'badge-gray' } }} mt-1 text-sm">
                            {{ $solicitud->evaluacion->clasificacion?->value ?? '—' }}
                        </span>
                    </div>
                </div>
                <div class="table-wrap">
                    <table class="dtable">
                        <thead><tr><th>Criterio</th><th class="text-center">Nivel</th></tr></thead>
                        <tbody>
                            @foreach ($solicitud->evaluacion->calificaciones()->with('criterio')->get()->sortBy('criterio.orden') as $c)
                                <tr><td>{{ $c->criterio?->nombre }}</td><td class="text-center font-semibold tabular-nums">{{ $c->valor }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-sm text-ink-400">Sin evaluar.</p>
            @endif
        </section>

        {{-- Section 2: Resumen del cambio --}}
        <section class="card card-pad">
            <div class="mb-4 flex items-center gap-3"><span class="section-num">2</span><h2 class="text-sm font-semibold text-ink-900">Resumen del cambio</h2></div>
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

        {{-- Section 3: Descripción simple --}}
        <section class="card card-pad">
            <div class="mb-4 flex items-center gap-3"><span class="section-num">3</span><h2 class="text-sm font-semibold text-ink-900">Descripción simple</h2></div>
            <dl class="space-y-3 text-sm">
                @foreach (['Situación actual' => $solicitud->situacion_actual, 'Qué cambiará' => $solicitud->que_cambiara, 'Resultado esperado' => $solicitud->resultado_esperado] as $k => $v)
                    <div><dt class="field-label">{{ $k }}</dt><dd class="text-ink-800">{{ $v ?: '—' }}</dd></div>
                @endforeach
            </dl>
        </section>

        {{-- Section 4: Cuestionario --}}
        <section class="card card-pad">
            <div class="mb-4 flex items-center gap-3"><span class="section-num">4</span><h2 class="text-sm font-semibold text-ink-900">Cuestionario de riesgos</h2></div>
            <div class="table-wrap">
                <table class="dtable">
                    <thead><tr><th>Proceso</th><th>Pregunta</th><th class="text-center">Respuesta</th></tr></thead>
                    <tbody>
                        @forelse ($solicitud->respuestas()->with('pregunta.proceso')->get()->sortBy(['pregunta.proceso.orden', 'pregunta.orden']) as $r)
                            <tr>
                                <td>{{ $r->pregunta?->proceso?->nombre }}</td>
                                <td>{{ $r->pregunta?->texto }}</td>
                                <td class="text-center">
                                    <span class="badge {{ match ($r->valor?->value) { 'SI' => 'badge-green', 'NO' => 'badge-gray', default => 'badge-amber' } }}">{{ $r->valor?->value }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="py-4 text-center text-ink-400">No aplica o sin responder.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        {{-- Section 5: Riesgos asociados --}}
        <section class="card card-pad">
            <div class="mb-4 flex items-center gap-3"><span class="section-num">5</span><h2 class="text-sm font-semibold text-ink-900">Riesgos asociados</h2></div>
            <div class="table-wrap">
                <table class="dtable">
                    <thead><tr><th>Proceso</th><th>Riesgo</th><th>Control existente</th><th>Validación requerida</th><th>Responsable</th><th class="text-center">P</th><th class="text-center">I</th><th class="text-center">NR</th><th>Nivel</th></tr></thead>
                    <tbody>
                        @forelse ($solicitud->riesgosAsociados()->with('responsableUsuario:id,name')->orderBy('proceso_nombre')->get() as $r)
                            <tr>
                                <td>{{ $r->proceso_nombre }}</td>
                                <td>{{ $r->riesgo_texto }}</td>
                                <td>{{ $r->control_existente ?: '—' }}</td>
                                <td>{{ $r->accion_requerida ?: '—' }}</td>
                                <td>{{ $r->responsableUsuario?->name ?: ($r->responsable ?: '—') }}</td>
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
                            <tr><td colspan="9" class="py-4 text-center text-ink-400">Sin riesgos asociados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        {{-- Section 6: Plan de acción --}}
        <section class="card card-pad">
            <div class="mb-4 flex items-center gap-3"><span class="section-num">6</span><h2 class="text-sm font-semibold text-ink-900">Plan de acción y seguimiento</h2></div>
            <div class="space-y-2">
                @forelse ($solicitud->accionesPlan()->with(['responsableUsuario:id,name', 'riesgoAsociado', 'adjuntos.subidoPor:id,name'])->orderBy('numero')->get() as $a)
                    <div class="rounded-lg border border-ink-200 p-3 text-sm">
                        <div class="mb-1.5 flex flex-wrap items-center gap-2">
                            <span class="flex h-6 w-6 flex-none items-center justify-center rounded-md bg-ink-100 text-xs font-bold text-ink-500">{{ $a->numero }}</span>
                            <span class="badge {{ $a->estado->badgeClass() }}">{{ $a->estado->etiqueta() }}</span>
                            @if ($a->riesgoAsociado)
                                <span class="badge {{ $a->riesgoAsociado->nivel?->colorBadge() ?? 'badge-gray' }}">Riesgo {{ $a->riesgoAsociado->nivel?->value }}</span>
                            @endif
                            @if ($a->responsableUsuario)<span class="text-xs text-ink-400">Responsable: {{ $a->responsableUsuario->name }}</span>@endif
                            @if ($a->fecha)<span class="text-xs text-ink-400">Fecha: {{ $a->fecha->format('d/m/Y') }}</span>@endif
                        </div>
                        <p class="text-ink-800">{{ $a->descripcion }}</p>
                        @if ($a->evidencia)<p class="mt-1 text-xs text-ink-500"><span class="field-label">Evidencia:</span> {{ $a->evidencia }}</p>@endif
                        @if ($a->adjuntos->isNotEmpty())
                            <div class="mt-1.5 flex flex-wrap gap-x-3 gap-y-0.5 text-xs">
                                @foreach ($a->adjuntos as $adj)
                                    <a href="{{ route('solicitudes.adjuntos.download', [$solicitud, $adj]) }}" class="font-medium text-brand-700 hover:text-brand-800">{{ $adj->nombre_original }}</a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-ink-400">Sin acciones registradas.</p>
                @endforelse
            </div>
        </section>

        {{-- Section 7: Seguimiento y cierre --}}
        <section class="card card-pad">
            <div class="mb-4 flex items-center gap-3"><span class="section-num">7</span><h2 class="text-sm font-semibold text-ink-900">Seguimiento y cierre</h2></div>
            @if ($solicitud->nota_cierre)
                <p class="whitespace-pre-line text-sm text-ink-800">{{ $solicitud->nota_cierre }}</p>
            @else
                <p class="text-sm text-ink-400">Sin nota de cierre.</p>
            @endif
        </section>

        {{-- Section 8: Historial --}}
        <section class="card card-pad">
            <div class="mb-4 flex items-center gap-3"><span class="section-num">8</span><h2 class="text-sm font-semibold text-ink-900">Historial</h2></div>
            <ol class="max-h-96 space-y-0 overflow-y-auto pr-1">
                @foreach ($solicitud->eventos()->with('usuario:id,name')->get() as $ev)
                    <li class="flex gap-3 border-b border-ink-100 py-3 first:pt-0 last:border-0 last:pb-0">
                        <span class="mt-1.5 h-2 w-2 flex-none rounded-full {{ $ev->dotClass() }}"></span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
                                <div class="flex min-w-0 flex-wrap items-center gap-2">
                                    <span class="badge {{ $ev->badgeClass() }}">{{ $ev->etiqueta() }}</span>
                                    <span class="truncate text-sm font-medium text-ink-800">{{ $ev->usuario?->name ?? 'Sistema' }}</span>
                                </div>
                                <span class="flex-none text-xs text-ink-400">{{ $ev->created_at?->format('d/m/Y H:i') }}</span>
                            </div>
                            @if ($ev->comentario)
                                <p class="mt-1.5 rounded-md bg-ink-50 px-2.5 py-1.5 text-xs text-ink-600">{{ $ev->comentario }}</p>
                            @endif
                        </div>
                    </li>
                @endforeach
                <li class="flex gap-3 pt-3 first:pt-0">
                    <span class="mt-1.5 h-2 w-2 flex-none rounded-full bg-ink-300"></span>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
                            <div class="flex min-w-0 flex-wrap items-center gap-2">
                                <span class="badge badge-gray">Creada</span>
                                <span class="truncate text-sm font-medium text-ink-800">{{ $solicitud->responsable?->name ?? '—' }}</span>
                            </div>
                            <span class="flex-none text-xs text-ink-400">{{ $solicitud->created_at?->format('d/m/Y H:i') }}</span>
                        </div>
                    </div>
                </li>
            </ol>
        </section>

        @if ($solicitud->aprobado_at)
            <p class="text-xs text-ink-400">Aprobada el {{ $solicitud->aprobado_at->format('d/m/Y H:i') }}. {{ $solicitud->decision_comentario }}</p>
        @endif
    </div>
</x-app-layout>
