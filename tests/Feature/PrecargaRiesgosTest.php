<?php

namespace Tests\Feature;

use App\Domain\GestionCambio\RegistrarRespuesta;
use App\Domain\GestionCambio\SincronizadorRiesgos;
use App\Enums\ValorRespuesta;
use App\Models\PreguntaClave;
use App\Models\Proceso;
use App\Models\RiesgoPredeterminado;
use App\Models\SolicitudCambio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrecargaRiesgosTest extends TestCase
{
    use RefreshDatabase;

    private function responder(SolicitudCambio $s, PreguntaClave $p, ValorRespuesta $v): void
    {
        app(RegistrarRespuesta::class)($s, $p, $v);
    }

    public function test_marcar_si_agrega_el_riesgo_predeterminado_con_proceso_y_texto(): void
    {
        $solicitud = SolicitudCambio::factory()->create();
        $proceso = Proceso::factory()->create(['nombre' => 'Seguridad Alimentaria / BPM']);
        $riesgo = RiesgoPredeterminado::factory()->for($proceso)->create([
            'texto' => 'Residuos químicos o contaminación cruzada tras el CIP.',
        ]);
        $pregunta = PreguntaClave::factory()->conRiesgo($riesgo)->create();

        $this->responder($solicitud, $pregunta, ValorRespuesta::Si);

        $this->assertDatabaseCount('riesgos_asociados', 1);
        $fila = $solicitud->riesgosAsociados()->first();
        $this->assertSame('Seguridad Alimentaria / BPM', $fila->proceso_nombre);
        $this->assertSame('Residuos químicos o contaminación cruzada tras el CIP.', $fila->riesgo_texto);
        $this->assertNull($fila->nr);
        $this->assertNull($fila->nivel);
        $this->assertEqualsCanonicalizing([$pregunta->id], $fila->preguntas->pluck('id')->all());
    }

    public function test_pregunta_si_sin_riesgo_no_crea_fila_pero_se_reporta(): void
    {
        $solicitud = SolicitudCambio::factory()->create();
        $pregunta = PreguntaClave::factory()->sinRiesgo()->create();

        $this->responder($solicitud, $pregunta, ValorRespuesta::Si);

        $this->assertDatabaseCount('riesgos_asociados', 0);

        $advertencias = app(SincronizadorRiesgos::class)->preguntasSiSinRiesgo($solicitud);
        $this->assertEqualsCanonicalizing([$pregunta->id], $advertencias->pluck('id')->all());
    }

    public function test_dos_preguntas_con_el_mismo_riesgo_se_consolidan_en_una_fila(): void
    {
        $solicitud = SolicitudCambio::factory()->create();
        $riesgo = RiesgoPredeterminado::factory()->create();
        $p1 = PreguntaClave::factory()->conRiesgo($riesgo)->create();
        $p2 = PreguntaClave::factory()->conRiesgo($riesgo)->create();

        $this->responder($solicitud, $p1, ValorRespuesta::Si);
        $this->responder($solicitud, $p2, ValorRespuesta::Si);

        $this->assertDatabaseCount('riesgos_asociados', 1);
        $fila = $solicitud->riesgosAsociados()->first();
        $this->assertEqualsCanonicalizing([$p1->id, $p2->id], $fila->preguntas->pluck('id')->all());
    }

    public function test_quitar_una_de_dos_preguntas_no_elimina_el_riesgo_consolidado(): void
    {
        $solicitud = SolicitudCambio::factory()->create();
        $riesgo = RiesgoPredeterminado::factory()->create();
        $p1 = PreguntaClave::factory()->conRiesgo($riesgo)->create();
        $p2 = PreguntaClave::factory()->conRiesgo($riesgo)->create();

        $this->responder($solicitud, $p1, ValorRespuesta::Si);
        $this->responder($solicitud, $p2, ValorRespuesta::Si);
        $this->responder($solicitud, $p1, ValorRespuesta::No);

        $this->assertDatabaseCount('riesgos_asociados', 1);
        $fila = $solicitud->riesgosAsociados()->first();
        $this->assertEqualsCanonicalizing([$p2->id], $fila->preguntas->pluck('id')->all());
    }

    public function test_sincronizar_todo_es_idempotente(): void
    {
        $solicitud = SolicitudCambio::factory()->create();
        $preguntas = PreguntaClave::factory()->count(3)->create();
        foreach ($preguntas as $p) {
            $this->responder($solicitud, $p, ValorRespuesta::Si);
        }

        app(SincronizadorRiesgos::class)->sincronizarTodo($solicitud->fresh());
        app(SincronizadorRiesgos::class)->sincronizarTodo($solicitud->fresh());

        $this->assertDatabaseCount('riesgos_asociados', 3);
        $this->assertDatabaseCount('riesgo_asociado_pregunta', 3);
    }
}
