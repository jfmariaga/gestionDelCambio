<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvaluacionCalificacion extends Model
{
    protected $table = 'evaluacion_calificaciones';

    protected $fillable = ['evaluacion_cambio_id', 'criterio_rubrica_id', 'valor'];

    protected $casts = ['valor' => 'integer'];

    public function evaluacion(): BelongsTo
    {
        return $this->belongsTo(EvaluacionCambio::class, 'evaluacion_cambio_id');
    }

    public function criterio(): BelongsTo
    {
        return $this->belongsTo(CriterioRubrica::class, 'criterio_rubrica_id');
    }
}
