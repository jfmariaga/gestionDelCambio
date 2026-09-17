<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Repara el `url` de notificaciones ya guardadas para que apunten al destino vigente:
 * tarea_asignada/tarea_por_validar/tarea_rechazada -> vista de foco de una sola tarea
 * (solicitudes.tarea); criterio_cierre_asignado -> sección de cierre en solicitudes.edit.
 * Se puede correr varias veces sin problema: solo toca lo que no tenga ya el formato vigente.
 */
class ActualizarEnlacesNotificaciones extends Command
{
    protected $signature = 'app:actualizar-enlaces-notificaciones';

    protected $description = 'Repara el enlace de notificaciones ya guardadas que aún apuntan a un destino viejo';

    private const TIPOS_TAREA = ['tarea_asignada', 'tarea_por_validar', 'tarea_rechazada'];

    public function handle(): int
    {
        $actualizadas = 0;

        DatabaseNotification::query()
            ->whereIn('data->tipo', [...self::TIPOS_TAREA, 'criterio_cierre_asignado'])
            ->each(function (DatabaseNotification $notificacion) use (&$actualizadas) {
                $datos = $notificacion->data;
                $nuevaUrl = $this->urlVigente($datos);

                if ($nuevaUrl === null || $nuevaUrl === ($datos['url'] ?? null)) {
                    return;
                }

                $datos['url'] = $nuevaUrl;
                $notificacion->forceFill(['data' => $datos])->save();
                $actualizadas++;
            });

        $this->info("Notificaciones actualizadas: {$actualizadas}.");

        return self::SUCCESS;
    }

    /** @param  array<string,mixed>  $datos */
    private function urlVigente(array $datos): ?string
    {
        if (! isset($datos['solicitud_id'])) {
            return null;
        }

        if (in_array($datos['tipo'], self::TIPOS_TAREA, true) && isset($datos['accion_id'])) {
            return route('solicitudes.tarea', [$datos['solicitud_id'], $datos['accion_id']]);
        }

        if ($datos['tipo'] === 'criterio_cierre_asignado') {
            // La vista de foco por criterio se retiró (Seguimiento y cierre ahora es una sola
            // nota); esto solo repara notificaciones históricas para que no queden rotas.
            return route('solicitudes.edit', $datos['solicitud_id']).'#sec-6';
        }

        return null;
    }
}
