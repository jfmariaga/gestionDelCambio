<?php

namespace App\Models;

use App\Models\Concerns\TieneAdjuntos;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CriterioCierre extends Model
{
    use TieneAdjuntos;

    protected $table = 'criterios_cierre';

    protected $fillable = ['solicitud_cambio_id', 'descripcion', 'valor', 'detalle', 'responsable', 'responsable_id'];

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudCambio::class, 'solicitud_cambio_id');
    }

    public function responsableUsuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }
}
