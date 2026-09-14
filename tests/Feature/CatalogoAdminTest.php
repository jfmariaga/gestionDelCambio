<?php

namespace Tests\Feature;

use App\Console\Commands\ImportarCatalogoGestionCambio;
use App\Models\PreguntaClave;
use App\Models\Proceso;
use App\Models\RespuestaPregunta;
use App\Models\RiesgoPredeterminado;
use App\Models\SolicitudCambio;
use App\Models\User;
use Database\Seeders\RolesPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogoAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesPermisosSeeder::class);
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->assignRole('administrador');

        return $u;
    }

    public function test_comando_importa_el_catalogo_base(): void
    {
        $this->artisan(ImportarCatalogoGestionCambio::class)->assertSuccessful();

        $this->assertGreaterThan(150, PreguntaClave::count());
        $this->assertSame(17, Proceso::count());
    }

    public function test_reimportar_es_idempotente(): void
    {
        $this->artisan(ImportarCatalogoGestionCambio::class)->assertSuccessful();
        $antes = PreguntaClave::count();

        $this->artisan(ImportarCatalogoGestionCambio::class)->assertSuccessful();

        $this->assertSame($antes, PreguntaClave::count());
    }

    public function test_solo_admin_ve_la_seccion_de_administracion(): void
    {
        $solicitante = User::factory()->create();
        $solicitante->assignRole('solicitante');

        $this->actingAs($solicitante)->get(route('admin.catalogo.preguntas.index'))->assertForbidden();
        $this->actingAs($this->admin())->get(route('admin.catalogo.preguntas.index'))->assertOk();
    }

    public function test_admin_crea_edita_y_elimina_un_proceso(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.catalogo.procesos.store'), [
            'nombre' => 'Proceso de prueba', 'orden' => 99, 'activo' => '1',
        ])->assertRedirect();
        $proceso = Proceso::where('nombre', 'Proceso de prueba')->firstOrFail();

        $this->actingAs($admin)->put(route('admin.catalogo.procesos.update', $proceso), [
            'nombre' => 'Proceso renombrado', 'orden' => 50,
        ])->assertRedirect();
        $this->assertSame('Proceso renombrado', $proceso->fresh()->nombre);
        $this->assertFalse($proceso->fresh()->activo); // checkbox ausente

        $this->actingAs($admin)->delete(route('admin.catalogo.procesos.destroy', $proceso))->assertRedirect();
        $this->assertModelMissing($proceso);
    }

    public function test_admin_crea_pregunta_y_riesgo_y_los_relaciona(): void
    {
        $admin = $this->admin();
        $proceso = Proceso::factory()->create();

        $this->actingAs($admin)->post(route('admin.catalogo.riesgos.store'), [
            'proceso_id' => $proceso->id, 'texto' => 'Riesgo nuevo de prueba',
        ])->assertRedirect();
        $riesgo = RiesgoPredeterminado::where('texto', 'Riesgo nuevo de prueba')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.catalogo.preguntas.store'), [
            'proceso_id' => $proceso->id,
            'texto' => '¿Pregunta nueva de prueba?',
            'riesgo_predeterminado_id' => $riesgo->id,
            'orden' => 1,
        ])->assertRedirect();

        $pregunta = PreguntaClave::where('texto', '¿Pregunta nueva de prueba?')->firstOrFail();
        $this->assertSame($riesgo->id, $pregunta->riesgo_predeterminado_id);
    }

    public function test_no_se_elimina_pregunta_ya_respondida(): void
    {
        $admin = $this->admin();
        $pregunta = PreguntaClave::factory()->create();
        RespuestaPregunta::create([
            'solicitud_cambio_id' => SolicitudCambio::factory()->create()->id,
            'pregunta_clave_id' => $pregunta->id,
            'valor' => 'SI',
        ]);

        $this->actingAs($admin)->delete(route('admin.catalogo.preguntas.destroy', $pregunta))
            ->assertRedirect();

        $this->assertModelExists($pregunta);
    }
}
