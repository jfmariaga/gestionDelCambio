<?php

namespace Tests\Feature;

use App\Enums\EstadoSolicitud;
use App\Models\AprobacionSolicitud;
use App\Models\Planta;
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
}
