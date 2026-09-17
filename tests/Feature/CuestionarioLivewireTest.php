<?php

namespace Tests\Feature;

use App\Domain\GestionCambio\EvaluadorRubrica;
use App\Domain\GestionCambio\RegistrarRespuesta;
use App\Enums\EstadoFila;
use App\Enums\NivelRiesgo;
use App\Enums\ValorRespuesta;
use App\Livewire\Solicitud\Cuestionario;
use App\Livewire\Solicitud\PlanAccion;
use App\Livewire\Solicitud\RiesgosAsociados;
use App\Models\CriterioRubrica;
use App\Models\PreguntaClave;
use App\Models\Proceso;
use App\Models\SolicitudCambio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CuestionarioLivewireTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actuarComo('administrador');
    }

    public function test_responder_si_precarga_consideracion_y_riesgo_en_vivo(): void
    {
        $solicitud = SolicitudCambio::factory()->create();
        $proceso = Proceso::factory()->create(['nombre' => 'Producción']);
        $pregunta = PreguntaClave::factory()->paraProceso($proceso)->create();

        Livewire::test(Cuestionario::class, ['solicitud' => $solicitud])
            ->call('responder', $pregunta->id, 'SI')
            ->assertDispatched('secciones-actualizadas');

        $this->assertDatabaseHas('riesgos_asociados', [
            'solicitud_cambio_id' => $solicitud->id,
            'riesgo_predeterminado_id' => $pregunta->riesgo_predeterminado_id,
            'proceso_nombre' => 'Producción',
        ]);
    }

    public function test_responder_la_ultima_pregunta_redirige_para_habilitar_riesgos_sin_recargar(): void
    {
        $solicitud = SolicitudCambio::factory()->create();
        [$pregunta1, $pregunta2] = PreguntaClave::factory()->count(2)->create();

        $componente = Livewire::test(Cuestionario::class, ['solicitud' => $solicitud])
            ->call('responder', $pregunta1->id, 'NO');

        $componente->assertNoRedirect();

        $componente->call('responder', $pregunta2->id, 'NO')
            ->assertRedirect(route('solicitudes.edit', $solicitud));

        $this->assertTrue($solicitud->fresh()->cuestionarioCompleto());
    }

    public function test_responder_de_nuevo_con_el_cuestionario_ya_completo_no_redirige(): void
    {
        $solicitud = SolicitudCambio::factory()->create();
        $pregunta = PreguntaClave::factory()->create();
        app(RegistrarRespuesta::class)($solicitud, $pregunta, ValorRespuesta::No);

        Livewire::test(Cuestionario::class, ['solicitud' => $solicitud])
            ->call('responder', $pregunta->id, 'SI')
            ->assertNoRedirect();
    }

    public function test_responder_valor_invalido_aborta(): void
    {
        $solicitud = SolicitudCambio::factory()->create();
        $pregunta = PreguntaClave::factory()->create();

        Livewire::test(Cuestionario::class, ['solicitud' => $solicitud])
            ->call('responder', $pregunta->id, 'TAL_VEZ')
            ->assertStatus(422);
    }

    public function test_calificar_riesgo_calcula_nr_y_nivel(): void
    {
        $solicitud = SolicitudCambio::factory()->create();
        $pregunta = PreguntaClave::factory()->create();
        app(RegistrarRespuesta::class)($solicitud, $pregunta, ValorRespuesta::Si);
        $fila = $solicitud->riesgosAsociados()->first();

        Livewire::test(RiesgosAsociados::class, ['solicitud' => $solicitud])
            ->set("edicion.{$fila->id}.probabilidad", 5)
            ->set("edicion.{$fila->id}.impacto", 7)
            ->call('guardarFila', $fila->id);

        $fila->refresh();
        $this->assertSame(35, $fila->nr);
        $this->assertSame(NivelRiesgo::Medio, $fila->nivel);
    }

    public function test_montar_el_componente_precarga_edicion_con_los_valores_guardados(): void
    {
        $solicitud = SolicitudCambio::factory()->create();
        $pregunta = PreguntaClave::factory()->create();
        app(RegistrarRespuesta::class)($solicitud, $pregunta, ValorRespuesta::Si);
        $fila = $solicitud->riesgosAsociados()->first();
        $fila->update(['control_existente' => 'Control X', 'probabilidad' => 7, 'impacto' => 7, 'nr' => 49, 'nivel' => NivelRiesgo::Medio]);

        // Antes de este fix, $edicion nacía vacío: al re-renderizar (p. ej. tras guardar OTRA
        // fila) Livewire pisaba estos campos con "" y los guardaba como null en el siguiente save.
        Livewire::test(RiesgosAsociados::class, ['solicitud' => $solicitud])
            ->assertSet("edicion.{$fila->id}.control_existente", 'Control X')
            ->assertSet("edicion.{$fila->id}.probabilidad", 7)
            ->assertSet("edicion.{$fila->id}.impacto", 7);
    }

    public function test_guardar_sin_tocar_los_campos_no_borra_lo_ya_calificado(): void
    {
        $solicitud = SolicitudCambio::factory()->create();
        $pregunta = PreguntaClave::factory()->create();
        app(RegistrarRespuesta::class)($solicitud, $pregunta, ValorRespuesta::Si);
        $fila = $solicitud->riesgosAsociados()->first();
        $fila->update(['control_existente' => 'Control X', 'probabilidad' => 7, 'impacto' => 7, 'nr' => 49, 'nivel' => NivelRiesgo::Medio]);

        Livewire::test(RiesgosAsociados::class, ['solicitud' => $solicitud])
            ->call('guardarFila', $fila->id);

        $fila->refresh();
        $this->assertSame('Control X', $fila->control_existente);
        $this->assertSame(7, $fila->probabilidad);
        $this->assertSame(7, $fila->impacto);
        $this->assertSame(49, $fila->nr);
    }

    public function test_calificar_riesgo_medio_o_alto_refresca_el_plan_de_accion_sin_recargar(): void
    {
        $solicitud = SolicitudCambio::factory()->create();
        $pregunta = PreguntaClave::factory()->create();
        app(RegistrarRespuesta::class)($solicitud, $pregunta, ValorRespuesta::Si);
        $fila = $solicitud->riesgosAsociados()->first();

        // El componente de Plan de acción ya está montado (como en la página real) ANTES de que
        // el riesgo se califique como Medio/Alto: no debe verse la acción todavía.
        $plan = Livewire::test(PlanAccion::class, ['solicitud' => $solicitud]);
        $plan->assertDontSee($fila->riesgo_texto);

        Livewire::test(RiesgosAsociados::class, ['solicitud' => $solicitud])
            ->set("edicion.{$fila->id}.probabilidad", 10)
            ->set("edicion.{$fila->id}.impacto", 10)
            ->call('guardarFila', $fila->id)
            ->assertDispatched('secciones-actualizadas');

        $this->assertDatabaseHas('acciones_plan', [
            'solicitud_cambio_id' => $solicitud->id,
            'riesgo_asociado_id' => $fila->id,
        ]);

        // El mismo componente de Plan de acción, al recibir el evento global, refresca su lista.
        $plan->dispatch('secciones-actualizadas')
            ->assertSee($fila->riesgo_texto);
    }

    public function test_calificar_riesgo_fuera_de_escala_falla_validacion(): void
    {
        $solicitud = SolicitudCambio::factory()->create();
        $pregunta = PreguntaClave::factory()->create();
        app(RegistrarRespuesta::class)($solicitud, $pregunta, ValorRespuesta::Si);
        $fila = $solicitud->riesgosAsociados()->first();

        Livewire::test(RiesgosAsociados::class, ['solicitud' => $solicitud])
            ->set("edicion.{$fila->id}.probabilidad", 15)
            ->set("edicion.{$fila->id}.impacto", 3)
            ->call('guardarFila', $fila->id)
            ->assertHasErrors("edicion.{$fila->id}.probabilidad");

        $this->assertNull($fila->fresh()->nr);
    }

    public function test_no_se_puede_editar_un_riesgo_una_vez_congelada_la_solicitud(): void
    {
        $solicitud = SolicitudCambio::factory()->create();
        $pregunta = PreguntaClave::factory()->create();
        app(RegistrarRespuesta::class)($solicitud, $pregunta, ValorRespuesta::Si);
        $fila = $solicitud->riesgosAsociados()->first();
        $solicitud->forceFill(['snapshot_at' => now()])->save();

        Livewire::test(RiesgosAsociados::class, ['solicitud' => $solicitud])
            ->set("edicion.{$fila->id}.probabilidad", 10)
            ->set("edicion.{$fila->id}.impacto", 10)
            ->call('guardarFila', $fila->id)
            ->assertDispatched('solicitud-bloqueada');

        $this->assertNull($fila->fresh()->nr);
    }

    public function test_riesgo_huerfano_se_puede_conservar_o_eliminar(): void
    {
        $solicitud = SolicitudCambio::factory()->create();
        $pregunta = PreguntaClave::factory()->create();
        $registrar = app(RegistrarRespuesta::class);
        $registrar($solicitud, $pregunta, ValorRespuesta::Si);
        $solicitud->riesgosAsociados()->first()->update(['probabilidad' => 5, 'impacto' => 7, 'editado_manualmente' => true]);
        $registrar($solicitud, $pregunta, ValorRespuesta::No);
        $fila = $solicitud->riesgosAsociados()->first();
        $this->assertSame(EstadoFila::Huerfana, $fila->estado);

        Livewire::test(RiesgosAsociados::class, ['solicitud' => $solicitud])
            ->call('confirmarEliminar', $fila->id, false);
        $this->assertDatabaseCount('riesgos_asociados', 1);

        Livewire::test(RiesgosAsociados::class, ['solicitud' => $solicitud])
            ->call('confirmarEliminar', $fila->id, true);
        $this->assertDatabaseCount('riesgos_asociados', 0);
    }

    public function test_pagina_de_edicion_carga_cuestionario_pero_no_riesgos_sin_responder(): void
    {
        $solicitud = SolicitudCambio::factory()->create();

        // R2/US7: cuestionario se habilita si la clasificación es Mayor o Crítico.
        $criterios = CriterioRubrica::factory()->count(11)->create();
        $evaluador = app(EvaluadorRubrica::class);
        $valores = array_merge(array_fill(0, 9, 2), [1, 1]);
        foreach ($criterios as $i => $criterio) {
            $evaluador->calificar($solicitud, $criterio->id, $valores[$i]);
        }

        // Riesgos, plan y cierre solo se habilitan una vez el cuestionario esté 100% respondido.
        $this->get(route('solicitudes.edit', $solicitud))
            ->assertOk()
            ->assertSeeLivewire(Cuestionario::class)
            ->assertDontSeeLivewire(RiesgosAsociados::class);
    }

    public function test_pagina_de_edicion_carga_riesgos_una_vez_completo_el_cuestionario(): void
    {
        $solicitud = SolicitudCambio::factory()->create();

        $criterios = CriterioRubrica::factory()->count(11)->create();
        $evaluador = app(EvaluadorRubrica::class);
        $valores = array_merge(array_fill(0, 9, 2), [1, 1]);
        foreach ($criterios as $i => $criterio) {
            $evaluador->calificar($solicitud, $criterio->id, $valores[$i]);
        }

        $pregunta = PreguntaClave::factory()->create();
        app(RegistrarRespuesta::class)($solicitud, $pregunta, ValorRespuesta::No);

        $this->get(route('solicitudes.edit', $solicitud))
            ->assertOk()
            ->assertSeeLivewire(Cuestionario::class)
            ->assertSeeLivewire(RiesgosAsociados::class);
    }
}
