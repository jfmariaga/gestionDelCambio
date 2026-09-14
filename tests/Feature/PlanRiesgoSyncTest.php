<?php

namespace Tests\Feature;

use App\Domain\GestionCambio\RegistrarRespuesta;
use App\Domain\GestionCambio\SincronizadorPlanRiesgos;
use App\Enums\ValorRespuesta;
use App\Models\PreguntaClave;
use App\Models\RiesgoPredeterminado;
use App\Models\SolicitudCambio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanRiesgoSyncTest extends TestCase
{
    use RefreshDatabase;

    private function riesgoDe(SolicitudCambio $s)
    {
        $riesgo = RiesgoPredeterminado::factory()->create();
        $pregunta = PreguntaClave::factory()->conRiesgo($riesgo)->create();
        app(RegistrarRespuesta::class)($s, $pregunta, ValorRespuesta::Si);

        return $s->riesgosAsociados()->first();
    }

    public function test_riesgo_medio_genera_accion_en_el_plan(): void
    {
        $solicitud = SolicitudCambio::factory()->create();
        $fila = $this->riesgoDe($solicitud);
        $fila->update(['probabilidad' => 5, 'impacto' => 7, 'nr' => 35, 'nivel' => 'Medio']);

        app(SincronizadorPlanRiesgos::class)->sincronizar($solicitud->fresh());

        $this->assertDatabaseHas('acciones_plan', [
            'solicitud_cambio_id' => $solicitud->id,
            'riesgo_asociado_id' => $fila->id,
        ]);
        $this->assertSame(1, $solicitud->accionesPlan()->count());
    }

    public function test_riesgo_bajo_no_genera_accion(): void
    {
        $solicitud = SolicitudCambio::factory()->create();
        $fila = $this->riesgoDe($solicitud);
        $fila->update(['probabilidad' => 3, 'impacto' => 3, 'nr' => 9, 'nivel' => 'Bajo']);

        app(SincronizadorPlanRiesgos::class)->sincronizar($solicitud->fresh());

        $this->assertSame(0, $solicitud->accionesPlan()->count());
    }

    public function test_subir_de_bajo_a_alto_crea_la_accion_y_bajar_la_elimina_si_no_fue_trabajada(): void
    {
        $solicitud = SolicitudCambio::factory()->create();
        $fila = $this->riesgoDe($solicitud);

        $fila->update(['probabilidad' => 10, 'impacto' => 7, 'nr' => 70, 'nivel' => 'Alto']);
        app(SincronizadorPlanRiesgos::class)->sincronizar($solicitud->fresh());
        $this->assertSame(1, $solicitud->accionesPlan()->count());

        $fila->update(['probabilidad' => 3, 'impacto' => 3, 'nr' => 9, 'nivel' => 'Bajo']);
        app(SincronizadorPlanRiesgos::class)->sincronizar($solicitud->fresh());
        $this->assertSame(0, $solicitud->accionesPlan()->count());
    }
}
