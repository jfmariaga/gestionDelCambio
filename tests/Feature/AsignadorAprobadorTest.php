<?php

namespace Tests\Feature;

use App\Domain\GestionCambio\EvaluadorRubrica;
use App\Enums\Clasificacion;
use App\Models\AreaNotificacion;
use App\Models\CriterioRubrica;
use App\Models\DestinatarioArea;
use App\Models\Proceso;
use App\Models\SolicitudCambio;
use App\Models\User;
use Database\Seeders\AreaNotificacionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AsignadorAprobadorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actuarComo('administrador');
        $this->seed(AreaNotificacionSeeder::class);
    }

    private function clasificar(SolicitudCambio $solicitud, int $suma): void
    {
        $criterios = CriterioRubrica::factory()->count(11)->create();
        $evaluador = app(EvaluadorRubrica::class);
        $extra = $suma - 11;
        foreach ($criterios as $criterio) {
            $valor = 1;
            if ($extra > 0) {
                $sube = min(2, $extra);
                $valor += $sube;
                $extra -= $sube;
            }
            $evaluador->calificar($solicitud, $criterio->id, $valor);
        }
    }

    public function test_menor_asigna_al_dueno_del_proceso(): void
    {
        $proceso = Proceso::factory()->create(['nombre' => 'Producción']);
        $dueno = User::factory()->create();
        $dueno->assignRole('dueno_proceso');
        $proceso->duenos()->attach($dueno);

        $solicitud = SolicitudCambio::factory()->create(['area_proceso' => 'Producción']);
        $this->clasificar($solicitud, 13);

        $this->assertSame(Clasificacion::Menor, $solicitud->fresh()->clasificacionVigente());
        $this->assertSame($dueno->id, $solicitud->fresh()->aprobador_asignado_id);
    }

    private function destinatario(string $clave): User
    {
        $user = User::factory()->create();
        DestinatarioArea::create([
            'area_id' => AreaNotificacion::where('clave', $clave)->value('id'),
            'user_id' => $user->id,
            'activo' => true,
        ]);

        return $user;
    }

    public function test_mayor_asigna_sig_ambiental_calidad_sst_y_dueno(): void
    {
        $proceso = Proceso::factory()->create(['nombre' => 'Producción']);
        $dueno = User::factory()->create();
        $dueno->assignRole('dueno_proceso');
        $proceso->duenos()->attach($dueno);

        $sig = $this->destinatario('gestion_integral');
        $amb = $this->destinatario('gestion_ambiental');
        $cal = $this->destinatario('calidad_inocuidad');
        $sst = $this->destinatario('sst');

        $solicitud = SolicitudCambio::factory()->create(['area_proceso' => 'Producción']);
        $this->clasificar($solicitud, 20);

        $solicitud->refresh();
        $this->assertSame(Clasificacion::Mayor, $solicitud->clasificacionVigente());
        $this->assertEqualsCanonicalizing(
            [$sig->id, $amb->id, $cal->id, $sst->id, $dueno->id],
            $solicitud->aprobadores->pluck('id')->all(),
        );
    }

    public function test_critico_incluye_gerencia_general(): void
    {
        $sig = $this->destinatario('gestion_integral');
        $gerente = $this->destinatario('gerencia_general');

        $solicitud = SolicitudCambio::factory()->create();
        $this->clasificar($solicitud, 30);

        $solicitud->refresh();
        $this->assertSame(Clasificacion::Critico, $solicitud->clasificacionVigente());
        $this->assertTrue($solicitud->aprobadores->contains($sig));
        $this->assertTrue($solicitud->aprobadores->contains($gerente));
    }

    public function test_override_manual_sobrevive_a_recalculo(): void
    {
        $elegido = User::factory()->create();
        $solicitud = SolicitudCambio::factory()->create();
        $solicitud->forceFill(['aprobador_asignado_id' => $elegido->id, 'aprobador_override' => true])->save();

        $this->clasificar($solicitud, 20);

        $this->assertSame($elegido->id, $solicitud->fresh()->aprobador_asignado_id);
    }

    public function test_endpoint_admin_fija_override(): void
    {
        $otro = User::factory()->create();
        $solicitud = SolicitudCambio::factory()->create();

        $this->patch(route('solicitudes.aprobador', $solicitud), ['aprobador_asignado_id' => $otro->id])
            ->assertRedirect();

        $solicitud->refresh();
        $this->assertSame($otro->id, $solicitud->aprobador_asignado_id);
        $this->assertTrue($solicitud->aprobador_override);
    }
}
