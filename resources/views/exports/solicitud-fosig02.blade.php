<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; font-size: 10px; }
        h1 { font-size: 14px; margin: 0; }
        h2 { font-size: 11px; background: #eee; padding: 4px; margin: 14px 0 4px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        th, td { border: 1px solid #999; padding: 3px 4px; text-align: left; vertical-align: top; }
        th { background: #f3f3f3; }
        .head { width: 100%; border: 0; margin-bottom: 8px; }
        .head td { border: 0; }
        .muted { color: #666; }
    </style>
</head>
<body>
    <table class="head">
        <tr>
            <td><h1>Gestión del Cambio</h1></td>
            <td style="text-align:right">
                <strong>FOSIG-02</strong><br>
                {{ $solicitud->consecutivo }}<br>
                <span class="muted">Estado: {{ $solicitud->estado->etiqueta() }}</span>
            </td>
        </tr>
    </table>

    <h2>1. Resumen del cambio</h2>
    <table>
        <tr><th>Consecutivo</th><td>{{ $solicitud->consecutivo }}</td><th>Responsable / líder del cambio</th><td>{{ $solicitud->responsable?->name }}</td></tr>
        <tr><th>Fecha</th><td>{{ $solicitud->fecha?->format('Y-m-d') }}</td><th>Nombre del cambio</th><td>{{ $solicitud->nombre_cambio }}</td></tr>
        <tr><th>Solicitante / cargo</th><td>{{ $solicitud->solicitante_cargo }}</td><th>Área / Proceso</th><td>{{ $solicitud->area_proceso }}</td></tr>
        <tr><th>Tipo de cambio</th><td>{{ $solicitud->tipo_cambio }}</td><th>Fecha requerida</th><td>{{ optional($solicitud->fecha_requerida)->format('Y-m-d') }}</td></tr>
        <tr><th>Costo estimado</th><td>{{ $solicitud->costo_estimado ? \Illuminate\Support\Number::currency((float) $solicitud->costo_estimado, in: 'COP', locale: 'es_CO', precision: 0) : '' }}</td><th>¿Requiere comité?</th><td>{{ $solicitud->requiere_comite ? 'Sí' : 'No' }}</td></tr>
        <tr><th>Clasificación</th><td colspan="3">{{ $solicitud->evaluacion?->clasificacion?->value ?? $solicitud->clasificacion_manual ?? '—' }}</td></tr>
    </table>

    <h2>2. Descripción simple</h2>
    <table>
        <tr><th>Situación actual</th><td>{{ $solicitud->situacion_actual }}</td></tr>
        <tr><th>Qué cambiará</th><td>{{ $solicitud->que_cambiara }}</td></tr>
        <tr><th>Resultado esperado</th><td>{{ $solicitud->resultado_esperado }}</td></tr>
    </table>

    <h2>4. Riesgos asociados</h2>
    <table>
        <tr><th>Proceso</th><th>Riesgo</th><th>Control existente</th><th>Validación requerida</th><th>Resp.</th><th>Fecha</th><th>P</th><th>I</th><th>NR</th><th>Nivel</th></tr>
        @forelse ($solicitud->riesgosAsociados as $r)
            <tr>
                <td>{{ $r->proceso_nombre }}</td><td>{{ $r->riesgo_texto }}</td><td>{{ $r->control_existente }}</td>
                <td>{{ $r->accion_requerida }}</td><td>{{ $r->responsable }}</td><td>{{ optional($r->fecha)->format('Y-m-d') }}</td>
                <td>{{ $r->probabilidad }}</td><td>{{ $r->impacto }}</td><td>{{ $r->nr }}</td><td>{{ $r->nivel?->value }}</td>
            </tr>
        @empty
            <tr><td colspan="10" class="muted">Sin riesgos asociados.</td></tr>
        @endforelse
    </table>

    <h2>5. Plan de acción y seguimiento</h2>
    <table>
        <tr><th>#</th><th>Descripción</th><th>Riesgo asociado</th><th>Proceso</th><th>Responsable</th><th>Fecha</th><th>Estado</th><th>Evidencia</th><th>Adjuntos</th></tr>
        @forelse ($solicitud->accionesPlan as $a)
            <tr>
                <td>{{ $a->numero }}</td><td>{{ $a->descripcion }}</td>
                <td>{{ $a->riesgoAsociado?->riesgo_texto ? ($a->riesgoAsociado->riesgo_texto.' ('.$a->riesgoAsociado->nivel?->value.')') : '—' }}</td>
                <td>{{ $a->proceso }}</td><td>{{ $a->responsable }}</td>
                <td>{{ optional($a->fecha)->format('Y-m-d') }}</td>
                <td>{{ $a->estado?->value ?? $a->estado }}</td>
                <td>{{ $a->evidencia }}</td>
                <td>{{ $a->adjuntos->pluck('nombre_original')->join(', ') ?: '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="9" class="muted">Sin acciones.</td></tr>
        @endforelse
    </table>

    <h2>6. Seguimiento y cierre</h2>
    <table>
        <tr><th>Criterio</th><th>Sí/No/N-A</th><th>Detalle / evidencia</th><th>Responsable</th><th>Adjuntos</th></tr>
        @forelse ($solicitud->criteriosCierre as $cc)
            <tr><td>{{ $cc->descripcion }}</td><td>{{ $cc->valor }}</td><td>{{ $cc->detalle }}</td><td>{{ $cc->responsable }}</td><td>{{ $cc->adjuntos->pluck('nombre_original')->join(', ') ?: '—' }}</td></tr>
        @empty
            <tr><td colspan="5" class="muted">Sin criterios.</td></tr>
        @endforelse
    </table>
    @if ($solicitud->aprobado_at)
        <p class="muted">Aprobada el {{ $solicitud->aprobado_at->format('Y-m-d H:i') }}. {{ $solicitud->decision_comentario }}</p>
    @endif
</body>
</html>
