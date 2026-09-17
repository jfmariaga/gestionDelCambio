<?php

namespace App\Policies;

use App\Enums\EstadoSolicitud;
use App\Models\AprobacionSolicitud;
use App\Models\SolicitudCambio;
use App\Models\User;

/**
 * Autorización por-registro para la solicitud de cambio (FR-034…FR-044 + matriz de la spec).
 */
class SolicitudCambioPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        // decidir/decidirCierre son por-fila: incluso el administrador debe tener una
        // aprobación pendiente propia para esa etapa, si no, el botón "Aprobar" seguiría
        // visible después de haber decidido (o sin haber sido nunca aprobador asignado).
        if (in_array($ability, ['decidir', 'decidirCierre'], true)) {
            return null;
        }

        return $user->hasRole('administrador') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['solicitante', 'dueno_proceso', 'aprobador', 'consulta']);
    }

    public function view(User $user, SolicitudCambio $solicitud): bool
    {
        // Los roles son aditivos: basta con que UNO conceda acceso (gana el más permisivo).

        // Autor / líder del cambio: siempre ve sus solicitudes.
        if ($this->esAutor($user, $solicitud)) {
            return true;
        }

        // Aprobador: ve todo lo que ya salió de borrador (y sus propios borradores).
        if ($user->hasRole('aprobador')
            && ($solicitud->estado !== EstadoSolicitud::Solicitado || $solicitud->created_by === $user->id)) {
            return true;
        }

        // Dueño de proceso: ve las solicitudes que tienen filas de sus procesos.
        if ($user->hasRole('dueno_proceso') && $this->tieneFilasDeSusProcesos($user, $solicitud)) {
            return true;
        }

        // Consulta / auditoría: solo lectura de estados aprobado o posteriores.
        if ($user->hasRole('consulta') && in_array($solicitud->estado, [
            EstadoSolicitud::Aprobado, EstadoSolicitud::EnImplementacion,
            EstadoSolicitud::Implementado, EstadoSolicitud::EnVerificacion, EstadoSolicitud::Cerrado,
        ], true)) {
            return true;
        }

        // Aprobador de cualquiera de las dos compuertas: puede ver la solicitud que debe decidir.
        if ($solicitud->aprobaciones()->where('user_id', $user->id)->exists()) {
            return true;
        }

        // Responsable o creador de una tarea del plan de acción, aunque no tenga otro rol
        // (FR-043/FR-055): así el enlace de su notificación no lo deja en una página muerta.
        if ($solicitud->accionesPlan()
            ->where(fn ($q) => $q->where('responsable_id', $user->id)->orWhere('creador_id', $user->id))
            ->exists()) {
            return true;
        }

        // Responsable de un criterio de cierre, mismo criterio que las tareas del plan.
        if ($solicitud->criteriosCierre()->where('responsable_id', $user->id)->exists()) {
            return true;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('solicitante');
    }

    public function update(User $user, SolicitudCambio $solicitud): bool
    {
        return $this->esAutor($user, $solicitud) && $solicitud->estado === EstadoSolicitud::Solicitado;
    }

    public function enviar(User $user, SolicitudCambio $solicitud): bool
    {
        return $this->esAutor($user, $solicitud) && $solicitud->estado === EstadoSolicitud::Solicitado;
    }

    /** Decidir la compuerta inicial: solo un aprobador con fila pendiente/devuelta en etapa "inicial". */
    public function decidir(User $user, SolicitudCambio $solicitud): bool
    {
        return $solicitud->estado === EstadoSolicitud::EnEvaluacion
            && $solicitud->aprobacionPendiente($user, AprobacionSolicitud::ETAPA_INICIAL) !== null;
    }

    /** Decidir la compuerta de cierre: aprobador con fila pendiente/devuelta en etapa "cierre". */
    public function decidirCierre(User $user, SolicitudCambio $solicitud): bool
    {
        return $solicitud->estado === EstadoSolicitud::EnVerificacion
            && $solicitud->aprobacionPendiente($user, AprobacionSolicitud::ETAPA_CIERRE) !== null;
    }

    /** El líder del cambio inicia la implementación y la envía a verificación. */
    public function gestionarImplementacion(User $user, SolicitudCambio $solicitud): bool
    {
        return ($this->esAutor($user, $solicitud) || $user->hasRole('aprobador'))
            && in_array($solicitud->estado, [
                EstadoSolicitud::Aprobado, EstadoSolicitud::EnImplementacion, EstadoSolicitud::Implementado,
            ], true);
    }

    public function cerrar(User $user, SolicitudCambio $solicitud): bool
    {
        return $user->hasRole('aprobador') && in_array($solicitud->estado, [
            EstadoSolicitud::EnVerificacion,
        ], true);
    }

    public function exportar(User $user, SolicitudCambio $solicitud): bool
    {
        return $this->view($user, $solicitud);
    }

    /** Completar consideraciones / riesgos: autor o dueño de proceso, mientras sea editable. */
    public function completarSecciones(User $user, SolicitudCambio $solicitud): bool
    {
        if (! $solicitud->estado->esEditable()) {
            return false;
        }

        return $this->esAutor($user, $solicitud) || $user->hasRole('dueno_proceso');
    }

    private function esAutor(User $user, SolicitudCambio $solicitud): bool
    {
        return $solicitud->created_by === $user->id && $user->hasRole('solicitante');
    }

    private function tieneFilasDeSusProcesos(User $user, SolicitudCambio $solicitud): bool
    {
        $nombres = $user->procesos()->pluck('nombre');

        return $solicitud->riesgosAsociados()->whereIn('proceso_nombre', $nombres)->exists();
    }
}
