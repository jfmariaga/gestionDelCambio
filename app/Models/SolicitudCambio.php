<?php

namespace App\Models;

use App\Enums\Clasificacion;
use App\Enums\EstadoSolicitud;
use App\Enums\TipoCambio;
use Database\Factories\SolicitudCambioFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SolicitudCambio extends Model
{
    /** @use HasFactory<SolicitudCambioFactory> */
    use HasFactory;

    protected $table = 'solicitudes_cambio';

    protected $fillable = [
        'consecutivo',
        'planta_id',
        'fecha',
        'nombre_cambio',
        'solicitante_cargo',
        'area_proceso',
        'tipo_cambio',
        'clasificacion_manual',
        'fecha_requerida',
        'costo_estimado',
        'requiere_comite',
        'aprobador_id',
        'aprobador_asignado_id',
        'aprobador_override',
        'situacion_actual',
        'que_cambiara',
        'resultado_esperado',
        'estado',
        'snapshot_at',
        'aprobado_por',
        'aprobado_at',
        'decision_comentario',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'fecha' => 'date',
        'fecha_requerida' => 'date',
        'costo_estimado' => 'decimal:2',
        'requiere_comite' => 'boolean',
        'aprobador_override' => 'boolean',
        'tipo_cambio' => TipoCambio::class,
        'estado' => EstadoSolicitud::class,
        'snapshot_at' => 'datetime',
        'aprobado_at' => 'datetime',
    ];

    public function respuestas(): HasMany
    {
        return $this->hasMany(RespuestaPregunta::class);
    }

    public function riesgosAsociados(): HasMany
    {
        return $this->hasMany(RiesgoAsociado::class);
    }

    /** Decisiones de las dos compuertas de aprobación (etapas "inicial" y "cierre"). */
    public function aprobaciones(): HasMany
    {
        return $this->hasMany(AprobacionSolicitud::class, 'solicitud_cambio_id');
    }

    /** @return Collection<int,AprobacionSolicitud> */
    public function aprobacionesDe(string $etapa)
    {
        return $this->aprobaciones()->where('etapa', $etapa)->get();
    }

    public function aprobacionPendiente(User $user, string $etapa): ?AprobacionSolicitud
    {
        return $this->aprobaciones()
            ->where('etapa', $etapa)
            ->where('user_id', $user->id)
            ->whereIn('decision', [AprobacionSolicitud::PENDIENTE, AprobacionSolicitud::DEVUELTO])
            ->first();
    }

    /** ¿Todos los aprobadores de la etapa ya decidieron "aprobado"? */
    public function etapaAprobada(string $etapa): bool
    {
        $filas = $this->aprobacionesDe($etapa);

        return $filas->isNotEmpty()
            && $filas->every(fn (AprobacionSolicitud $a) => $a->decision === AprobacionSolicitud::APROBADO);
    }

    public function etapaTieneDevolucion(string $etapa): bool
    {
        return $this->aprobacionesDe($etapa)
            ->contains(fn (AprobacionSolicitud $a) => $a->decision === AprobacionSolicitud::DEVUELTO);
    }

    public function evaluacion(): HasOne
    {
        return $this->hasOne(EvaluacionCambio::class);
    }

    public function accionesPlan(): HasMany
    {
        return $this->hasMany(AccionPlan::class)->orderBy('numero');
    }

    public function criteriosCierre(): HasMany
    {
        return $this->hasMany(CriterioCierre::class);
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(BitacoraEvento::class)->latest();
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Responsable / líder del cambio: la persona que creó la solicitud queda ligada a ella
     * como responsable y no puede reasignarse.
     */
    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function aprobador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobado_por');
    }

    /** Aprobador designado (histórico: se elegía a mano al diligenciar la solicitud). */
    public function aprobadorManual(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobador_id');
    }

    /**
     * Aprobador principal asignado automáticamente según la clasificación del cambio
     * (Revisión R2 / US8). Para "Crítico" hay varios: ver aprobadores().
     */
    public function aprobadorAsignado(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobador_asignado_id');
    }

    /** Todos los aprobadores requeridos (Crítico = Gerencia General + Comité). */
    public function aprobadores(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'solicitud_aprobadores');
    }

    public function planta(): BelongsTo
    {
        return $this->belongsTo(Planta::class);
    }

    /** Clasificación vigente derivada de la rúbrica de evaluación (no se persiste). */
    public function clasificacionVigente(): ?Clasificacion
    {
        return $this->evaluacion?->clasificacion;
    }

    /** ¿Están respondidas todas las preguntas clave activas del cuestionario? */
    public function cuestionarioCompleto(): bool
    {
        $total = PreguntaClave::activas()->count();

        return $total > 0 && $this->respuestas()->count() >= $total;
    }

    public function estaCongelada(): bool
    {
        return $this->snapshot_at !== null;
    }

    public function permiteSincronizacion(): bool
    {
        return $this->estado->permiteSincronizacion() && ! $this->estaCongelada();
    }
}
