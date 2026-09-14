<?php

namespace Tests\Feature;

use App\Enums\EstadoSolicitud;
use App\Models\SolicitudCambio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SituacionActualObligatoriaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actuarComo('administrador');
    }

    public function test_no_deja_enviar_sin_descripcion_simple(): void
    {
        $solicitud = SolicitudCambio::factory()->create([
            'situacion_actual' => null,
            'que_cambiara' => null,
            'resultado_esperado' => null,
        ]);

        $this->post(route('solicitudes.enviar', $solicitud))
            ->assertSessionHasErrors(['situacion_actual', 'que_cambiara', 'resultado_esperado']);

        $this->assertSame(EstadoSolicitud::Solicitado, $solicitud->fresh()->estado);
    }

    public function test_deja_enviar_con_descripcion_completa(): void
    {
        $solicitud = SolicitudCambio::factory()->create([
            'situacion_actual' => 'Hoy se hace manual.',
            'que_cambiara' => 'Se automatiza el registro.',
            'resultado_esperado' => 'Menos errores y trazabilidad.',
        ]);

        $this->post(route('solicitudes.enviar', $solicitud))->assertRedirect();

        $this->assertSame(EstadoSolicitud::EnEvaluacion, $solicitud->fresh()->estado);
    }
}
