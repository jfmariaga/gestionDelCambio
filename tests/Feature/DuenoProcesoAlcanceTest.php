<?php

namespace Tests\Feature;

use App\Domain\GestionCambio\RegistrarRespuesta;
use App\Enums\ValorRespuesta;
use App\Livewire\Solicitud\RiesgosAsociados;
use App\Models\PreguntaClave;
use App\Models\Proceso;
use App\Models\SolicitudCambio;
use App\Models\User;
use Database\Seeders\RolesPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DuenoProcesoAlcanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesPermisosSeeder::class);
    }

    public function test_dueno_de_proceso_solo_edita_filas_de_sus_procesos(): void
    {
        $produccion = Proceso::factory()->create(['nombre' => 'Producción']);
        $sst = Proceso::factory()->create(['nombre' => 'SST']);

        $dueno = User::factory()->create();
        $dueno->assignRole('dueno_proceso');
        $dueno->procesos()->attach($produccion->id);

        $solicitud = SolicitudCambio::factory()->create();
        $pProd = PreguntaClave::factory()->paraProceso($produccion)->create();
        $pSst = PreguntaClave::factory()->paraProceso($sst)->create();
        $registrar = app(RegistrarRespuesta::class);
        $registrar($solicitud, $pProd, ValorRespuesta::Si);
        $registrar($solicitud, $pSst, ValorRespuesta::Si);

        $filaProd = $solicitud->riesgosAsociados()->where('proceso_nombre', 'Producción')->first();
        $filaSst = $solicitud->riesgosAsociados()->where('proceso_nombre', 'SST')->first();

        $this->actingAs($dueno);
        $component = Livewire::test(RiesgosAsociados::class, ['solicitud' => $solicitud]);

        // Puede guardar la fila de su proceso.
        $component->set("edicion.{$filaProd->id}.accion_requerida", 'ajuste válido')
            ->call('guardarFila', $filaProd->id);
        $this->assertSame('ajuste válido', $filaProd->fresh()->accion_requerida);

        // No puede guardar la fila de un proceso ajeno.
        $component->set("edicion.{$filaSst->id}.accion_requerida", 'no permitido')
            ->call('guardarFila', $filaSst->id)
            ->assertStatus(403);
    }

    public function test_dueno_de_proceso_sin_asignaciones_puede_montar_pero_no_editar(): void
    {
        $dueno = User::factory()->create();
        $dueno->assignRole('dueno_proceso');
        $solicitud = SolicitudCambio::factory()->create();

        $this->actingAs($dueno);

        Livewire::test(RiesgosAsociados::class, ['solicitud' => $solicitud])->assertOk();
    }
}
