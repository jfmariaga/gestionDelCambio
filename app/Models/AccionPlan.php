<?php

namespace App\Models;

use App\Enums\EstadoAccionPlan;
use App\Models\Concerns\TieneAdjuntos;
use Database\Factories\AccionPlanFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccionPlan extends Model
{
    /** @use HasFactory<AccionPlanFactory> */
    use HasFactory, TieneAdjuntos;

    protected $table = 'acciones_plan';

    protected $fillable = [
        'solicitud_cambio_id',
        'riesgo_asociado_id',
        'numero',
        'descripcion',
        'proceso',
        'responsable',
        'responsable_id',
        'creador_id',
        'validada_por',
        'validada_at',
        'comentario_validacion',
        'fecha',
        'estado',
        'evidencia',
        'nota',
    ];

    protected $casts = [
        'numero' => 'integer',
        'fecha' => 'date',
        'validada_at' => 'datetime',
        'estado' => EstadoAccionPlan::class,
    ];

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudCambio::class, 'solicitud_cambio_id');
    }

    /** Riesgo asociado (Medio/Alto) que originó esta acción; null si es una acción manual. */
    public function riesgoAsociado(): BelongsTo
    {
        return $this->belongsTo(RiesgoAsociado::class, 'riesgo_asociado_id');
    }

    /** ¿Alguien ya trabajó esta acción (responsable, evidencia o validación)? */
    public function fueTrabajada(): bool
    {
        return $this->responsable_id !== null
            || filled($this->evidencia)
            || $this->validada_at !== null
            || $this->estado !== EstadoAccionPlan::Pendiente;
    }

    public function responsableUsuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creador_id');
    }

    public function validadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validada_por');
    }

    /** Acciones aún no validadas cuya fecha ya pasó. */
    public function scopeVencidas(Builder $query): Builder
    {
        return $query->where('estado', '!=', EstadoAccionPlan::Validada)
            ->whereDate('fecha', '<', now());
    }

    /** Acciones aún no validadas cuya fecha vence dentro de los próximos $dias días. */
    public function scopeProximasAVencer(Builder $query, int $dias): Builder
    {
        return $query->where('estado', '!=', EstadoAccionPlan::Validada)
            ->whereBetween('fecha', [now()->startOfDay(), now()->addDays($dias)->endOfDay()]);
    }
}
