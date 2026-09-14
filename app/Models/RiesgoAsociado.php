<?php

namespace App\Models;

use App\Enums\EstadoFila;
use App\Enums\NivelRiesgo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RiesgoAsociado extends Model
{
    protected $table = 'riesgos_asociados';

    protected $fillable = [
        'solicitud_cambio_id',
        'riesgo_predeterminado_id',
        'proceso_nombre',
        'riesgo_texto',
        'control_existente',
        'accion_requerida',
        'responsable',
        'responsable_id',
        'fecha',
        'probabilidad',
        'impacto',
        'nr',
        'nivel',
        'editado_manualmente',
        'estado',
    ];

    protected $casts = [
        'fecha' => 'date',
        'probabilidad' => 'integer',
        'impacto' => 'integer',
        'nr' => 'integer',
        'nivel' => NivelRiesgo::class,
        'editado_manualmente' => 'boolean',
        'estado' => EstadoFila::class,
    ];

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudCambio::class, 'solicitud_cambio_id');
    }

    public function riesgoPredeterminado(): BelongsTo
    {
        return $this->belongsTo(RiesgoPredeterminado::class);
    }

    public function preguntas(): BelongsToMany
    {
        return $this->belongsToMany(PreguntaClave::class, 'riesgo_asociado_pregunta');
    }

    public function responsableUsuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    /** Acción del plan generada para este riesgo (solo Medio/Alto). */
    public function accionPlan(): HasOne
    {
        return $this->hasOne(AccionPlan::class, 'riesgo_asociado_id');
    }

    public function esMedioOAlto(): bool
    {
        return in_array($this->nivel, [NivelRiesgo::Medio, NivelRiesgo::Alto], true);
    }
}
