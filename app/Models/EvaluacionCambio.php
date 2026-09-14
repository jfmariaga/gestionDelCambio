<?php

namespace App\Models;

use App\Enums\Clasificacion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EvaluacionCambio extends Model
{
    protected $table = 'evaluaciones_cambio';

    protected $fillable = ['solicitud_cambio_id', 'suma', 'clasificacion'];

    protected $casts = [
        'suma' => 'integer',
        'clasificacion' => Clasificacion::class,
    ];

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudCambio::class, 'solicitud_cambio_id');
    }

    public function calificaciones(): HasMany
    {
        return $this->hasMany(EvaluacionCalificacion::class);
    }
}
