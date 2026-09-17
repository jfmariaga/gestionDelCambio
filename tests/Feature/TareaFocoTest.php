<?php

namespace Tests\Feature;

use App\Enums\EstadoAccionPlan;
use App\Livewire\Solicitud\PlanAccion;
use App\Models\Planta;
use App\Models\SolicitudCambio;
use App\Models\User;
use Database\Seeders\RolesPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * La notificación de una tarea del plan de acción enlaza a /solicitudes/{id}/tareas/{accion}:
 * una vista mínima con SOLO esa tarea, para que un responsable que no es solicitante, dueño de
 * proceso ni admin no vea (ni pueda tocar) el resto del formulario.
 */
class TareaFocoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesPermisosSeeder::class);
    }

    private function solicitudConDosTareas(): array
    {
        $planta = Planta::query()->first() ?? Planta::factory()->create();
        $lider = User::factory()->create();
        $solicitud = SolicitudCambio::factory()->create(['planta_id' => $planta->id, 'created_by' => $lider->id]);
        $responsable = User::factory()->create();
        $responsable->forceFill(['planta_preferida_id' => $planta->id])->save();

        $suya = $solicitud->accionesPlan()->create([
            'numero' => 1,
            'descripcion' => 'Tarea del responsable',
            'responsable_id' => $responsable->id,
            'creador_id' => $lider->id,
            'estado' => EstadoAccionPlan::EnCurso->value,
        ]);
        $ajena = $solicitud->accionesPlan()->create([
            'numero' => 2,
            'descripcion' => 'Tarea de otra persona',
            'estado' => EstadoAccionPlan::Pendiente->value,
        ]);

        return [$planta, $solicitud, $responsable, $suya, $ajena, $lider];
    }

    public function test_la_vista_de_foco_solo_muestra_la_tarea_enlazada(): void
    {
        [$planta, $solicitud, $responsable, $suya, $ajena] = $this->solicitudConDosTareas();

        $this->actingAs($responsable)
            ->withSession(['planta_id' => $planta->id])
            ->get(route('solicitudes.tarea', [$solicitud, $suya]))
            ->assertOk()
            ->assertSee('Tarea del responsable')
            ->assertDontSee('Tarea de otra persona')
            ->assertDontSee('Agregar acción');
    }

    public function test_no_se_puede_guardar_ni_cerrar_una_tarea_distinta_a_la_enlazada(): void
    {
        [, $solicitud, $responsable, $suya, $ajena] = $this->solicitudConDosTareas();

        $this->actingAs($responsable);

        Livewire::test(PlanAccion::class, ['solicitud' => $solicitud, 'soloAccionId' => $suya->id])
            ->call('marcarCerrada', $ajena->id)
            ->assertForbidden();

        Livewire::test(PlanAccion::class, ['solicitud' => $solicitud, 'soloAccionId' => $suya->id])
            ->set("edicion.{$ajena->id}.evidencia", 'intento ajeno')
            ->call('guardar', $ajena->id)
            ->assertForbidden();

        $this->assertNull($ajena->fresh()->evidencia);
    }

    public function test_en_foco_no_se_puede_agregar_ni_eliminar_acciones(): void
    {
        [, $solicitud, $responsable, $suya] = $this->solicitudConDosTareas();

        $this->actingAs($responsable);

        Livewire::test(PlanAccion::class, ['solicitud' => $solicitud, 'soloAccionId' => $suya->id])
            ->call('agregar')
            ->assertForbidden();

        Livewire::test(PlanAccion::class, ['solicitud' => $solicitud, 'soloAccionId' => $suya->id])
            ->call('eliminar', $suya->id)
            ->assertForbidden();
    }

    public function test_el_responsable_si_puede_marcar_cerrada_su_propia_tarea_en_foco(): void
    {
        [, $solicitud, $responsable, $suya] = $this->solicitudConDosTareas();

        $this->actingAs($responsable);

        Livewire::test(PlanAccion::class, ['solicitud' => $solicitud, 'soloAccionId' => $suya->id])
            ->call('marcarCerrada', $suya->id);

        $this->assertSame(EstadoAccionPlan::CerradaPendienteValidacion, $suya->fresh()->estado);
    }

    public function test_el_responsable_solo_puede_tocar_evidencia_nota_y_estado_de_su_tarea(): void
    {
        [, $solicitud, $responsable, $suya] = $this->solicitudConDosTareas();
        $otroUsuario = User::factory()->create();

        $this->actingAs($responsable);

        Livewire::test(PlanAccion::class, ['solicitud' => $solicitud, 'soloAccionId' => $suya->id])
            ->set("edicion.{$suya->id}.descripcion", 'Descripción manipulada')
            ->set("edicion.{$suya->id}.proceso", 'Proceso manipulado')
            ->set("edicion.{$suya->id}.responsable_id", $otroUsuario->id)
            ->set("edicion.{$suya->id}.estado", EstadoAccionPlan::Pendiente->value)
            ->set("edicion.{$suya->id}.evidencia", 'Evidencia legítima del responsable')
            ->call('guardar', $suya->id);

        $suya->refresh();
        $this->assertSame('Tarea del responsable', $suya->descripcion);
        $this->assertNull($suya->proceso);
        $this->assertSame($responsable->id, $suya->responsable_id);
        $this->assertSame(EstadoAccionPlan::Pendiente, $suya->estado);
        $this->assertSame('Evidencia legítima del responsable', $suya->evidencia);
    }

    public function test_el_lider_si_puede_reorganizar_descripcion_proceso_fecha_y_responsable(): void
    {
        [, $solicitud, , $suya, , $lider] = $this->solicitudConDosTareas();
        $otroUsuario = User::factory()->create();

        $this->actingAs($lider);

        Livewire::test(PlanAccion::class, ['solicitud' => $solicitud])
            ->set("edicion.{$suya->id}.descripcion", 'Descripción reorganizada por el líder')
            ->set("edicion.{$suya->id}.responsable_id", $otroUsuario->id)
            ->call('guardar', $suya->id);

        $suya->refresh();
        $this->assertSame('Descripción reorganizada por el líder', $suya->descripcion);
        $this->assertSame($otroUsuario->id, $suya->responsable_id);
    }
}
