<?php

namespace Tests\Feature;

use App\Domain\GestionCambio\CongeladorSolicitud;
use App\Domain\GestionCambio\RegistrarRespuesta;
use App\Domain\GestionCambio\SincronizadorRiesgos;
use App\Enums\EstadoSolicitud;
use App\Enums\ValorRespuesta;
use App\Models\PreguntaClave;
use App\Models\RiesgoPredeterminado;
use App\Models\SolicitudCambio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CongelamientoAprobacionTest extends TestCase
{
    use RefreshDatabase;

    public function test_enviar_a_aprobacion_congela_y_deja_bitacora(): void
    {
        $solicitud = SolicitudCambio::factory()->create();

        app(CongeladorSolicitud::class)->enviarAAprobacion($solicitud, null);

        $solicitud->refresh();
        $this->assertSame(EstadoSolicitud::EnEvaluacion, $solicitud->estado);
        $this->assertNotNull($solicitud->snapshot_at);
        $this->assertDatabaseHas('bitacora_eventos', [
            'solicitud_cambio_id' => $solicitud->id,
            'evento' => 'enviada',
        ]);
    }

    public function test_editar_el_catalogo_tras_enviar_no_altera_la_solicitud(): void
    {
        $solicitud = SolicitudCambio::factory()->create();
        $riesgo = RiesgoPredeterminado::factory()->create(['texto' => 'Riesgo original del catálogo']);
        $pregunta = PreguntaClave::factory()->create([
            'texto' => 'Texto original de la pregunta',
            'riesgo_predeterminado_id' => $riesgo->id,
        ]);
        app(RegistrarRespuesta::class)($solicitud, $pregunta, ValorRespuesta::Si);

        app(CongeladorSolicitud::class)->enviarAAprobacion($solicitud, null);

        // Cambios posteriores en el catálogo.
        $riesgo->update(['texto' => 'Riesgo MODIFICADO en el catálogo']);
        app(SincronizadorRiesgos::class)->sincronizarTodo($solicitud->fresh());

        $fila = $solicitud->riesgosAsociados()->first();
        $this->assertSame('Riesgo original del catálogo', $fila->riesgo_texto);
        $this->assertSame(1, $solicitud->riesgosAsociados()->count());
    }

    public function test_no_se_puede_enviar_una_solicitud_que_no_esta_en_borrador(): void
    {
        $solicitud = SolicitudCambio::factory()->enAprobacion()->create();

        $this->expectException(\RuntimeException::class);
        app(CongeladorSolicitud::class)->enviarAAprobacion($solicitud, null);
    }
}
