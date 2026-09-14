<?php

namespace App\Domain\GestionCambio;

use App\Enums\ValorRespuesta;
use App\Models\PreguntaClave;
use App\Models\RespuestaPregunta;
use App\Models\SolicitudCambio;
use Illuminate\Support\Facades\DB;

/**
 * Acción de dominio "responder una pregunta del cuestionario": persiste la respuesta y
 * sincroniza la sección de Riesgos asociados (y de ahí el plan de acción).
 * Reutilizable por el componente Livewire y por las pruebas.
 */
final class RegistrarRespuesta
{
    public function __construct(
        private readonly SincronizadorRiesgos $riesgos = new SincronizadorRiesgos,
    ) {}

    public function __invoke(SolicitudCambio $solicitud, PreguntaClave $pregunta, ValorRespuesta $valor): RespuestaPregunta
    {
        return DB::transaction(function () use ($solicitud, $pregunta, $valor) {
            $respuesta = RespuestaPregunta::updateOrCreate(
                ['solicitud_cambio_id' => $solicitud->id, 'pregunta_clave_id' => $pregunta->id],
                ['valor' => $valor, 'respondida_at' => now()],
            );

            $this->riesgos->sincronizarPregunta($solicitud, $pregunta, $valor);
            app(SincronizadorPlanRiesgos::class)->sincronizar($solicitud->fresh());

            return $respuesta;
        });
    }
}
