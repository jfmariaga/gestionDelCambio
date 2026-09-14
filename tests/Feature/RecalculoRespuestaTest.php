<?php

namespace Tests\Feature;

use App\Domain\GestionCambio\RegistrarRespuesta;
use App\Enums\EstadoFila;
use App\Enums\ValorRespuesta;
use App\Models\PreguntaClave;
use App\Models\SolicitudCambio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecalculoRespuestaTest extends TestCase
{
    use RefreshDatabase;

    private function responder(SolicitudCambio $s, PreguntaClave $p, ValorRespuesta $v): void
    {
        app(RegistrarRespuesta::class)($s, $p, $v);
    }

    public function test_si_a_no_elimina_riesgo_no_calificado(): void
    {
        $solicitud = SolicitudCambio::factory()->create();
        $pregunta = PreguntaClave::factory()->create();

        $this->responder($solicitud, $pregunta, ValorRespuesta::Si);
        $this->assertDatabaseCount('riesgos_asociados', 1);

        $this->responder($solicitud, $pregunta, ValorRespuesta::No);
        $this->assertDatabaseCount('riesgos_asociados', 0);
    }

    public function test_si_a_no_conserva_riesgo_calificado_como_huerfano(): void
    {
        $solicitud = SolicitudCambio::factory()->create();
        $pregunta = PreguntaClave::factory()->create();

        $this->responder($solicitud, $pregunta, ValorRespuesta::Si);
        $solicitud->riesgosAsociados()->first()->update(['probabilidad' => 4, 'impacto' => 9, 'nr' => 36, 'nivel' => 'Medio']);

        $this->responder($solicitud, $pregunta, ValorRespuesta::No);

        $this->assertDatabaseCount('riesgos_asociados', 1);
        $this->assertSame(EstadoFila::Huerfana, $solicitud->riesgosAsociados()->first()->estado);
    }
}
