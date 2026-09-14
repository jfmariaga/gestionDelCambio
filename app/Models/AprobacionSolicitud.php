<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Decisión de un aprobador en una de las dos compuertas de una solicitud.
 *
 * @property string $etapa 'inicial' | 'cierre'
 * @property string $decision 'pendiente' | 'aprobado' | 'devuelto'
 */
class AprobacionSolicitud extends Model
{
    public const ETAPA_INICIAL = 'inicial';

    public const ETAPA_CIERRE = 'cierre';

    public const PENDIENTE = 'pendiente';

    public const APROBADO = 'aprobado';

    public const DEVUELTO = 'devuelto';

    protected $table = 'aprobaciones_solicitud';

    protected $fillable = [
        'solicitud_cambio_id',
        'user_id',
        'etapa',
        'decision',
        'comentario',
        'decidido_at',
    ];

    protected $casts = [
        'decidido_at' => 'datetime',
    ];

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudCambio::class, 'solicitud_cambio_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
