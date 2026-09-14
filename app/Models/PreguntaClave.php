<?php

namespace App\Models;

use Database\Factories\PreguntaClaveFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PreguntaClave extends Model
{
    /** @use HasFactory<PreguntaClaveFactory> */
    use HasFactory;

    protected $table = 'preguntas_clave';

    protected $fillable = [
        'proceso_id',
        'riesgo_predeterminado_id',
        'texto',
        'dueno_por_defecto',
        'evidencia_por_defecto',
        'accion_por_defecto',
        'orden',
        'activo',
    ];

    protected $casts = ['activo' => 'boolean'];

    public function proceso(): BelongsTo
    {
        return $this->belongsTo(Proceso::class);
    }

    public function riesgoPredeterminado(): BelongsTo
    {
        return $this->belongsTo(RiesgoPredeterminado::class);
    }

    public function scopeActivas($query)
    {
        return $query->where('activo', true);
    }
}
