<?php

namespace Tests\Feature;

use App\Domain\GestionCambio\EvaluadorRubrica;
use App\Domain\GestionCambio\RegistrarRespuesta;
use App\Domain\GestionCambio\TransicionSolicitud;
use App\Enums\Clasificacion;
use App\Enums\EstadoAccionPlan;
use App\Enums\EstadoSolicitud;
use App\Enums\NivelRiesgo;
use App\Enums\ValorRespuesta;
use App\Livewire\Solicitud\AprobacionCierre;
use App\Livewire\Solicitud\PlanAccion;
use App\Livewire\Solicitud\RiesgosAsociados;
use App\Models\AreaNotificacion;
use App\Models\CriterioRubrica;
use App\Models\DestinatarioArea;
use App\Models\PreguntaClave;
use App\Models\Proceso;
use App\Models\RiesgoPredeterminado;
use App\Models\SolicitudCambio;
use App\Models\User;
use App\Notifications\EventoSolicitudNotification;
use App\Notifications\TareaAsignadaNotification;
use App\Notifications\TareaPorValidarNotification;
use Database\Seeders\AreaNotificacionSeeder;
use Database\Seeders\RolesPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Recorre el ciclo de vida COMPLETO de una solicitud de cambio clasificada como Crítico:
 * creación → rúbrica de evaluación → cuestionario (genera riesgo) → riesgo calificado Alto
 * (genera plan de acción automático) → asignación y validación de la tarea → envío a
 * aprobación → aprobación inicial → implementación → verificación → cierre de criterios →
 * decisión de cierre. En cada paso verifica que la notificación correspondiente se dispare
 * hacia los destinatarios configurados.
 */
class FlujoCompletoCambioCriticoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesPermisosSeeder::class);
        $this->seed(AreaNotificacionSeeder::class);
        Notification::fake();
    }

    /** Registra un usuario como destinatario de un área de notificación. */
    private function destinatario(string $clave, User $usuario): void
    {
        DestinatarioArea::create([
            'area_id' => AreaNotificacion::where('clave', $clave)->value('id'),
            'user_id' => $usuario->id,
            'activo' => true,
        ]);
    }

    public function test_flujo_completo_de_un_cambio_critico_con_notificaciones(): void
    {
        // --- Actores ---
        $lider = $this->actuarComo('administrador'); // crea, completa y gestiona la solicitud.

        // Un único aprobador cubre todas las áreas que intervienen en un cambio Crítico, tal
        // como se configuraría en producción para las pruebas (un solo destinatario real).
        $aprobador = User::factory()->create();
        foreach (['gestion_integral', 'sst', 'gestion_ambiental', 'calidad_inocuidad', 'gerencia_general', 'comite_cambio'] as $area) {
            $this->destinatario($area, $aprobador);
        }
        $jefe = User::factory()->create();
        $this->destinatario('jefes', $jefe);

        // --- 1. Crear la solicitud (HTTP real, como lo haría un usuario) ---
        $proceso = Proceso::factory()->create();

        $response = $this->post(route('solicitudes.store'), [
            'fecha' => now()->toDateString(),
            'nombre_cambio' => 'Cambio crítico de prueba',
            'area_proceso' => $proceso->nombre,
            'tipo_cambio' => 'Permanente',
            'fecha_requerida' => now()->addDays(30)->toDateString(),
            'costo_estimado' => 1000000,
            'requiere_comite' => true,
            'situacion_actual' => 'Situación actual de prueba.',
            'que_cambiara' => 'Qué cambiará de prueba.',
            'resultado_esperado' => 'Resultado esperado de prueba.',
        ]);

        $solicitud = SolicitudCambio::where('created_by', $lider->id)->latest('id')->firstOrFail();
        $response->assertRedirect(route('solicitudes.edit', $solicitud));

        Notification::assertSentTo($aprobador, EventoSolicitudNotification::class,
            fn ($n) => $n->evento === 'creada' && $n->area === 'gestion_integral');
        Notification::assertSentTo($jefe, EventoSolicitudNotification::class,
            fn ($n) => $n->evento === 'creada' && $n->area === 'jefes');

        // --- 2. Rúbrica de evaluación: 11 criterios en 3 -> suma 33 -> Crítico ---
        $criterios = CriterioRubrica::factory()->count(11)->create();
        $evaluador = app(EvaluadorRubrica::class);
        foreach ($criterios as $criterio) {
            $resultado = $evaluador->calificar($solicitud, $criterio->id, 3);
        }
        $this->assertTrue($resultado['completa']);
        $this->assertSame(Clasificacion::Critico, $resultado['clasificacion']);
        $this->assertSame(Clasificacion::Critico, $solicitud->fresh()->clasificacionVigente());

        // --- 3. Cuestionario: responder "Sí" genera un riesgo asociado ---
        $riesgoPredeterminado = RiesgoPredeterminado::factory()->create(['proceso_id' => $proceso->id]);
        $pregunta = PreguntaClave::factory()->conRiesgo($riesgoPredeterminado)->create();
        app(RegistrarRespuesta::class)($solicitud, $pregunta, ValorRespuesta::Si);

        $riesgo = $solicitud->riesgosAsociados()->firstOrFail();

        // --- 4. Calificar el riesgo como Alto -> se crea automáticamente el plan de acción ---
        Livewire::test(RiesgosAsociados::class, ['solicitud' => $solicitud])
            ->set("edicion.{$riesgo->id}.probabilidad", 10)
            ->set("edicion.{$riesgo->id}.impacto", 10)
            ->call('guardarFila', $riesgo->id);

        $riesgo->refresh();
        $this->assertSame(NivelRiesgo::Alto, $riesgo->nivel);

        $accionRiesgo = $solicitud->accionesPlan()->whereNotNull('riesgo_asociado_id')->firstOrFail();
        $this->assertSame($riesgo->id, $accionRiesgo->riesgo_asociado_id);

        // --- 5. Asignar responsable a la tarea del plan -> notifica al responsable ---
        $responsableTarea = User::factory()->create();
        Livewire::test(PlanAccion::class, ['solicitud' => $solicitud])
            ->set("edicion.{$accionRiesgo->id}.responsable_id", $responsableTarea->id)
            ->call('guardar', $accionRiesgo->id);

        Notification::assertSentTo($responsableTarea, TareaAsignadaNotification::class);

        // --- 6. El responsable cierra la tarea -> notifica al líder para validar ---
        $this->actingAs($responsableTarea);
        Livewire::test(PlanAccion::class, ['solicitud' => $solicitud])
            ->call('marcarCerrada', $accionRiesgo->id);

        $this->assertSame(EstadoAccionPlan::CerradaPendienteValidacion, $accionRiesgo->fresh()->estado);
        Notification::assertSentTo($lider, TareaPorValidarNotification::class);

        // --- 7. El líder valida la tarea ---
        $this->actingAs($lider);
        Livewire::test(PlanAccion::class, ['solicitud' => $solicitud])
            ->call('validar', $accionRiesgo->id);

        $this->assertSame(EstadoAccionPlan::Validada, $accionRiesgo->fresh()->estado);

        // --- 8. Enviar a aprobación ---
        $this->post(route('solicitudes.enviar', $solicitud))
            ->assertRedirect(route('solicitudes.show', $solicitud));

        $this->assertSame(EstadoSolicitud::EnEvaluacion, $solicitud->fresh()->estado);
        Notification::assertSentTo($aprobador, EventoSolicitudNotification::class,
            fn ($n) => $n->evento === 'enviada' && $n->area === 'comite_cambio');

        // --- 9. Aprobación inicial (compuerta 1) ---
        $this->actingAs($aprobador);
        $this->post(route('solicitudes.decision', $solicitud), ['accion' => 'aprobar'])
            ->assertRedirect(route('solicitudes.show', $solicitud));

        $this->assertSame(EstadoSolicitud::Aprobado, $solicitud->fresh()->estado);
        Notification::assertSentTo($aprobador, EventoSolicitudNotification::class,
            fn ($n) => $n->evento === 'aprobada');

        // --- 10. Implementación ---
        $this->actingAs($lider);
        $this->post(route('solicitudes.implementar', $solicitud))
            ->assertRedirect(route('solicitudes.show', $solicitud));
        $this->assertSame(EstadoSolicitud::EnImplementacion, $solicitud->fresh()->estado);

        app(TransicionSolicitud::class)->marcarImplementadoSiCorresponde($solicitud->fresh(), $lider->id);
        $this->assertSame(EstadoSolicitud::Implementado, $solicitud->fresh()->estado);

        // --- 11. Nota de cierre: se registra ANTES de poder enviar a verificación ---
        Livewire::test(AprobacionCierre::class, ['solicitud' => $solicitud])
            ->set('notaCierre', 'Cambio implementado sin novedades, listo para verificación.')
            ->call('guardar');

        $this->assertSame('Cambio implementado sin novedades, listo para verificación.', $solicitud->fresh()->nota_cierre);

        // --- 12. Enviar a verificación (compuerta 2) ---
        $this->post(route('solicitudes.enviarVerificacion', $solicitud))
            ->assertRedirect(route('solicitudes.show', $solicitud));
        $this->assertSame(EstadoSolicitud::EnVerificacion, $solicitud->fresh()->estado);
        Notification::assertSentTo($aprobador, EventoSolicitudNotification::class,
            fn ($n) => $n->evento === 'verificacion' && $n->area === 'gerencia_general');

        // --- 13. Decisión de cierre (compuerta 2): sin bloqueos porque la tarea ya está validada
        //     y el riesgo Alto tiene su plan de acción vinculado -> la solicitud queda Cerrada.
        $this->actingAs($aprobador);
        $this->post(route('solicitudes.decision', $solicitud), ['accion' => 'aprobar'])
            ->assertRedirect(route('solicitudes.show', $solicitud));

        $this->assertSame(EstadoSolicitud::Cerrado, $solicitud->fresh()->estado);
        Notification::assertSentTo($aprobador, EventoSolicitudNotification::class,
            fn ($n) => $n->evento === 'cerrada');
        Notification::assertSentTo($jefe, EventoSolicitudNotification::class,
            fn ($n) => $n->evento === 'cerrada' && $n->area === 'jefes');
    }
}
