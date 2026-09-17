<?php

namespace Tests\Feature;

use App\Domain\GestionCambio\AsignadorAprobador;
use App\Domain\GestionCambio\EvaluadorRubrica;
use App\Enums\EstadoSolicitud;
use App\Models\AprobacionSolicitud;
use App\Models\CriterioRubrica;
use App\Models\Proceso;
use App\Models\SolicitudCambio;
use App\Models\User;
use App\Notifications\AprobacionPendienteNotification;
use Database\Seeders\RolesPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * El dueño de proceso resuelto como aprobador (por ser Crítico/Mayor) nunca estuvo registrado
 * como "destinatario de área" (esa tabla es para SIG/SST/etc), así que EventoSolicitudNotification
 * jamás le llegaba: tenía una fila pendiente en aprobaciones_solicitud pero cero avisos.
 */
class AprobacionPendienteNotificacionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesPermisosSeeder::class);
        Notification::fake();
    }

    public function test_dueno_de_proceso_resuelto_como_aprobador_recibe_la_notificacion(): void
    {
        $proceso = Proceso::factory()->create();
        $dueno = User::factory()->create();
        $dueno->assignRole('dueno_proceso');
        $dueno->procesos()->attach($proceso);

        $solicitud = SolicitudCambio::factory()->create(['area_proceso' => $proceso->nombre]);

        // Suma 33 -> Crítico: incluye a los dueños del proceso en la compuerta inicial.
        foreach (CriterioRubrica::factory()->count(11)->create() as $criterio) {
            app(EvaluadorRubrica::class)->calificar($solicitud, $criterio->id, 3);
        }

        app(AsignadorAprobador::class)->sembrarAprobaciones($solicitud->fresh(), AprobacionSolicitud::ETAPA_INICIAL);

        $this->assertDatabaseHas('aprobaciones_solicitud', [
            'solicitud_cambio_id' => $solicitud->id,
            'user_id' => $dueno->id,
            'etapa' => AprobacionSolicitud::ETAPA_INICIAL,
        ]);
        Notification::assertSentTo($dueno, AprobacionPendienteNotification::class);
    }

    public function test_no_reenvia_la_notificacion_al_recalcular_el_set_de_aprobadores(): void
    {
        $proceso = Proceso::factory()->create();
        $dueno = User::factory()->create();
        $dueno->assignRole('dueno_proceso');
        $dueno->procesos()->attach($proceso);

        $solicitud = SolicitudCambio::factory()->create(['area_proceso' => $proceso->nombre]);
        foreach (CriterioRubrica::factory()->count(11)->create() as $criterio) {
            app(EvaluadorRubrica::class)->calificar($solicitud, $criterio->id, 3);
        }

        $asignador = app(AsignadorAprobador::class);
        $asignador->sembrarAprobaciones($solicitud->fresh(), AprobacionSolicitud::ETAPA_INICIAL);
        $asignador->sembrarAprobaciones($solicitud->fresh(), AprobacionSolicitud::ETAPA_INICIAL);

        Notification::assertSentToTimes($dueno, AprobacionPendienteNotification::class, 1);
    }

    public function test_el_flujo_completo_de_envio_notifica_al_dueno_de_proceso(): void
    {
        $proceso = Proceso::factory()->create();
        $dueno = User::factory()->create();
        $dueno->assignRole('dueno_proceso');
        $dueno->procesos()->attach($proceso);

        $lider = $this->actuarComo('administrador');
        $solicitud = SolicitudCambio::factory()->enEvaluacion()->create([
            'area_proceso' => $proceso->nombre,
            'created_by' => $lider->id,
        ]);
        foreach (CriterioRubrica::factory()->count(11)->create() as $criterio) {
            app(EvaluadorRubrica::class)->calificar($solicitud, $criterio->id, 3);
        }
        $solicitud->forceFill(['estado' => EstadoSolicitud::Solicitado])->save();

        $this->post(route('solicitudes.enviar', $solicitud))->assertRedirect();

        Notification::assertSentTo($dueno, AprobacionPendienteNotification::class);
    }
}
