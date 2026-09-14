<?php

namespace App\Domain\GestionCambio;

use App\Models\SolicitudCambio;
use Illuminate\Support\Facades\DB;

/**
 * Genera el consecutivo de la solicitud con el formato <prefijo>-<AAAA>-<NNN>, reiniciando la
 * secuencia por año. El prefijo y el número de dígitos se configuran en config/gestion_cambio.php.
 * Tolerante a concurrencia: la columna `consecutivo` es única y se calcula con lock.
 */
final class GeneradorConsecutivo
{
    public static function siguiente(?int $anio = null): string
    {
        $anio ??= (int) now()->format('Y');
        $prefijo = config('gestion_cambio.consecutivo.prefijo', 'GC');
        $digitos = max(1, (int) config('gestion_cambio.consecutivo.digitos', 3));
        $base = "{$prefijo}-{$anio}-";

        return DB::transaction(function () use ($base, $digitos) {
            $ultimo = SolicitudCambio::query()
                ->where('consecutivo', 'like', $base.'%')
                ->lockForUpdate()
                ->orderByDesc('consecutivo')
                ->value('consecutivo');

            $secuencia = $ultimo ? ((int) substr($ultimo, strlen($base))) + 1 : 1;

            return $base.str_pad((string) $secuencia, $digitos, '0', STR_PAD_LEFT);
        });
    }

    /** Vista previa del próximo consecutivo (sin reservarlo). */
    public static function proximo(?int $anio = null): string
    {
        $anio ??= (int) now()->format('Y');
        $prefijo = config('gestion_cambio.consecutivo.prefijo', 'GC');
        $digitos = max(1, (int) config('gestion_cambio.consecutivo.digitos', 3));
        $base = "{$prefijo}-{$anio}-";

        $ultimo = SolicitudCambio::query()
            ->where('consecutivo', 'like', $base.'%')
            ->orderByDesc('consecutivo')
            ->value('consecutivo');

        $secuencia = $ultimo ? ((int) substr($ultimo, strlen($base))) + 1 : 1;

        return $base.str_pad((string) $secuencia, $digitos, '0', STR_PAD_LEFT);
    }
}
