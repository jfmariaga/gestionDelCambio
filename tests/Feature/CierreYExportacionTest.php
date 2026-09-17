<?php

namespace Tests\Feature;

use App\Domain\GestionCambio\RegistrarRespuesta;
use App\Domain\GestionCambio\TransicionSolicitud;
use App\Enums\EstadoSolicitud;
use App\Enums\ValorRespuesta;
use App\Livewire\Solicitud\AprobacionCierre;
use App\Models\AccionPlan;
use App\Models\AprobacionSolicitud;
use App\Models\PreguntaClave;
use App\Models\RiesgoPredeterminado;
use App\Models\SolicitudCambio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CierreYExportacionTest extends TestCase
{
    use RefreshDatabase;

    public function test_bloqueo_de_cierre_por_riesgo_medio_alto_sin_accion(): void
    {
        $solicitud = SolicitudCambio::factory()->create();
        $riesgo = RiesgoPredeterminado::factory()->create();
        $pregunta = PreguntaClave::factory()->conRiesgo($riesgo)->create();
        app(RegistrarRespuesta::class)($solicitud, $pregunta, ValorRespuesta::Si);

        // Riesgo Alto pero sin acción del plan vinculada (se fuerza el nivel sin sincronizar).
        $solicitud->riesgosAsociados()->first()->forceFill([
            'probabilidad' => 10, 'impacto' => 7, 'nr' => 70, 'nivel' => 'Alto',
        ])->save();

        $bloqueos = app(TransicionSolicitud::class)->bloqueosDeCierre($solicitud->fresh());

        $this->assertNotEmpty($bloqueos);
    }

    public function test_bloqueo_de_cierre_por_accion_del_plan_sin_validar(): void
    {
        $solicitud = SolicitudCambio::factory()->create();
        AccionPlan::create([
            'solicitud_cambio_id' => $solicitud->id,
            'numero' => 1, 'descripcion' => 'Pendiente', 'estado' => 'En curso',
        ]);

        $bloqueos = app(TransicionSolicitud::class)->bloqueosDeCierre($solicitud->fresh());

        $this->assertNotEmpty($bloqueos);
    }

    public function test_cerrar_procede_cuando_no_hay_bloqueos(): void
    {
        $aprobador = User::factory()->create();

        $solicitud = SolicitudCambio::factory()->create(['estado' => EstadoSolicitud::EnVerificacion]);
        AprobacionSolicitud::create([
            'solicitud_cambio_id' => $solicitud->id,
            'user_id' => $aprobador->id,
            'etapa' => AprobacionSolicitud::ETAPA_CIERRE,
            'decision' => AprobacionSolicitud::APROBADO,
            'decidido_at' => now(),
        ]);

        $bloqueos = app(TransicionSolicitud::class)->cerrar($solicitud->fresh(), $aprobador->id);

        $this->assertSame([], $bloqueos);
        $this->assertSame(EstadoSolicitud::Cerrado, $solicitud->fresh()->estado);
        $this->assertDatabaseHas('bitacora_eventos', ['solicitud_cambio_id' => $solicitud->id, 'evento' => 'cerrada']);
    }

    public function test_decision_inicial_aprobar_y_devolver(): void
    {
        $transicion = app(TransicionSolicitud::class);

        $ap1 = User::factory()->create();
        $s1 = SolicitudCambio::factory()->enEvaluacion()->create();
        AprobacionSolicitud::create([
            'solicitud_cambio_id' => $s1->id, 'user_id' => $ap1->id,
            'etapa' => AprobacionSolicitud::ETAPA_INICIAL, 'decision' => AprobacionSolicitud::PENDIENTE,
        ]);
        $transicion->registrarDecisionInicial($s1->fresh(), $ap1, 'aprobar', 'ok');
        $this->assertSame(EstadoSolicitud::Aprobado, $s1->fresh()->estado);

        $ap2 = User::factory()->create();
        $s2 = SolicitudCambio::factory()->enEvaluacion()->create();
        AprobacionSolicitud::create([
            'solicitud_cambio_id' => $s2->id, 'user_id' => $ap2->id,
            'etapa' => AprobacionSolicitud::ETAPA_INICIAL, 'decision' => AprobacionSolicitud::PENDIENTE,
        ]);
        $transicion->registrarDecisionInicial($s2->fresh(), $ap2, 'devolver', 'faltan evidencias');
        $this->assertSame(EstadoSolicitud::Solicitado, $s2->fresh()->estado);
        $this->assertNull($s2->fresh()->snapshot_at);
    }

    public function test_comprobar_cierre_muestra_confirmacion_cuando_no_hay_bloqueos(): void
    {
        $this->actuarComo('administrador');
        $solicitud = SolicitudCambio::factory()->create();

        Livewire::test(AprobacionCierre::class, ['solicitud' => $solicitud])
            ->call('comprobarCierre')
            ->assertSet('bloqueos', [])
            ->assertSet('comprobado', true)
            ->assertSee('Sin bloqueos: la solicitud está lista para cerrarse.')
            ->assertDispatched('toast');
    }

    public function test_comprobar_cierre_lista_los_bloqueos_cuando_los_hay(): void
    {
        $this->actuarComo('administrador');
        $solicitud = SolicitudCambio::factory()->create();
        AccionPlan::create([
            'solicitud_cambio_id' => $solicitud->id,
            'numero' => 1, 'descripcion' => 'Pendiente', 'estado' => 'En curso',
        ]);

        Livewire::test(AprobacionCierre::class, ['solicitud' => $solicitud])
            ->call('comprobarCierre')
            ->assertSet('comprobado', true)
            ->assertDontSee('Sin bloqueos: la solicitud está lista para cerrarse.');

        $this->assertNotEmpty(app(TransicionSolicitud::class)->bloqueosDeCierre($solicitud->fresh()));
    }

    public function test_el_resumen_muestra_el_historial_de_bitacora(): void
    {
        $lider = $this->actuarComo('solicitante');
        $solicitud = SolicitudCambio::factory()->enEvaluacion()->create(['created_by' => $lider->id]);

        \App\Models\BitacoraEvento::create([
            'solicitud_cambio_id' => $solicitud->id,
            'user_id' => $lider->id,
            'evento' => 'devuelta',
            'comentario' => 'Faltan evidencias',
        ]);

        $this->get(route('solicitudes.show', $solicitud))
            ->assertOk()
            ->assertSee('Creada')
            ->assertSee('Devuelta en aprobación inicial')
            ->assertSee('Faltan evidencias')
            ->assertSee($lider->name);
    }

    public function test_el_resumen_muestra_evaluacion_cuestionario_plan_y_cierre(): void
    {
        $this->actuarComo('administrador');

        $solicitud = SolicitudCambio::factory()->create();
        $criterio = \App\Models\CriterioRubrica::factory()->create(['nombre' => 'Criterio de prueba']);
        app(\App\Domain\GestionCambio\EvaluadorRubrica::class)->calificar($solicitud, $criterio->id, 2);

        $pregunta = PreguntaClave::factory()->create(['texto' => 'Pregunta de prueba']);
        app(RegistrarRespuesta::class)($solicitud, $pregunta, ValorRespuesta::Si);

        $solicitud->accionesPlan()->create(['numero' => 1, 'descripcion' => 'Acción de prueba', 'estado' => 'Pendiente']);
        $solicitud->update(['nota_cierre' => 'Nota de cierre de prueba']);

        $this->get(route('solicitudes.show', $solicitud))
            ->assertOk()
            ->assertSee('Evaluación y clasificación del cambio')
            ->assertSee('Criterio de prueba')
            ->assertSee('Pregunta de prueba')
            ->assertSee('Acción de prueba')
            ->assertSee('Nota de cierre de prueba');
    }

    public function test_exportar_genera_pdf(): void
    {
        $this->actuarComo('administrador');
        $solicitud = SolicitudCambio::factory()->create();

        $response = $this->get(route('solicitudes.exportar', $solicitud));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }
}
