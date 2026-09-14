<?php

namespace Tests\Feature;

use App\Domain\GestionCambio\TransicionSolicitud;
use App\Enums\EstadoAccionPlan;
use App\Enums\EstadoSolicitud;
use App\Livewire\Solicitud\PlanAccion;
use App\Models\SolicitudCambio;
use App\Models\User;
use App\Notifications\TareaAsignadaNotification;
use App\Notifications\TareaPorValidarNotification;
use App\Notifications\TareaRechazadaNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class TareaPlanValidacionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    public function test_flujo_completo_asignacion_cierre_validacion(): void
    {
        $lider = $this->actuarComo('administrador');
        $responsable = User::factory()->create(['name' => 'Reyes Soto']);
        $solicitud = SolicitudCambio::factory()->create(['created_by' => $lider->id]);

        // El líder crea la tarea y asigna a otra persona -> notificación de asignación.
        $comp = Livewire::test(PlanAccion::class, ['solicitud' => $solicitud])->call('agregar');
        $accion = $solicitud->accionesPlan()->first();
        $comp->set("edicion.{$accion->id}.responsable_id", $responsable->id)
            ->call('guardar', $accion->id);

        Notification::assertSentTo($responsable, TareaAsignadaNotification::class);

        // El responsable marca cerrada -> pendiente de validación + aviso al líder.
        $this->actingAs($responsable);
        Livewire::test(PlanAccion::class, ['solicitud' => $solicitud])
            ->call('marcarCerrada', $accion->id);

        $accion->refresh();
        $this->assertSame(EstadoAccionPlan::CerradaPendienteValidacion, $accion->estado);
        Notification::assertSentTo($lider, TareaPorValidarNotification::class);

        // No se puede cerrar la solicitud con la tarea sin validar.
        $solicitud->update(['estado' => EstadoSolicitud::Aprobado]);
        $bloqueos = app(TransicionSolicitud::class)->bloqueosDeCierre($solicitud->fresh());
        $this->assertNotEmpty($bloqueos);

        // El líder valida.
        $this->actingAs($lider);
        Livewire::test(PlanAccion::class, ['solicitud' => $solicitud])
            ->call('validar', $accion->id);

        $accion->refresh();
        $this->assertSame(EstadoAccionPlan::Validada, $accion->estado);
        $this->assertSame($lider->id, $accion->validada_por);
        $this->assertSame([], app(TransicionSolicitud::class)->bloqueosDeCierre($solicitud->fresh()));
    }

    public function test_rechazo_devuelve_a_en_curso_y_notifica_al_responsable(): void
    {
        $lider = $this->actuarComo('administrador');
        $responsable = User::factory()->create();
        $solicitud = SolicitudCambio::factory()->create(['created_by' => $lider->id]);

        $comp = Livewire::test(PlanAccion::class, ['solicitud' => $solicitud])->call('agregar');
        $accion = $solicitud->accionesPlan()->first();
        $comp->set("edicion.{$accion->id}.responsable_id", $responsable->id)->call('guardar', $accion->id);

        $this->actingAs($responsable);
        Livewire::test(PlanAccion::class, ['solicitud' => $solicitud])->call('marcarCerrada', $accion->id);

        $this->actingAs($lider);
        Livewire::test(PlanAccion::class, ['solicitud' => $solicitud])
            ->set("rechazo.{$accion->id}", 'Falta la evidencia fotográfica.')
            ->call('rechazar', $accion->id);

        $accion->refresh();
        $this->assertSame(EstadoAccionPlan::EnCurso, $accion->estado);
        $this->assertSame('Falta la evidencia fotográfica.', $accion->comentario_validacion);
        Notification::assertSentTo($responsable, TareaRechazadaNotification::class);
    }

    public function test_solo_el_responsable_marca_cerrada(): void
    {
        $lider = $this->actuarComo('administrador');
        $responsable = User::factory()->create();
        $intruso = User::factory()->create();
        $intruso->assignRole('consulta');
        $solicitud = SolicitudCambio::factory()->create(['created_by' => $lider->id]);

        $comp = Livewire::test(PlanAccion::class, ['solicitud' => $solicitud])->call('agregar');
        $accion = $solicitud->accionesPlan()->first();
        $comp->set("edicion.{$accion->id}.responsable_id", $responsable->id)->call('guardar', $accion->id);

        // 'consulta' puede ver una solicitud aprobada pero no es el responsable de la tarea.
        $solicitud->update(['estado' => EstadoSolicitud::Aprobado]);

        $this->actingAs($intruso);
        Livewire::test(PlanAccion::class, ['solicitud' => $solicitud])
            ->call('marcarCerrada', $accion->id)
            ->assertStatus(403);
    }
}
