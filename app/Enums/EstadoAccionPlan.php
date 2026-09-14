<?php

namespace App\Enums;

/**
 * Estado de una acción del plan de acción (sección 5) con el flujo de validación de la
 * Revisión R2 (US9 / FR-054…FR-057):
 *
 *   Pendiente / En curso  --(responsable marca cerrada)-->  Cerrada — pendiente de validación
 *   Cerrada — pendiente de validación  --(líder valida)-->  Validada
 *   Cerrada — pendiente de validación  --(líder rechaza)-->  En curso  (queda comentario)
 *
 * Una solicitud no puede cerrarse si tiene acciones que no estén en Validada.
 */
enum EstadoAccionPlan: string
{
    case Pendiente = 'Pendiente';
    case EnCurso = 'En curso';
    case CerradaPendienteValidacion = 'Cerrada — pendiente de validación';
    case Validada = 'Validada';
    case Rechazada = 'Rechazada';

    public function etiqueta(): string
    {
        return $this->value;
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Pendiente => 'badge-gray',
            self::EnCurso => 'badge-amber',
            self::CerradaPendienteValidacion => 'badge-blue',
            self::Validada => 'badge-green',
            self::Rechazada => 'badge-red',
        };
    }

    /** ¿Cuenta como completada para permitir el cierre de la solicitud? */
    public function estaValidada(): bool
    {
        return $this === self::Validada;
    }
}
