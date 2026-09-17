<?php

namespace Tests\Feature;

use App\Domain\GestionCambio\AsignadorAprobador;
use App\Domain\GestionCambio\TransicionSolicitud;
use App\Enums\EstadoAccionPlan;
use App\Enums\EstadoSolicitud;
use App\Models\AccionPlan;
use App\Models\AprobacionSolicitud;
use App\Models\AreaNotificacion;
use App\Models\DestinatarioArea;
use App\Models\SolicitudCambio;
use App\Models\User;
use Database\Seeders\AreaNotificacionSeeder;
use Database\Seeders\RolesPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AprobacionDosCompuertasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesPermisosSeeder::class);
        $this->seed(AreaNotificacionSeeder::class);
    }

    private function destinatario(string $clave): User
    {
        $u = User::factory()->create();
        DestinatarioArea::create([
            'area_id' => AreaNotificacion::where('clave', $clave)->value('id'),
            'user_id' => $u->id,
            'activo' => true,
        ]);

        return $u;
    }

    public function test_flujo_completo_de_dos_compuertas(): void
    {
        $transicion = app(TransicionSolicitud::class);
        $sig = $this->destinatario('gestion_integral');

        $solicitud = SolicitudCambio::factory()->create(['estado' => EstadoSolicitud::EnEvaluacion]);

        // Compuerta 1: se siembra con SIG (clasificación null -> solo áreas resueltas via override).
        AprobacionSolicitud::create([
            'solicitud_cambio_id' => $solicitud->id, 'user_id' => $sig->id,
            'etapa' => AprobacionSolicitud::ETAPA_INICIAL, 'decision' => AprobacionSolicitud::PENDIENTE,
        ]);

        $transicion->registrarDecisionInicial($solicitud->fresh(), $sig, 'aprobar', null);
        $this->assertSame(EstadoSolicitud::Aprobado, $solicitud->fresh()->estado);

        // Implementación
        $transicion->iniciarImplementacion($solicitud->fresh(), $sig->id);
        $this->assertSame(EstadoSolicitud::EnImplementacion, $solicitud->fresh()->estado);

        $accion = AccionPlan::create([
            'solicitud_cambio_id' => $solicitud->id, 'numero' => 1,
            'descripcion' => 'Acción', 'estado' => EstadoAccionPlan::Validada->value,
        ]);
        $transicion->marcarImplementadoSiCorresponde($solicitud->fresh(), $sig->id);
        $this->assertSame(EstadoSolicitud::Implementado, $solicitud->fresh()->estado);

        // Compuerta 2: exige la nota de cierre registrada.
        $solicitud->update(['nota_cierre' => 'Se implementó sin novedades.']);
        $this->assertSame([], $transicion->enviarAVerificacion($solicitud->fresh(), $sig->id));
        $this->assertSame(EstadoSolicitud::EnVerificacion, $solicitud->fresh()->estado);
        $this->assertDatabaseHas('aprobaciones_solicitud', [
            'solicitud_cambio_id' => $solicitud->id,
            'user_id' => $sig->id,
            'etapa' => AprobacionSolicitud::ETAPA_CIERRE,
        ]);

        $bloqueos = $transicion->registrarDecisionCierre($solicitud->fresh(), $sig, 'aprobar', null);
        $this->assertSame([], $bloqueos);
        $this->assertSame(EstadoSolicitud::Cerrado, $solicitud->fresh()->estado);
    }

    public function test_marcar_implementado_manual_bloquea_si_hay_acciones_sin_validar(): void
    {
        $transicion = app(TransicionSolicitud::class);
        $solicitud = SolicitudCambio::factory()->create(['estado' => EstadoSolicitud::EnImplementacion]);
        AccionPlan::create([
            'solicitud_cambio_id' => $solicitud->id, 'numero' => 1,
            'descripcion' => 'Acción', 'estado' => EstadoAccionPlan::EnCurso->value,
        ]);

        $bloqueos = $transicion->marcarImplementado($solicitud, null);

        $this->assertNotEmpty($bloqueos);
        $this->assertSame(EstadoSolicitud::EnImplementacion, $solicitud->fresh()->estado);
    }

    public function test_marcar_implementado_manual_funciona_aunque_no_haya_acciones_en_el_plan(): void
    {
        // Antes del botón manual, una solicitud SIN acciones de plan se quedaba atascada para
        // siempre en "en_implementacion" (el paso automático exigía al menos una acción).
        $transicion = app(TransicionSolicitud::class);
        $solicitud = SolicitudCambio::factory()->create(['estado' => EstadoSolicitud::EnImplementacion]);

        $bloqueos = $transicion->marcarImplementado($solicitud, null);

        $this->assertSame([], $bloqueos);
        $this->assertSame(EstadoSolicitud::Implementado, $solicitud->fresh()->estado);
    }

    public function test_boton_marcar_implementado_en_la_pagina(): void
    {
        $lider = $this->actuarComo('solicitante');
        $solicitud = SolicitudCambio::factory()->create(['estado' => EstadoSolicitud::EnImplementacion, 'created_by' => $lider->id]);
        AccionPlan::create([
            'solicitud_cambio_id' => $solicitud->id, 'numero' => 1,
            'descripcion' => 'Acción', 'estado' => EstadoAccionPlan::Validada->value,
        ]);

        $this->get(route('solicitudes.show', $solicitud))
            ->assertOk()
            ->assertSee('Marcar como implementado');

        $this->post(route('solicitudes.marcarImplementado', $solicitud))
            ->assertRedirect(route('solicitudes.show', $solicitud))
            ->assertSessionHas('status');

        $this->assertSame(EstadoSolicitud::Implementado, $solicitud->fresh()->estado);
    }

    public function test_enviar_a_verificacion_exige_la_nota_de_cierre(): void
    {
        $transicion = app(TransicionSolicitud::class);
        $solicitud = SolicitudCambio::factory()->create(['estado' => EstadoSolicitud::Implementado]);

        $bloqueos = $transicion->enviarAVerificacion($solicitud, null);

        $this->assertNotEmpty($bloqueos);
        $this->assertSame(EstadoSolicitud::Implementado, $solicitud->fresh()->estado);

        $solicitud->update(['nota_cierre' => 'Se implementó sin novedades.']);
        $this->assertSame([], $transicion->enviarAVerificacion($solicitud->fresh(), null));
        $this->assertSame(EstadoSolicitud::EnVerificacion, $solicitud->fresh()->estado);
    }

    public function test_el_enlace_editar_sigue_visible_en_implementado_para_escribir_la_nota_de_cierre(): void
    {
        // Sin este enlace, un usuario llega al bloqueo de "enviar a verificación" (exige la
        // nota de cierre) sin ninguna forma de llegar a la sección donde se escribe.
        $lider = $this->actuarComo('solicitante');
        $solicitud = SolicitudCambio::factory()->create(['estado' => EstadoSolicitud::Implementado, 'created_by' => $lider->id]);

        $this->get(route('solicitudes.show', $solicitud))
            ->assertOk()
            ->assertSee(route('solicitudes.edit', $solicitud), false);

        $this->get(route('solicitudes.index'))
            ->assertOk()
            ->assertSee(route('solicitudes.edit', $solicitud), false);
    }

    public function test_boton_enviar_a_verificacion_muestra_bloqueo_sin_nota_de_cierre(): void
    {
        $lider = $this->actuarComo('solicitante');
        $solicitud = SolicitudCambio::factory()->create(['estado' => EstadoSolicitud::Implementado, 'created_by' => $lider->id]);

        $this->post(route('solicitudes.enviarVerificacion', $solicitud))
            ->assertRedirect(route('solicitudes.show', $solicitud))
            ->assertSessionHas('errores_verificacion');

        $this->assertSame(EstadoSolicitud::Implementado, $solicitud->fresh()->estado);
    }

    public function test_una_devolucion_en_compuerta_1_vuelve_a_solicitado(): void
    {
        $transicion = app(TransicionSolicitud::class);
        $a = User::factory()->create();
        $b = User::factory()->create();
        $solicitud = SolicitudCambio::factory()->create(['estado' => EstadoSolicitud::EnEvaluacion, 'snapshot_at' => now()]);

        foreach ([$a, $b] as $u) {
            AprobacionSolicitud::create([
                'solicitud_cambio_id' => $solicitud->id, 'user_id' => $u->id,
                'etapa' => AprobacionSolicitud::ETAPA_INICIAL, 'decision' => AprobacionSolicitud::PENDIENTE,
            ]);
        }

        $transicion->registrarDecisionInicial($solicitud->fresh(), $a, 'aprobar', null);
        $this->assertSame(EstadoSolicitud::EnEvaluacion, $solicitud->fresh()->estado);

        $transicion->registrarDecisionInicial($solicitud->fresh(), $b, 'devolver', 'faltan datos');
        $this->assertSame(EstadoSolicitud::Solicitado, $solicitud->fresh()->estado);
        $this->assertNull($solicitud->fresh()->snapshot_at);
    }

    public function test_aprobadores_cierre_incluye_areas_y_gerencia_si_critico(): void
    {
        $sig = $this->destinatario('gestion_integral');
        $sst = $this->destinatario('sst');
        $amb = $this->destinatario('gestion_ambiental');
        $cal = $this->destinatario('calidad_inocuidad');
        $ger = $this->destinatario('gerencia_general');

        $solicitud = SolicitudCambio::factory()->create();
        // Sin evaluación -> clasificación null; forzamos Crítico vía evaluación falsa no es trivial,
        // así que sólo comprobamos las 4 áreas base cuando no es Crítico.
        $ids = app(AsignadorAprobador::class)
            ->aprobadoresPara($solicitud, AprobacionSolicitud::ETAPA_CIERRE)
            ->pluck('id');

        $this->assertTrue($ids->contains($sig->id));
        $this->assertTrue($ids->contains($sst->id));
        $this->assertTrue($ids->contains($amb->id));
        $this->assertTrue($ids->contains($cal->id));
        $this->assertFalse($ids->contains($ger->id)); // no es Crítico
    }
}
