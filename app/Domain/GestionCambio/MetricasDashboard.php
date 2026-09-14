<?php

namespace App\Domain\GestionCambio;

use App\Enums\EstadoAccionPlan;
use App\Enums\EstadoSolicitud;
use App\Models\AccionPlan;
use App\Models\SolicitudCambio;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Cálculo de las métricas del panel de administrador y de las listas de detalle
 * ("drill-down") que se muestran al hacer clic en cada tarjeta. Centraliza el filtrado
 * para que el resumen (tarjetas) y el detalle usen exactamente los mismos criterios.
 */
final class MetricasDashboard
{
    /** Etiqueta visible de cada tarjeta, en el orden en que se muestran. */
    public const ETIQUETAS = [
        'cambios' => 'Cambios',
        'planes' => 'Planes',
        'acciones' => 'Acciones',
        'acciones_completadas' => 'Acciones completadas',
        'acciones_en_proceso' => 'Acciones en proceso',
        'acciones_pendientes' => 'Acciones pendientes',
        'acciones_vencidas' => 'Acciones vencidas',
        'acciones_proximas_vencer' => 'Próximas a vencer',
        'cambios_cerrados' => 'Cambios cerrados',
    ];

    /** Color de acento de cada tarjeta (clases Tailwind), en el mismo orden que ETIQUETAS. */
    public const ACENTOS = [
        'cambios' => 'text-ink-900',
        'planes' => 'text-ink-900',
        'acciones' => 'text-ink-900',
        'acciones_completadas' => 'text-emerald-600',
        'acciones_en_proceso' => 'text-sky-600',
        'acciones_pendientes' => 'text-ink-500',
        'acciones_vencidas' => 'text-rose-600',
        'acciones_proximas_vencer' => 'text-amber-600',
        'cambios_cerrados' => 'text-emerald-600',
    ];

    /** Métricas cuyo detalle son solicitudes de cambio; el resto son acciones del plan. */
    private const METRICAS_DE_SOLICITUDES = ['cambios', 'planes', 'cambios_cerrados'];

    private const MAX_FILAS_DETALLE = 200;

    /** @param array<string,mixed> $filtros */
    public function solicitudesFiltradas(array $filtros): Builder
    {
        return SolicitudCambio::query()
            ->when($filtros['lider_id'] ?? null, fn ($q, $v) => $q->where('created_by', $v))
            ->when($filtros['responsable_id'] ?? null, fn ($q, $v) => $q->whereHas('accionesPlan', fn ($aq) => $aq->where('responsable_id', $v)))
            ->when($filtros['proceso'] ?? null, fn ($q, $v) => $q->where('area_proceso', $v))
            ->when($filtros['tipo_cambio'] ?? null, fn ($q, $v) => $q->where('tipo_cambio', $v))
            ->when($filtros['clasificacion'] ?? null, fn ($q, $v) => $q->whereHas('evaluacion', fn ($eq) => $eq->where('clasificacion', $v)))
            ->when($filtros['estado'] ?? null, fn ($q, $v) => $q->where('estado', $v))
            ->when($filtros['fecha_desde'] ?? null, fn ($q, $v) => $q->whereDate('fecha', '>=', $v))
            ->when($filtros['fecha_hasta'] ?? null, fn ($q, $v) => $q->whereDate('fecha', '<=', $v));
    }

    /** @param array<string,mixed> $filtros */
    public function accionesFiltradas(array $filtros): Builder
    {
        return AccionPlan::query()
            ->when($filtros['responsable_id'] ?? null, fn ($q, $v) => $q->where('responsable_id', $v))
            ->when($filtros['lider_id'] ?? null, fn ($q, $v) => $q->whereHas('solicitud', fn ($sq) => $sq->where('created_by', $v)))
            ->when($filtros['proceso'] ?? null, fn ($q, $v) => $q->where(fn ($pq) => $pq->where('proceso', $v)->orWhereHas('solicitud', fn ($sq) => $sq->where('area_proceso', $v))))
            ->when($filtros['tipo_cambio'] ?? null, fn ($q, $v) => $q->whereHas('solicitud', fn ($sq) => $sq->where('tipo_cambio', $v)))
            ->when($filtros['clasificacion'] ?? null, fn ($q, $v) => $q->whereHas('solicitud.evaluacion', fn ($eq) => $eq->where('clasificacion', $v)))
            ->when($filtros['estado'] ?? null, fn ($q, $v) => $q->whereHas('solicitud', fn ($sq) => $sq->where('estado', $v)))
            ->when($filtros['fecha_desde'] ?? null, fn ($q, $v) => $q->whereDate('fecha', '>=', $v))
            ->when($filtros['fecha_hasta'] ?? null, fn ($q, $v) => $q->whereDate('fecha', '<=', $v));
    }

    /** @param array<string,mixed> $filtros */
    public function calcular(array $filtros): array
    {
        $solicitudes = $this->solicitudesFiltradas($filtros);
        $acciones = $this->accionesFiltradas($filtros);
        $diasProximoVencimiento = (int) config('gestioncambio.dias_proximo_vencimiento');

        return [
            'cambios' => (clone $solicitudes)->count(),
            'planes' => (clone $solicitudes)->has('accionesPlan')->count(),
            'acciones' => (clone $acciones)->count(),
            'acciones_completadas' => (clone $acciones)->where('estado', EstadoAccionPlan::Validada)->count(),
            'acciones_en_proceso' => (clone $acciones)->whereIn('estado', [EstadoAccionPlan::EnCurso, EstadoAccionPlan::CerradaPendienteValidacion])->count(),
            'acciones_pendientes' => (clone $acciones)->where('estado', EstadoAccionPlan::Pendiente)->count(),
            'acciones_vencidas' => (clone $acciones)->vencidas()->count(),
            'acciones_proximas_vencer' => (clone $acciones)->proximasAVencer($diasProximoVencimiento)->count(),
            'cambios_cerrados' => (clone $solicitudes)->where('estado', EstadoSolicitud::Cerrado)->count(),
        ];
    }

    /** Total, completadas, en proceso, pendientes, vencidas y % de cumplimiento por responsable. */
    public function tablaPorResponsable(Builder $accionesQuery): array
    {
        return $accionesQuery
            ->with('responsableUsuario:id,name')
            ->get(['id', 'responsable_id', 'responsable', 'estado', 'fecha'])
            ->groupBy(fn (AccionPlan $a) => $a->responsable_id ?? 'sin_responsable')
            ->map(function ($acciones) {
                $primero = $acciones->first();
                $total = $acciones->count();
                $completadas = $acciones->where('estado', EstadoAccionPlan::Validada)->count();
                $vencidas = $acciones->filter(fn (AccionPlan $a) => $a->estado !== EstadoAccionPlan::Validada
                    && $a->fecha !== null && $a->fecha->isPast())->count();

                return [
                    'nombre' => $primero->responsableUsuario?->name ?? ($primero->responsable ?: 'Sin asignar'),
                    'total' => $total,
                    'completadas' => $completadas,
                    'en_proceso' => $acciones->whereIn('estado', [EstadoAccionPlan::EnCurso, EstadoAccionPlan::CerradaPendienteValidacion])->count(),
                    'pendientes' => $acciones->where('estado', EstadoAccionPlan::Pendiente)->count(),
                    'vencidas' => $vencidas,
                    'cumplimiento' => $total > 0 ? round($completadas / $total * 100) : 0,
                ];
            })
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    /** Últimos 6 meses: acciones programadas (creadas) vs. cerradas (validadas). */
    public function evolucionMensual(Builder $accionesQuery): array
    {
        return collect(range(5, 0))
            ->map(function (int $i) use ($accionesQuery) {
                $mes = now()->startOfMonth()->subMonths($i);

                return [
                    'mes' => $mes->translatedFormat('M Y'),
                    'programadas' => (clone $accionesQuery)->whereYear('created_at', $mes->year)->whereMonth('created_at', $mes->month)->count(),
                    'cerradas' => (clone $accionesQuery)->whereYear('validada_at', $mes->year)->whereMonth('validada_at', $mes->month)->count(),
                ];
            })
            ->all();
    }

    /** ¿La tarjeta $metrica muestra solicitudes de cambio (true) o acciones del plan (false)? */
    public function esMetricaDeSolicitudes(string $metrica): bool
    {
        return in_array($metrica, self::METRICAS_DE_SOLICITUDES, true);
    }

    /**
     * Detalle ("drill-down") de una tarjeta: hasta 200 filas, más recientes primero.
     *
     * @param  array<string,mixed>  $filtros
     */
    public function itemsPara(string $metrica, array $filtros): Collection
    {
        if (! array_key_exists($metrica, self::ETIQUETAS)) {
            return collect();
        }

        if ($this->esMetricaDeSolicitudes($metrica)) {
            $query = $this->aplicarMetricaSolicitudes($this->solicitudesFiltradas($filtros), $metrica)
                ->with(['planta', 'autor:id,name', 'evaluacion']);

            return $query->latest('fecha')->limit(self::MAX_FILAS_DETALLE)->get();
        }

        $query = $this->aplicarMetricaAcciones($this->accionesFiltradas($filtros), $metrica)
            ->with(['responsableUsuario:id,name', 'solicitud:id,consecutivo,nombre_cambio']);

        return $query->orderBy('fecha')->limit(self::MAX_FILAS_DETALLE)->get();
    }

    private function aplicarMetricaSolicitudes(Builder $query, string $metrica): Builder
    {
        return match ($metrica) {
            'planes' => $query->has('accionesPlan'),
            'cambios_cerrados' => $query->where('estado', EstadoSolicitud::Cerrado),
            default => $query, // 'cambios'
        };
    }

    private function aplicarMetricaAcciones(Builder $query, string $metrica): Builder
    {
        $diasProximoVencimiento = (int) config('gestioncambio.dias_proximo_vencimiento');

        return match ($metrica) {
            'acciones_completadas' => $query->where('estado', EstadoAccionPlan::Validada),
            'acciones_en_proceso' => $query->whereIn('estado', [EstadoAccionPlan::EnCurso, EstadoAccionPlan::CerradaPendienteValidacion]),
            'acciones_pendientes' => $query->where('estado', EstadoAccionPlan::Pendiente),
            'acciones_vencidas' => $query->vencidas(),
            'acciones_proximas_vencer' => $query->proximasAVencer($diasProximoVencimiento),
            default => $query, // 'acciones'
        };
    }
}
