<?php

namespace App\Models;

use App\Enums\ValorRespuesta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RespuestaPregunta extends Model
{
    protected $table = 'respuestas_pregunta';

    protected $fillable = ['solicitud_cambio_id', 'pregunta_clave_id', 'valor', 'respondida_at'];

    protected $casts = [
        'valor' => ValorRespuesta::class,
        'respondida_at' => 'datetime',
    ];

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudCambio::class, 'solicitud_cambio_id');
    }

    public function pregunta(): BelongsTo
    {
        return $this->belongsTo(PreguntaClave::class, 'pregunta_clave_id');
    }
}
