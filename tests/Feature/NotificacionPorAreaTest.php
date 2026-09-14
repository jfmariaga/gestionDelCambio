<?php

namespace Tests\Feature;

use App\Domain\GestionCambio\EvaluadorRubrica;
use App\Domain\GestionCambio\Notificador;
use App\Models\AreaNotificacion;
use App\Models\CriterioRubrica;
use App\Models\DestinatarioArea;
use App\Models\SolicitudCambio;
use App\Models\User;
use App\Notifications\EventoSolicitudNotification;
use Database\Seeders\AreaNotificacionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificacionPorAreaTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string,User> */
    private array $miembros = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->actuarComo('administrador');
        $this->seed(AreaNotificacionSeeder::class);
        Notification::fake();

        foreach (['gestion_integral', 'jefes', 'sst', 'gestion_ambiental', 'calidad_inocuidad', 'comite_cambio'] as $clave) {
            $user = User::factory()->create();
            DestinatarioArea::create([
                'area_id' => AreaNotificacion::where('clave', $clave)->value('id'),
                'user_id' => $user->id,
                'activo' => true,
            ]);
            $this->miembros[$clave] = $user;
        }
    }

    private function clasificar(SolicitudCambio $solicitud, int $suma): void
    {
        $criterios = CriterioRubrica::factory()->count(11)->create();
        $evaluador = app(EvaluadorRubrica::class);
        $extra = $suma - 11;
        foreach ($criterios as $criterio) {
            $v = 1;
            if ($extra > 0) {
                $s = min(2, $extra);
                $v += $s;
                $extra -= $s;
            }
            $evaluador->calificar($solicitud, $criterio->id, $v);
        }
    }

    public function test_menor_solo_notifica_gestion_integral_y_jefes(): void
    {
        $solicitud = SolicitudCambio::factory()->create();
        $this->clasificar($solicitud, 13);

        app(Notificador::class)->notificarEvento($solicitud->fresh(), 'creada');

        Notification::assertSentTo($this->miembros['gestion_integral'], EventoSolicitudNotification::class);
        Notification::assertSentTo($this->miembros['jefes'], EventoSolicitudNotification::class);
        Notification::assertNotSentTo($this->miembros['sst'], EventoSolicitudNotification::class);
        Notification::assertNotSentTo($this->miembros['comite_cambio'], EventoSolicitudNotification::class);
    }

    public function test_mayor_notifica_las_cuatro_areas(): void
    {
        $solicitud = SolicitudCambio::factory()->create();
        $this->clasificar($solicitud, 20);

        app(Notificador::class)->notificarEvento($solicitud->fresh(), 'creada');

        foreach (['gestion_integral', 'jefes', 'sst', 'gestion_ambiental', 'calidad_inocuidad'] as $clave) {
            Notification::assertSentTo($this->miembros[$clave], EventoSolicitudNotification::class);
        }
        Notification::assertNotSentTo($this->miembros['comite_cambio'], EventoSolicitudNotification::class);
    }

    public function test_evento_enviada_incluye_comite(): void
    {
        $solicitud = SolicitudCambio::factory()->create();
        $this->clasificar($solicitud, 20);

        app(Notificador::class)->notificarEvento($solicitud->fresh(), 'enviada');

        Notification::assertSentTo($this->miembros['comite_cambio'], EventoSolicitudNotification::class);
    }

    public function test_area_sin_destinatarios_queda_en_bitacora_sin_error(): void
    {
        // Quitamos los destinatarios de "jefes".
        DestinatarioArea::where('area_id', AreaNotificacion::where('clave', 'jefes')->value('id'))->delete();

        $solicitud = SolicitudCambio::factory()->create();
        $this->clasificar($solicitud, 13);

        app(Notificador::class)->notificarEvento($solicitud->fresh(), 'creada');

        $this->assertDatabaseHas('bitacora_eventos', [
            'solicitud_cambio_id' => $solicitud->id,
            'evento' => 'notificacion_sin_destinatarios',
        ]);
    }

    public function test_destinatario_de_correo_externo_recibe_por_mail(): void
    {
        DestinatarioArea::create([
            'area_id' => AreaNotificacion::where('clave', 'gestion_integral')->value('id'),
            'email' => 'gestion.integral@empresa.com',
            'activo' => true,
        ]);

        $solicitud = SolicitudCambio::factory()->create();
        $this->clasificar($solicitud, 13);

        app(Notificador::class)->notificarEvento($solicitud->fresh(), 'creada');

        Notification::assertSentOnDemand(EventoSolicitudNotification::class, function ($notification, $channels, $notifiable) {
            return in_array('gestion.integral@empresa.com', (array) ($notifiable->routes['mail'] ?? []), true);
        });
    }
}
