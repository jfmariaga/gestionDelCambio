<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BitacoraEvento extends Model
{
    protected $table = 'bitacora_eventos';

    public $timestamps = false;

    protected $fillable = ['solicitud_cambio_id', 'user_id', 'evento', 'comentario', 'datos', 'created_at'];

    protected $casts = [
        'datos' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (BitacoraEvento $evento) {
            $evento->created_at ??= now();
        });
    }

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudCambio::class, 'solicitud_cambio_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Texto legible del evento, para la bitácora de la solicitud. */
    public function etiqueta(): string
    {
        return match ($this->evento) {
            'enviada' => 'Enviada a evaluación',
            'aprobacion_inicial_registrada' => 'Aprobación inicial registrada',
            'aprobada' => 'Aprobación inicial completa',
            'devuelta' => 'Devuelta en aprobación inicial',
            'implementacion_iniciada' => 'Implementación iniciada',
            'plan_implementado' => 'Implementación completa',
            'enviada_verificacion' => 'Enviada a verificación',
            'aprobacion_cierre_registrada' => 'Aprobación de cierre registrada',
            'cierre_devuelto' => 'Devuelta en verificación',
            'cerrada' => 'Solicitud cerrada',
            'anulada' => 'Solicitud anulada',
            'aprobador_reasignado' => 'Aprobador reasignado',
            'aprobadores_sin_resolver' => 'Sin aprobadores resueltos para la etapa',
            'notificacion_sin_destinatarios' => 'Área de notificación sin destinatarios',
            default => ucfirst(str_replace('_', ' ', $this->evento)),
        };
    }

    /** Clase de badge (ver resources/css/app.css) para el evento. */
    public function badgeClass(): string
    {
        return match ($this->evento) {
            'aprobada', 'cerrada' => 'badge-green',
            'devuelta', 'cierre_devuelto', 'anulada' => 'badge-red',
            'aprobadores_sin_resolver', 'notificacion_sin_destinatarios' => 'badge-amber',
            default => 'badge-gray',
        };
    }

    /** Color del punto de la línea de tiempo en el historial, a juego con badgeClass(). */
    public function dotClass(): string
    {
        return match ($this->badgeClass()) {
            'badge-green' => 'bg-emerald-500',
            'badge-red' => 'bg-rose-500',
            'badge-amber' => 'bg-amber-500',
            default => 'bg-ink-300',
        };
    }
}
