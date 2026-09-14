<?php

namespace App\Domain\GestionCambio;

use App\Enums\Clasificacion;
use App\Models\SolicitudCambio;

/**
 * Determina qué secciones del formulario de una solicitud están habilitadas según la
 * clasificación vigente del cambio (Revisión R2 / US7 / FR-045…FR-049):
 *
 *  - Sin evaluación completa: solo "resumen" y "evaluacion".
 *  - Menor: además "plan" y "cierre" (sin cuestionario ni riesgos).
 *  - Mayor / Crítico / Revisar: habilita "cuestionario"; una vez respondido al 100%,
 *    habilita también "riesgos", "plan" y "cierre".
 */
final class GatingSecciones
{
    public const TODAS = ['resumen', 'evaluacion', 'cuestionario', 'riesgos', 'plan', 'cierre'];

    private const BASE = ['resumen', 'evaluacion'];

    private const MENOR = ['resumen', 'evaluacion', 'plan', 'cierre'];

    private const CUESTIONARIO_PENDIENTE = ['resumen', 'evaluacion', 'cuestionario'];

    /** Alias retirado: la sección "consideraciones" ya no existe. */

    /** @return list<string> */
    public function secciones(SolicitudCambio $solicitud): array
    {
        return match ($solicitud->clasificacionVigente()) {
            null => self::BASE,
            Clasificacion::Menor => self::MENOR,
            default => $solicitud->cuestionarioCompleto() ? self::TODAS : self::CUESTIONARIO_PENDIENTE,
        };
    }

    public function permite(SolicitudCambio $solicitud, string $seccion): bool
    {
        return in_array($seccion, $this->secciones($solicitud), true);
    }

    /** ¿La clasificación ya está resuelta (evaluación completa)? */
    public function evaluacionCompleta(SolicitudCambio $solicitud): bool
    {
        return $solicitud->clasificacionVigente() !== null;
    }

    /** ¿Falta completar el cuestionario para habilitar riesgos, plan y cierre? */
    public function cuestionarioPendiente(SolicitudCambio $solicitud): bool
    {
        $clasificacion = $solicitud->clasificacionVigente();

        return $clasificacion !== null
            && $clasificacion !== Clasificacion::Menor
            && ! $solicitud->cuestionarioCompleto();
    }
}
