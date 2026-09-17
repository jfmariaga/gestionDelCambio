<?php

namespace Tests\Feature;

use App\Enums\EstadoAccionPlan;
use App\Enums\EstadoSolicitud;
use App\Livewire\Solicitud\PlanAccion;
use App\Models\AprobacionSolicitud;
use App\Models\Planta;
use App\Models\Proceso;
use App\Models\SolicitudCambio;
use App\Models\User;
use Database\Seeders\RolesPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermisosPorRolTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesPermisosSeeder::class);
    }

    private function usuario(string $rol): User
    {
        $planta = Planta::query()->first() ?? Planta::factory()->create();
        $u = User::factory()->create();
        $u->forceFill(['planta_preferida_id' => $planta->id])->save();
        $u->assignRole($rol);

        return $u;
    }

    public function test_solicitante_crea_y_ve_solo_las_propias(): void
    {
        $solicitante = $this->usuario('solicitante');
        $otro = $this->usuario('solicitante');

        $propia = SolicitudCambio::factory()->create(['created_by' => $solicitante->id]);
        $ajena = SolicitudCambio::factory()->create(['created_by' => $otro->id]);

        $this->actingAs($solicitante);
        $this->get(route('solicitudes.create'))->assertOk();
        $this->get(route('solicitudes.show', $propia))->assertOk();
        $this->get(route('solicitudes.show', $ajena))->assertForbidden();
    }

    public function test_roles_son_aditivos_solicitante_con_consulta_ve_su_borrador(): void
    {
        // Un usuario con varios roles queda con el acceso más permisivo, no el más restrictivo.
        $planta = Planta::query()->first() ?? Planta::factory()->create();
        $user = User::factory()->create();
        $user->forceFill(['planta_preferida_id' => $planta->id])->save();
        $user->assignRole(['solicitante', 'dueno_proceso', 'aprobador', 'consulta']);

        $propia = SolicitudCambio::factory()->create(['created_by' => $user->id]);

        $this->actingAs($user)->withSession(['planta_id' => $planta->id]);
        $this->get(route('solicitudes.show', $propia))->assertOk();
        $this->get(route('solicitudes.edit', $propia))->assertOk();

        $this->post(route('solicitudes.store'), [
            'fecha' => now()->toDateString(),
            'nombre_cambio' => 'Con varios roles',
        ])->assertRedirect();
    }

    public function test_consulta_no_crea_y_solo_ve_aprobadas_o_posteriores(): void
    {
        $consulta = $this->usuario('consulta');
        $borrador = SolicitudCambio::factory()->create();
        $aprobada = SolicitudCambio::factory()->create(['estado' => EstadoSolicitud::Aprobado]);

        $this->actingAs($consulta);
        $this->get(route('solicitudes.create'))->assertForbidden();
        $this->get(route('solicitudes.show', $borrador))->assertForbidden();
        $this->get(route('solicitudes.show', $aprobada))->assertOk();
        $this->get(route('solicitudes.exportar', $aprobada))->assertOk();
    }

    public function test_solo_aprobador_asignado_puede_decidir(): void
    {
        $aprobador = $this->usuario('aprobador');
        $solicitante = $this->usuario('solicitante');
        $solicitud = SolicitudCambio::factory()->enEvaluacion()->create(['created_by' => $solicitante->id]);
        AprobacionSolicitud::create([
            'solicitud_cambio_id' => $solicitud->id,
            'user_id' => $aprobador->id,
            'etapa' => AprobacionSolicitud::ETAPA_INICIAL,
            'decision' => AprobacionSolicitud::PENDIENTE,
        ]);

        $this->actingAs($solicitante)
            ->post(route('solicitudes.decision', $solicitud), ['accion' => 'aprobar'])
            ->assertForbidden();

        $this->actingAs($aprobador)
            ->post(route('solicitudes.decision', $solicitud), ['accion' => 'aprobar'])
            ->assertRedirect();

        $this->assertSame(EstadoSolicitud::Aprobado, $solicitud->fresh()->estado);
    }

    public function test_administrador_sin_fila_de_aprobador_no_puede_decidir(): void
    {
        // decidir/decidirCierre son por-fila: el before() de la policy ya NO se salta esto
        // para el admin, así que sin una aprobación propia pendiente en esa etapa, no puede.
        $admin = $this->usuario('administrador');
        $otroAprobador = $this->usuario('aprobador');
        $solicitud = SolicitudCambio::factory()->enEvaluacion()->create();
        AprobacionSolicitud::create([
            'solicitud_cambio_id' => $solicitud->id,
            'user_id' => $otroAprobador->id,
            'etapa' => AprobacionSolicitud::ETAPA_INICIAL,
            'decision' => AprobacionSolicitud::PENDIENTE,
        ]);

        $this->actingAs($admin)
            ->post(route('solicitudes.decision', $solicitud), ['accion' => 'aprobar'])
            ->assertForbidden();

        $this->assertSame(EstadoSolicitud::EnEvaluacion, $solicitud->fresh()->estado);
    }

    public function test_admin_o_lider_que_ya_decidio_no_ve_ni_puede_reusar_el_boton_aprobar(): void
    {
        $admin = $this->usuario('administrador');
        $otroAprobador = $this->usuario('aprobador');
        $solicitud = SolicitudCambio::factory()->enEvaluacion()->create();
        AprobacionSolicitud::create([
            'solicitud_cambio_id' => $solicitud->id, 'user_id' => $admin->id,
            'etapa' => AprobacionSolicitud::ETAPA_INICIAL, 'decision' => AprobacionSolicitud::APROBADO,
            'decidido_at' => now(),
        ]);
        AprobacionSolicitud::create([
            'solicitud_cambio_id' => $solicitud->id, 'user_id' => $otroAprobador->id,
            'etapa' => AprobacionSolicitud::ETAPA_INICIAL, 'decision' => AprobacionSolicitud::PENDIENTE,
        ]);

        $this->actingAs($admin)
            ->get(route('solicitudes.show', $solicitud))
            ->assertOk()
            ->assertDontSee('Aprobar')
            ->assertSee('Esperando la decisión de los aprobadores asignados a esta etapa.');

        $this->actingAs($admin)
            ->post(route('solicitudes.decision', $solicitud), ['accion' => 'aprobar'])
            ->assertForbidden();
    }

    public function test_administrador_pasa_todos_los_gates(): void
    {
        $admin = $this->usuario('administrador');
        $solicitud = SolicitudCambio::factory()->create();

        $this->actingAs($admin);
        $this->get(route('solicitudes.index'))->assertOk();
        $this->get(route('solicitudes.show', $solicitud))->assertOk();
        $this->get(route('solicitudes.edit', $solicitud))->assertOk();
    }

    public function test_no_autenticado_es_redirigido_a_login(): void
    {
        $this->get(route('solicitudes.index'))->assertRedirect(route('login'));
    }

    public function test_listado_solicitante_solo_ve_las_propias(): void
    {
        $solicitante = $this->usuario('solicitante');
        $otro = $this->usuario('solicitante');

        $propia = SolicitudCambio::factory()->create(['created_by' => $solicitante->id]);
        SolicitudCambio::factory()->create(['created_by' => $otro->id]);

        $this->actingAs($solicitante)
            ->get(route('solicitudes.index'))
            ->assertOk()
            ->assertSee($propia->consecutivo)
            ->assertViewHas('solicitudes', fn ($s) => $s->total() === 1);
    }

    public function test_listado_dueno_de_proceso_solo_ve_las_que_afectan_su_proceso(): void
    {
        $duenoProceso = Proceso::factory()->create();
        $otroProceso = Proceso::factory()->create();

        $dueno = $this->usuario('dueno_proceso');
        $dueno->procesos()->attach($duenoProceso);

        $porAreaProceso = SolicitudCambio::factory()->create(['area_proceso' => $duenoProceso->nombre]);
        $porRiesgo = SolicitudCambio::factory()->create(['area_proceso' => $otroProceso->nombre]);
        $porRiesgo->riesgosAsociados()->create([
            'proceso_nombre' => $duenoProceso->nombre,
            'riesgo_texto' => 'Riesgo de prueba',
        ]);
        $ajena = SolicitudCambio::factory()->create(['area_proceso' => $otroProceso->nombre]);

        $this->actingAs($dueno)
            ->get(route('solicitudes.index'))
            ->assertOk()
            ->assertSee($porAreaProceso->consecutivo)
            ->assertSee($porRiesgo->consecutivo)
            ->assertDontSee($ajena->consecutivo);
    }

    public function test_responsable_de_tarea_sin_ningun_rol_llega_a_su_tarea_durante_la_implementacion(): void
    {
        $planta = Planta::query()->first() ?? Planta::factory()->create();
        $solicitud = SolicitudCambio::factory()->enEvaluacion()->create(['planta_id' => $planta->id]);
        $responsable = User::factory()->create(); // sin rol asignado, como en el flujo real.
        $responsable->forceFill(['planta_preferida_id' => $planta->id])->save();

        // Clasifica como "Menor" (rúbrica en 1) para que el gating habilite el Plan de acción
        // sin pasar por el cuestionario.
        $evaluador = app(\App\Domain\GestionCambio\EvaluadorRubrica::class);
        foreach (\App\Models\CriterioRubrica::factory()->count(11)->create() as $criterio) {
            $evaluador->calificar($solicitud, $criterio->id, 1);
        }

        $solicitud->accionesPlan()->create([
            'numero' => 1,
            'descripcion' => 'Tarea de prueba',
            'responsable_id' => $responsable->id,
            'creador_id' => $solicitud->created_by,
            'estado' => EstadoAccionPlan::EnCurso->value,
        ]);
        $solicitud->forceFill(['estado' => EstadoSolicitud::EnImplementacion])->save();

        // El link de su notificación (TareaAsignadaNotification) apunta aquí: '/solicitudes/{id}/editar#sec-5'.
        $this->actingAs($responsable)
            ->withSession(['planta_id' => $planta->id])
            ->get(route('solicitudes.edit', $solicitud))
            ->assertOk()
            ->assertSeeLivewire(PlanAccion::class);
    }

    public function test_responsable_de_tarea_ve_la_pagina_completa_sin_403_aunque_no_pueda_editar_las_demas_secciones(): void
    {
        $planta = Planta::query()->first() ?? Planta::factory()->create();
        $solicitud = SolicitudCambio::factory()->enEvaluacion()->create(['planta_id' => $planta->id]);
        $responsable = User::factory()->create(); // sin rol asignado, como en el flujo real.
        $responsable->forceFill(['planta_preferida_id' => $planta->id])->save();
        $responsable->assignRole('consulta');

        // Clasificado y con cuestionario completo, para que Cuestionario y Riesgos también
        // intenten montarse en la misma página (antes, su mount() exigía completarSecciones y
        // tumbaba TODA la página con un 403 para cualquiera que no fuera solicitante/dueño).
        $evaluador = app(\App\Domain\GestionCambio\EvaluadorRubrica::class);
        foreach (\App\Models\CriterioRubrica::factory()->count(11)->create() as $criterio) {
            $evaluador->calificar($solicitud, $criterio->id, 3);
        }
        $pregunta = \App\Models\PreguntaClave::factory()->create();
        app(\App\Domain\GestionCambio\RegistrarRespuesta::class)($solicitud, $pregunta, \App\Enums\ValorRespuesta::No);

        $solicitud->accionesPlan()->create([
            'numero' => 1,
            'descripcion' => 'Tarea de prueba',
            'responsable_id' => $responsable->id,
            'creador_id' => $solicitud->created_by,
            'estado' => EstadoAccionPlan::EnCurso->value,
        ]);

        $this->actingAs($responsable)
            ->withSession(['planta_id' => $planta->id])
            ->get(route('solicitudes.edit', $solicitud))
            ->assertOk()
            ->assertSeeLivewire(PlanAccion::class);
    }

    public function test_pagina_de_edicion_ya_no_es_alcanzable_una_vez_cerrada_o_anulada(): void
    {
        $admin = $this->usuario('administrador');
        $cerrada = SolicitudCambio::factory()->create(['estado' => EstadoSolicitud::Cerrado]);
        $cancelada = SolicitudCambio::factory()->create(['estado' => EstadoSolicitud::Cancelado]);

        $this->actingAs($admin);
        $this->get(route('solicitudes.edit', $cerrada))->assertForbidden();
        $this->get(route('solicitudes.edit', $cancelada))->assertForbidden();
    }

    public function test_el_formulario_de_decision_solo_se_ve_para_quien_puede_decidir(): void
    {
        $aprobador = $this->usuario('aprobador');
        $solicitud = SolicitudCambio::factory()->enEvaluacion()->create();
        AprobacionSolicitud::create([
            'solicitud_cambio_id' => $solicitud->id,
            'user_id' => $aprobador->id,
            'etapa' => AprobacionSolicitud::ETAPA_INICIAL,
            'decision' => AprobacionSolicitud::PENDIENTE,
        ]);

        $this->actingAs($aprobador)
            ->get(route('solicitudes.show', $solicitud))
            ->assertOk()
            ->assertSee('Aprobar')
            ->assertSee('Devolver');

        // Otro aprobador (rol válido, pero sin fila pendiente en ESTA solicitud) ve la sección,
        // pero sin botones que de todas formas le darían 403.
        $otroAprobador = $this->usuario('aprobador');
        $this->actingAs($otroAprobador)
            ->get(route('solicitudes.show', $solicitud))
            ->assertOk()
            ->assertSee('Esperando la decisión de los aprobadores asignados a esta etapa.')
            ->assertDontSee('Aprobar');
    }

    public function test_solicitante_y_admin_ven_quien_falta_por_aprobar_pero_el_aprobador_no(): void
    {
        $solicitante = $this->usuario('solicitante');
        $admin = $this->usuario('administrador');
        $decidido = $this->usuario('aprobador');
        $pendiente = $this->usuario('aprobador');
        $solicitud = SolicitudCambio::factory()->enEvaluacion()->create(['created_by' => $solicitante->id]);

        AprobacionSolicitud::create([
            'solicitud_cambio_id' => $solicitud->id, 'user_id' => $decidido->id,
            'etapa' => AprobacionSolicitud::ETAPA_INICIAL, 'decision' => AprobacionSolicitud::APROBADO,
            'decidido_at' => now(),
        ]);
        AprobacionSolicitud::create([
            'solicitud_cambio_id' => $solicitud->id, 'user_id' => $pendiente->id,
            'etapa' => AprobacionSolicitud::ETAPA_INICIAL, 'decision' => AprobacionSolicitud::PENDIENTE,
        ]);

        $this->actingAs($solicitante)
            ->get(route('solicitudes.show', $solicitud))
            ->assertOk()
            ->assertSee('1/2 decidieron')
            ->assertSee($pendiente->email)
            ->assertDontSee($decidido->email);

        $this->actingAs($admin)
            ->get(route('solicitudes.show', $solicitud))
            ->assertOk()
            ->assertSee($pendiente->email);

        $this->actingAs($pendiente)
            ->get(route('solicitudes.show', $solicitud))
            ->assertOk()
            ->assertDontSee('decidieron');
    }

    public function test_listado_administrador_y_consulta_ven_todas(): void
    {
        collect(range(1, 3))->each(fn () => SolicitudCambio::factory()->create());

        $admin = $this->usuario('administrador');
        $this->actingAs($admin)
            ->get(route('solicitudes.index'))
            ->assertOk()
            ->assertViewHas('solicitudes', fn ($s) => $s->total() === 3);

        $consulta = $this->usuario('consulta');
        $this->actingAs($consulta)
            ->get(route('solicitudes.index'))
            ->assertOk()
            ->assertViewHas('solicitudes', fn ($s) => $s->total() === 3);
    }
}
