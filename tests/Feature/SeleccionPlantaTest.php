<?php

namespace Tests\Feature;

use App\Models\Planta;
use App\Models\SolicitudCambio;
use App\Models\User;
use Database\Seeders\RolesPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeleccionPlantaTest extends TestCase
{
    use RefreshDatabase;

    public function test_usuario_sin_planta_preferida_es_redirigido_a_seleccionar(): void
    {
        $this->seed(RolesPermisosSeeder::class);
        Planta::factory()->create(['nombre' => 'Panal', 'codigo' => 'PANAL']);

        $user = User::factory()->create();
        $user->assignRole('solicitante');

        $this->actingAs($user)
            ->get(route('solicitudes.index'))
            ->assertRedirect(route('plantas.seleccionar'));
    }

    public function test_seleccionar_planta_con_recordar_fija_preferencia_y_sesion(): void
    {
        $this->seed(RolesPermisosSeeder::class);
        $planta = Planta::factory()->create(['nombre' => 'Leva Pan', 'codigo' => 'LEVAPAN']);

        $user = User::factory()->create();
        $user->assignRole('solicitante');

        $this->actingAs($user)
            ->post(route('plantas.seleccionar.store'), ['planta_id' => $planta->id, 'recordar' => '1'])
            ->assertRedirect();

        $this->assertSame($planta->id, $user->fresh()->planta_preferida_id);
        $this->assertSame($planta->id, session('planta_id'));
    }

    public function test_listado_se_filtra_por_planta_activa_para_no_administrador(): void
    {
        $this->seed(RolesPermisosSeeder::class);
        $panal = Planta::factory()->create(['nombre' => 'Panal', 'codigo' => 'PANAL']);
        $leva = Planta::factory()->create(['nombre' => 'Leva Pan', 'codigo' => 'LEVAPAN']);

        $user = User::factory()->create();
        $user->forceFill(['planta_preferida_id' => $panal->id])->save();
        $user->assignRole('solicitante');

        // Propias en ambas plantas: la del listado solo debe filtrar por la planta activa,
        // no por autoría, así que la de Leva Pan se oculta aunque sea suya.
        SolicitudCambio::factory()->create(['planta_id' => $panal->id, 'nombre_cambio' => 'Cambio en Panal', 'created_by' => $user->id]);
        SolicitudCambio::factory()->create(['planta_id' => $leva->id, 'nombre_cambio' => 'Cambio en Leva', 'created_by' => $user->id]);

        // De otro solicitante, en la misma planta activa: se oculta por no ser autor.
        $otro = User::factory()->create();
        $otro->assignRole('solicitante');
        SolicitudCambio::factory()->create(['planta_id' => $panal->id, 'nombre_cambio' => 'Cambio ajeno en Panal', 'created_by' => $otro->id]);

        $this->actingAs($user)
            ->withSession(['planta_id' => $panal->id])
            ->get(route('solicitudes.index'))
            ->assertOk()
            ->assertSee('Cambio en Panal')
            ->assertDontSee('Cambio en Leva')
            ->assertDontSee('Cambio ajeno en Panal');
    }

    public function test_administrador_ve_todas_las_plantas(): void
    {
        $admin = $this->actuarComo('administrador');
        $otra = Planta::factory()->create(['nombre' => 'Otra Sede', 'codigo' => 'OTRA']);
        SolicitudCambio::factory()->create(['planta_id' => $otra->id, 'nombre_cambio' => 'Cambio en otra sede']);

        $this->get(route('solicitudes.index'))
            ->assertOk()
            ->assertSee('Cambio en otra sede');
    }

    public function test_crear_solicitud_asocia_la_planta_activa(): void
    {
        $planta = Planta::factory()->create(['nombre' => 'Panal', 'codigo' => 'PANAL']);
        $this->seed(RolesPermisosSeeder::class);

        $user = User::factory()->create();
        $user->forceFill(['planta_preferida_id' => $planta->id])->save();
        $user->assignRole('solicitante');

        $this->actingAs($user)
            ->withSession(['planta_id' => $planta->id])
            ->post(route('solicitudes.store'), [
                'fecha' => now()->toDateString(),
                'nombre_cambio' => 'Cambio con planta',
            ])->assertRedirect();

        $this->assertSame($planta->id, SolicitudCambio::where('nombre_cambio', 'Cambio con planta')->value('planta_id'));
    }
}
