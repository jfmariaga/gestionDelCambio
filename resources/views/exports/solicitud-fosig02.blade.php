<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1e293b; }
        body { margin: 0; }
        h1 { font-size: 16px; margin: 0; color: #0f172a; }
        h2 {
            font-size: 11px; text-transform: uppercase; letter-spacing: .3px;
            background: #0f766e; color: #fff; padding: 5px 8px; margin: 16px 0 6px; border-radius: 3px;
        }
        h3 { font-size: 10px; margin: 8px 0 3px; color: #0f766e; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
        th, td { border: 1px solid #cbd5e1; padding: 4px 6px; text-align: left; vertical-align: top; }
        thead th { background: #f1f5f9; }
        .grid th { background: #f8fafc; width: 16%; font-weight: bold; }
        .head { width: 100%; border: 0; margin-bottom: 4px; }
        .head td { border: 0; padding: 0; vertical-align: middle; }
        .head-bar { border-bottom: 2px solid #0f766e; padding-bottom: 8px; margin-bottom: 4px; }
        .muted { color: #64748b; }
        .right { text-align: right; }
        .center { text-align: center; }
        .badge {
            display: inline-block; padding: 2px 7px; border-radius: 8px; font-weight: bold; font-size: 9px;
        }
        .img-row { margin-top: 4px; }
        .img-row img {
            width: 64px; height: auto; margin: 0 4px 4px 0; border: 1px solid #cbd5e1; border-radius: 2px;
        }
        .footer-note { margin-top: 14px; font-size: 8px; color: #94a3b8; }
    </style>
</head>
<body>
    @php
        $coloresEstado = [
            'solicitado' => ['#f1f5f9', '#475569'],
            'en_evaluacion' => ['#fef3c7', '#92400e'],
            'aprobado' => ['#dbeafe', '#1e40af'],
            'en_implementacion' => ['#ede9fe', '#5b21b6'],
            'implementado' => ['#ccfbf1', '#0f766e'],
            'en_verificacion' => ['#fef3c7', '#92400e'],
            'cerrado' => ['#dcfce7', '#166534'],
            'cancelado' => ['#fee2e2', '#991b1b'],
        ];
        [$estadoBg, $estadoFg] = $coloresEstado[$solicitud->estado->value] ?? ['#f1f5f9', '#475569'];

        $coloresClasificacion = [
            'Menor' => ['#dcfce7', '#166534'],
            'Mayor' => ['#fef3c7', '#92400e'],
            'Crítico' => ['#fee2e2', '#991b1b'],
            'Revisar' => ['#f1f5f9', '#475569'],
        ];
        $clasificacionVigente = $solicitud->evaluacion?->clasificacion?->value ?? $solicitud->clasificacion_manual;
        [$clasifBg, $clasifFg] = $coloresClasificacion[$clasificacionVigente] ?? ['#f1f5f9', '#475569'];

        $coloresDecision = [
            'pendiente' => ['#f1f5f9', '#475569'],
            'aprobado' => ['#dcfce7', '#166534'],
            'devuelto' => ['#fee2e2', '#991b1b'],
        ];

        $coloresRiesgo = [
            'Bajo' => ['#dcfce7', '#166534'],
            'Medio' => ['#fef3c7', '#92400e'],
            'Alto' => ['#fee2e2', '#991b1b'],
            'Crítico' => ['#fee2e2', '#991b1b'],
        ];

        $coloresValorRespuesta = [
            'SI' => ['#dcfce7', '#166534'],
            'NO' => ['#fee2e2', '#991b1b'],
            'NA' => ['#f1f5f9', '#475569'],
        ];

        $imagenesPorCampo = $solicitud->adjuntos->groupBy('campo');

        $imagenDataUri = function ($adjunto) {
            try {
                $contenido = \Illuminate\Support\Facades\Storage::disk($adjunto->disco)->get($adjunto->ruta);
            } catch (\Throwable $e) {
                return null;
            }

            return $contenido ? 'data:'.($adjunto->mime ?: 'image/jpeg').';base64,'.base64_encode($contenido) : null;
        };

        $respuestasPorProceso = $solicitud->respuestas
            ->filter(fn ($r) => $r->pregunta)
            ->sortBy(fn ($r) => (($r->pregunta->proceso?->orden ?? 0) * 1000) + ($r->pregunta->orden ?? 0))
            ->groupBy(fn ($r) => $r->pregunta->proceso?->nombre ?? 'Sin proceso');

        $aprobacionesIniciales = $solicitud->aprobaciones->where('etapa', \App\Models\AprobacionSolicitud::ETAPA_INICIAL);
        $aprobacionesCierre = $solicitud->aprobaciones->where('etapa', \App\Models\AprobacionSolicitud::ETAPA_CIERRE);
    @endphp

    <table class="head head-bar">
        <tr>
            <td style="width:65px;">
                <svg viewBox="0 0 316 316" width="46" height="46" xmlns="http://www.w3.org/2000/svg" style="fill:#0f766e;">
                    <path d="M305.8 81.125C305.77 80.995 305.69 80.885 305.65 80.755C305.56 80.525 305.49 80.285 305.37 80.075C305.29 79.935 305.17 79.815 305.07 79.685C304.94 79.515 304.83 79.325 304.68 79.175C304.55 79.045 304.39 78.955 304.25 78.845C304.09 78.715 303.95 78.575 303.77 78.475L251.32 48.275C249.97 47.495 248.31 47.495 246.96 48.275L194.51 78.475C194.33 78.575 194.19 78.725 194.03 78.845C193.89 78.955 193.73 79.045 193.6 79.175C193.45 79.325 193.34 79.515 193.21 79.685C193.11 79.815 192.99 79.935 192.91 80.075C192.79 80.285 192.71 80.525 192.63 80.755C192.58 80.875 192.51 80.995 192.48 81.125C192.38 81.495 192.33 81.875 192.33 82.265V139.625L148.62 164.795V52.575C148.62 52.185 148.57 51.805 148.47 51.435C148.44 51.305 148.36 51.195 148.32 51.065C148.23 50.835 148.16 50.595 148.04 50.385C147.96 50.245 147.84 50.125 147.74 49.995C147.61 49.825 147.5 49.635 147.35 49.485C147.22 49.355 147.06 49.265 146.92 49.155C146.76 49.025 146.62 48.885 146.44 48.785L93.99 18.585C92.64 17.805 90.98 17.805 89.63 18.585L37.18 48.785C37 48.885 36.86 49.035 36.7 49.155C36.56 49.265 36.4 49.355 36.27 49.485C36.12 49.635 36.01 49.825 35.88 49.995C35.78 50.125 35.66 50.245 35.58 50.385C35.46 50.595 35.38 50.835 35.3 51.065C35.25 51.185 35.18 51.305 35.15 51.435C35.05 51.805 35 52.185 35 52.575V232.235C35 233.795 35.84 235.245 37.19 236.025L142.1 296.425C142.33 296.555 142.58 296.635 142.82 296.725C142.93 296.765 143.04 296.835 143.16 296.865C143.53 296.965 143.9 297.015 144.28 297.015C144.66 297.015 145.03 296.965 145.4 296.865C145.5 296.835 145.59 296.775 145.69 296.745C145.95 296.655 146.21 296.565 146.45 296.435L251.36 236.035C252.72 235.255 253.55 233.815 253.55 232.245V174.885L303.81 145.945C305.17 145.165 306 143.725 306 142.155V82.265C305.95 81.875 305.89 81.495 305.8 81.125ZM144.2 227.205L100.57 202.515L146.39 176.135L196.66 147.195L240.33 172.335L208.29 190.625L144.2 227.205ZM244.75 114.995V164.795L226.39 154.225L201.03 139.625V89.825L219.39 100.395L244.75 114.995ZM249.12 57.105L292.81 82.265L249.12 107.425L205.43 82.265L249.12 57.105ZM114.49 184.425L96.13 194.995V85.305L121.49 70.705L139.85 60.135V169.815L114.49 184.425ZM91.76 27.425L135.45 52.585L91.76 77.745L48.07 52.585L91.76 27.425ZM43.67 60.135L62.03 70.705L87.39 85.305V202.545V202.555V202.565C87.39 202.735 87.44 202.895 87.46 203.055C87.49 203.265 87.49 203.485 87.55 203.695V203.705C87.6 203.875 87.69 204.035 87.76 204.195C87.84 204.375 87.89 204.575 87.99 204.745C87.99 204.745 87.99 204.755 88 204.755C88.09 204.905 88.22 205.035 88.33 205.175C88.45 205.335 88.55 205.495 88.69 205.635L88.7 205.645C88.82 205.765 88.98 205.855 89.12 205.965C89.28 206.085 89.42 206.225 89.59 206.325C89.6 206.325 89.6 206.325 89.61 206.335C89.62 206.335 89.62 206.345 89.63 206.345L139.87 234.775V285.065L43.67 229.705V60.135ZM244.75 229.705L148.58 285.075V234.775L219.8 194.115L244.75 179.875V229.705ZM297.2 139.625L253.49 164.795V114.995L278.85 100.395L297.21 89.825V139.625H297.2Z"/>
                </svg>
            </td>
            <td>
                <h1>Gestión del Cambio</h1>
                <div class="muted">Formato FOSIG-02 · Solicitud, evaluación, aprobación y cierre</div>
            </td>
            <td class="right" style="width:160px;">
                <div style="font-size:13px;font-weight:bold;">{{ $solicitud->consecutivo }}</div>
                <span class="badge" style="background:{{ $estadoBg }};color:{{ $estadoFg }};">{{ $solicitud->estado->etiqueta() }}</span>
            </td>
        </tr>
    </table>

    <h2>1. Resumen del cambio</h2>
    <table class="grid">
        <tr><th>Consecutivo</th><td>{{ $solicitud->consecutivo }}</td><th>Responsable / líder</th><td>{{ $solicitud->responsable?->name }}</td></tr>
        <tr><th>Fecha</th><td>{{ $solicitud->fecha?->format('d/m/Y') }}</td><th>Nombre del cambio</th><td>{{ $solicitud->nombre_cambio }}</td></tr>
        <tr><th>Solicitante / cargo</th><td>{{ $solicitud->solicitante_cargo }}</td><th>Área / Proceso</th><td>{{ $solicitud->area_proceso }}</td></tr>
        <tr><th>Tipo de cambio</th><td>{{ $solicitud->tipo_cambio }}</td><th>Fecha requerida</th><td>{{ optional($solicitud->fecha_requerida)->format('d/m/Y') }}</td></tr>
        <tr>
            <th>Costo estimado</th>
            <td>{{ $solicitud->costo_estimado ? \Illuminate\Support\Number::currency(round((float) $solicitud->costo_estimado), in: 'COP', locale: 'es_CO') : '—' }}</td>
            <th>Clasificación</th>
            <td><span class="badge" style="background:{{ $clasifBg }};color:{{ $clasifFg }};">{{ $clasificacionVigente ?? 'Sin evaluar' }}</span></td>
        </tr>
    </table>

    <h2>2. Descripción simple</h2>
    <table class="grid">
        @foreach ([
            'situacion_actual' => 'Situación actual',
            'que_cambiara' => 'Qué cambiará',
            'resultado_esperado' => 'Resultado esperado',
        ] as $campo => $etiqueta)
            <tr>
                <th>{{ $etiqueta }}</th>
                <td>
                    {{ $solicitud->{$campo} }}
                    @php($imagenes = $imagenesPorCampo->get($campo, collect()))
                    @if ($imagenes->isNotEmpty())
                        <div class="img-row">
                            @foreach ($imagenes as $img)
                                @php($uri = $imagenDataUri($img))
                                @if ($uri)
                                    <img src="{{ $uri }}" alt="{{ $img->nombre_original }}">
                                @endif
                            @endforeach
                        </div>
                    @endif
                </td>
            </tr>
        @endforeach
    </table>

    <h2>3. Evaluación y clasificación del cambio</h2>
    @if ($solicitud->evaluacion)
        <table>
            <thead>
                <tr><th style="width:28%;">Criterio</th><th>Nivel seleccionado</th><th style="width:8%;">Valor</th></tr>
            </thead>
            <tbody>
                @foreach ($solicitud->evaluacion->calificaciones->sortBy(fn ($c) => $c->criterio?->orden ?? 0) as $c)
                    <tr>
                        <td>{{ $c->criterio?->nombre }}</td>
                        <td>{{ $c->criterio?->{'desc_nivel_'.$c->valor} }}</td>
                        <td class="center">{{ $c->valor }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <table class="grid">
            <tr>
                <th style="width:16%;">Suma total</th><td>{{ $solicitud->evaluacion->suma }}</td>
                <th style="width:16%;">Clasificación resultante</th>
                <td><span class="badge" style="background:{{ $clasifBg }};color:{{ $clasifFg }};">{{ $solicitud->evaluacion->clasificacion?->value }}</span></td>
            </tr>
        </table>
    @else
        <p class="muted">Sin evaluación registrada.</p>
    @endif

    <h2>4. Cuestionario de preguntas clave</h2>
    @forelse ($respuestasPorProceso as $proceso => $respuestas)
        <h3>{{ $proceso }}</h3>
        <table>
            <thead>
                <tr><th>Pregunta</th><th style="width:10%;">Respuesta</th><th style="width:30%;">Riesgo predeterminado</th></tr>
            </thead>
            <tbody>
                @foreach ($respuestas as $r)
                    @php([$vBg, $vFg] = $coloresValorRespuesta[$r->valor->value] ?? ['#f1f5f9', '#475569'])
                    <tr>
                        <td>{{ $r->pregunta->texto }}</td>
                        <td class="center"><span class="badge" style="background:{{ $vBg }};color:{{ $vFg }};">{{ $r->valor->etiqueta() }}</span></td>
                        <td>{{ $r->valor->value === 'SI' ? ($r->pregunta->riesgoPredeterminado?->texto ?? '—') : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @empty
        <p class="muted">Cuestionario no diligenciado.</p>
    @endforelse

    <h2>5. Riesgos asociados</h2>
    <table>
        <thead>
            <tr><th>Proceso</th><th>Riesgo</th><th>Control existente</th><th>Validación requerida</th><th>Resp.</th><th>Fecha</th><th>P</th><th>I</th><th>NR</th><th>Nivel</th></tr>
        </thead>
        <tbody>
            @forelse ($solicitud->riesgosAsociados as $r)
                @php([$nBg, $nFg] = $coloresRiesgo[$r->nivel?->value] ?? ['#f1f5f9', '#475569'])
                <tr>
                    <td>{{ $r->proceso_nombre }}</td><td>{{ $r->riesgo_texto }}</td><td>{{ $r->control_existente }}</td>
                    <td>{{ $r->accion_requerida }}</td><td>{{ $r->responsable }}</td><td>{{ optional($r->fecha)->format('d/m/Y') }}</td>
                    <td class="center">{{ $r->probabilidad }}</td><td class="center">{{ $r->impacto }}</td><td class="center">{{ $r->nr }}</td>
                    <td><span class="badge" style="background:{{ $nBg }};color:{{ $nFg }};">{{ $r->nivel?->value }}</span></td>
                </tr>
            @empty
                <tr><td colspan="10" class="muted">Sin riesgos asociados.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>6. Plan de acción y seguimiento</h2>
    <table>
        <thead>
            <tr><th>#</th><th>Descripción</th><th>Riesgo asociado</th><th>Proceso</th><th>Responsable</th><th>Fecha</th><th>Estado</th><th>Evidencia</th><th>Adjuntos</th></tr>
        </thead>
        <tbody>
            @forelse ($solicitud->accionesPlan as $a)
                @php($estadoAccion = $a->estado instanceof \App\Enums\EstadoAccionPlan ? $a->estado : null)
                @php($coloresAccion = [
                    'Pendiente' => ['#f1f5f9', '#475569'],
                    'En curso' => ['#fef3c7', '#92400e'],
                    'Cerrada — pendiente de validación' => ['#dbeafe', '#1e40af'],
                    'Validada' => ['#dcfce7', '#166534'],
                    'Rechazada' => ['#fee2e2', '#991b1b'],
                ])
                @php([$aBg, $aFg] = $coloresAccion[$estadoAccion?->value ?? $a->estado] ?? ['#f1f5f9', '#475569'])
                <tr>
                    <td class="center">{{ $a->numero }}</td><td>{{ $a->descripcion }}</td>
                    <td>{{ $a->riesgoAsociado?->riesgo_texto ? ($a->riesgoAsociado->riesgo_texto.' ('.$a->riesgoAsociado->nivel?->value.')') : '—' }}</td>
                    <td>{{ $a->proceso }}</td><td>{{ $a->responsable }}</td>
                    <td>{{ optional($a->fecha)->format('d/m/Y') }}</td>
                    <td><span class="badge" style="background:{{ $aBg }};color:{{ $aFg }};">{{ $estadoAccion?->etiqueta() ?? $a->estado }}</span></td>
                    <td>{{ $a->evidencia }}</td>
                    <td>{{ $a->adjuntos->pluck('nombre_original')->join(', ') ?: '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="9" class="muted">Sin acciones.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>7. Seguimiento y cierre</h2>
    <table>
        <thead>
            <tr><th>Criterio</th><th style="width:8%;">Sí/No/N-A</th><th>Detalle / evidencia</th><th>Responsable</th><th>Adjuntos</th></tr>
        </thead>
        <tbody>
            @forelse ($solicitud->criteriosCierre as $cc)
                <tr>
                    <td>{{ $cc->descripcion }}</td><td class="center">{{ $cc->valor }}</td><td>{{ $cc->detalle }}</td>
                    <td>{{ $cc->responsable }}</td><td>{{ $cc->adjuntos->pluck('nombre_original')->join(', ') ?: '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">Sin criterios.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>8. Historial de aprobaciones</h2>
    @if ($solicitud->aprobaciones->isEmpty())
        <p class="muted">Sin aprobadores asignados aún.</p>
    @else
        @foreach ([
            'Etapa inicial' => $aprobacionesIniciales,
            'Etapa de cierre' => $aprobacionesCierre,
        ] as $titulo => $grupo)
            @if ($grupo->isNotEmpty())
                <h3>{{ $titulo }}</h3>
                <table>
                    <thead>
                        <tr><th>Aprobador</th><th style="width:14%;">Decisión</th><th style="width:16%;">Fecha</th><th>Comentario</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($grupo as $decision)
                            @php([$dBg, $dFg] = $coloresDecision[$decision->decision] ?? ['#f1f5f9', '#475569'])
                            <tr>
                                <td>{{ $decision->usuario?->name }}</td>
                                <td><span class="badge" style="background:{{ $dBg }};color:{{ $dFg }};">{{ ucfirst($decision->decision) }}</span></td>
                                <td>{{ optional($decision->decidido_at)->format('d/m/Y H:i') ?? '—' }}</td>
                                <td>{{ $decision->comentario ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        @endforeach
    @endif

    <p class="footer-note">Documento generado el {{ now()->format('d/m/Y H:i') }} · {{ config('app.name') }}</p>
</body>
</html>
