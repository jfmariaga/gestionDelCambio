<?php

namespace App\Enums;

/**
 * Ciclo de vida de una solicitud de cambio (catálogo "Estado" del formato FOSIG-02).
 *
 *   solicitado ─enviarAEvaluacion→ en_evaluacion ─(todas aprueban)→ aprobado
 *     ↑ (una devuelve) ┘                                              │ iniciarImplementacion
 *   en_implementacion ←──────────────────────────────────────────────┘
 *     │ (todas las acciones validadas)
 *   implementado ─enviarAVerificacion→ en_verificacion ─(todas aprueban)→ cerrado
 *     ↑ (una devuelve) ┘
 *   (cualquier estado ≠ cerrado) ─cancelar→ cancelado
 */
enum EstadoSolicitud: string
{
    case Solicitado = 'solicitado';
    case EnEvaluacion = 'en_evaluacion';
    case Aprobado = 'aprobado';
    case EnImplementacion = 'en_implementacion';
    case Implementado = 'implementado';
    case EnVerificacion = 'en_verificacion';
    case Cerrado = 'cerrado';
    case Cancelado = 'cancelado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Solicitado => 'Solicitado',
            self::EnEvaluacion => 'En evaluación',
            self::Aprobado => 'Aprobado',
            self::EnImplementacion => 'En implementación',
            self::Implementado => 'Implementado',
            self::EnVerificacion => 'En verificación',
            self::Cerrado => 'Cerrado',
            self::Cancelado => 'Cancelado',
        };
    }

    /** Clase de badge (ver resources/css/app.css) para el estado. */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Solicitado => 'badge-gray',
            self::EnEvaluacion => 'badge-amber',
            self::Aprobado => 'badge-blue',
            self::EnImplementacion => 'badge-violet',
            self::Implementado => 'badge-brand',
            self::EnVerificacion => 'badge-amber',
            self::Cerrado => 'badge-green',
            self::Cancelado => 'badge-red',
        };
    }

    /** Estados en los que la solicitud aún puede editarse / sincronizarse. */
    public function esEditable(): bool
    {
        return in_array($this, [self::Solicitado, self::EnEvaluacion], true);
    }

    /** La sincronización automática de secciones (cuestionario → riesgos) solo corre al inicio. */
    public function permiteSincronizacion(): bool
    {
        return $this === self::Solicitado;
    }
}
