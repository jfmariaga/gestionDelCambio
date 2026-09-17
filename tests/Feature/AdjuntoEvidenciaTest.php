<?php

namespace Tests\Feature;

use App\Livewire\Solicitud\PlanAccion;
use App\Models\SolicitudCambio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AdjuntoEvidenciaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->actuarComo('administrador');
    }

    public function test_adjunta_archivo_a_una_accion_del_plan_y_es_descargable(): void
    {
        $solicitud = SolicitudCambio::factory()->create();

        $comp = Livewire::test(PlanAccion::class, ['solicitud' => $solicitud])->call('agregar');
        $accion = $solicitud->accionesPlan()->first();

        $comp->set("nuevoAdjunto.{$accion->id}", UploadedFile::fake()->create('evidencia.pdf', 120, 'application/pdf'))
            ->call('subirAdjunto', $accion->id);

        $adjunto = $accion->adjuntos()->first();
        $this->assertNotNull($adjunto);
        $this->assertSame('evidencia.pdf', $adjunto->nombre_original);
        Storage::disk('local')->assertExists($adjunto->ruta);

        $this->get(route('solicitudes.adjuntos.download', [$solicitud, $adjunto]))->assertOk();
    }

    public function test_admite_hasta_diez_adjuntos_uno_a_la_vez(): void
    {
        $solicitud = SolicitudCambio::factory()->create();
        $comp = Livewire::test(PlanAccion::class, ['solicitud' => $solicitud])->call('agregar');
        $accion = $solicitud->accionesPlan()->first();

        for ($i = 1; $i <= 10; $i++) {
            $comp->set("nuevoAdjunto.{$accion->id}", UploadedFile::fake()->create("doc{$i}.pdf", 10))
                ->call('subirAdjunto', $accion->id);
        }

        $this->assertSame(10, $accion->adjuntos()->count());

        // El undécimo se rechaza sin romper nada.
        $comp->set("nuevoAdjunto.{$accion->id}", UploadedFile::fake()->create('doc11.pdf', 10))
            ->call('subirAdjunto', $accion->id)
            ->assertHasErrors("nuevoAdjunto.{$accion->id}");

        $this->assertSame(10, $accion->adjuntos()->count());
    }

    public function test_usuario_sin_acceso_a_la_solicitud_no_descarga_adjunto(): void
    {
        $solicitud = SolicitudCambio::factory()->create();
        $comp = Livewire::test(PlanAccion::class, ['solicitud' => $solicitud])->call('agregar');
        $accion = $solicitud->accionesPlan()->first();
        $comp->set("nuevoAdjunto.{$accion->id}", UploadedFile::fake()->create('x.pdf', 10))
            ->call('subirAdjunto', $accion->id);
        $adjunto = $accion->adjuntos()->first();

        $ajeno = User::factory()->create();
        $ajeno->forceFill(['planta_preferida_id' => $solicitud->planta_id])->save();
        $ajeno->assignRole('consulta');

        $this->actingAs($ajeno)
            ->get(route('solicitudes.adjuntos.download', [$solicitud, $adjunto]))
            ->assertForbidden();
    }

    public function test_eliminar_accion_borra_sus_adjuntos_y_archivos(): void
    {
        $solicitud = SolicitudCambio::factory()->create();
        $comp = Livewire::test(PlanAccion::class, ['solicitud' => $solicitud])->call('agregar');
        $accion = $solicitud->accionesPlan()->first();
        $comp->set("nuevoAdjunto.{$accion->id}", UploadedFile::fake()->create('e.pdf', 10))
            ->call('subirAdjunto', $accion->id);
        $ruta = $accion->adjuntos()->first()->ruta;

        $comp->call('eliminar', $accion->id);

        $this->assertDatabaseCount('adjuntos_evidencia', 0);
        Storage::disk('local')->assertMissing($ruta);
    }
}
