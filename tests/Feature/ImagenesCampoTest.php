<?php

namespace Tests\Feature;

use App\Enums\EstadoSolicitud;
use App\Livewire\Solicitud\ImagenesCampo;
use App\Models\AdjuntoEvidencia;
use App\Models\Planta;
use App\Models\SolicitudCambio;
use App\Models\User;
use Database\Seeders\RolesPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ImagenesCampoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    /** Autor real de la solicitud (no admin, para probar la regla esAutor sin el bypass del policy). */
    private function crearAutorConSolicitud(EstadoSolicitud $estado = EstadoSolicitud::Solicitado): array
    {
        $this->seed(RolesPermisosSeeder::class);

        $planta = Planta::query()->first() ?? Planta::factory()->create(['nombre' => 'Planta Test', 'codigo' => 'TEST']);

        $autor = User::factory()->create();
        $autor->forceFill(['planta_preferida_id' => $planta->id])->save();
        $autor->assignRole('solicitante');

        $this->withSession(['planta_id' => $planta->id]);

        $solicitud = SolicitudCambio::factory()->create(['created_by' => $autor->id, 'estado' => $estado, 'planta_id' => $planta->id]);

        return [$autor, $solicitud];
    }

    public function test_sube_imagen_y_queda_descargable_e_inline(): void
    {
        [$autor, $solicitud] = $this->crearAutorConSolicitud();
        $this->actingAs($autor);

        Livewire::test(ImagenesCampo::class, ['solicitud' => $solicitud, 'campo' => 'situacion_actual'])
            ->set('nuevaImagen', UploadedFile::fake()->image('foto.jpg'))
            ->call('subir');

        $adjunto = $solicitud->adjuntos()->first();
        $this->assertNotNull($adjunto);
        $this->assertSame('situacion_actual', $adjunto->campo);
        $this->assertSame(SolicitudCambio::class, $adjunto->adjuntable_type);
        Storage::disk('local')->assertExists($adjunto->ruta);

        $this->get(route('solicitudes.adjuntos.download', [$solicitud, $adjunto]))->assertOk();
        $this->get(route('solicitudes.adjuntos.inline', [$solicitud, $adjunto]))->assertOk();
    }

    public function test_rechaza_archivo_que_no_es_imagen(): void
    {
        [$autor, $solicitud] = $this->crearAutorConSolicitud();
        $this->actingAs($autor);

        Livewire::test(ImagenesCampo::class, ['solicitud' => $solicitud, 'campo' => 'situacion_actual'])
            ->set('nuevaImagen', UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'))
            ->call('subir')
            ->assertHasErrors('nuevaImagen');

        $this->assertSame(0, $solicitud->adjuntos()->count());
    }

    public function test_admite_hasta_cinco_imagenes_por_campo(): void
    {
        [$autor, $solicitud] = $this->crearAutorConSolicitud();
        $this->actingAs($autor);

        $comp = Livewire::test(ImagenesCampo::class, ['solicitud' => $solicitud, 'campo' => 'situacion_actual']);

        for ($i = 1; $i <= 5; $i++) {
            $comp->set('nuevaImagen', UploadedFile::fake()->image("foto{$i}.jpg"))->call('subir');
        }

        $this->assertSame(5, $solicitud->adjuntos()->where('campo', 'situacion_actual')->count());

        $comp->set('nuevaImagen', UploadedFile::fake()->image('foto6.jpg'))
            ->call('subir')
            ->assertHasErrors('nuevaImagen');

        $this->assertSame(5, $solicitud->adjuntos()->where('campo', 'situacion_actual')->count());
    }

    public function test_tres_campos_mantienen_galerias_independientes(): void
    {
        [$autor, $solicitud] = $this->crearAutorConSolicitud();
        $this->actingAs($autor);

        Livewire::test(ImagenesCampo::class, ['solicitud' => $solicitud, 'campo' => 'situacion_actual'])
            ->set('nuevaImagen', UploadedFile::fake()->image('a.jpg'))
            ->call('subir');

        Livewire::test(ImagenesCampo::class, ['solicitud' => $solicitud, 'campo' => 'que_cambiara'])
            ->set('nuevaImagen', UploadedFile::fake()->image('b.jpg'))
            ->call('subir');

        $this->assertSame(1, $solicitud->adjuntos()->where('campo', 'situacion_actual')->count());
        $this->assertSame(1, $solicitud->adjuntos()->where('campo', 'que_cambiara')->count());
        $this->assertSame(0, $solicitud->adjuntos()->where('campo', 'resultado_esperado')->count());
    }

    public function test_eliminar_borra_fila_y_archivo(): void
    {
        [$autor, $solicitud] = $this->crearAutorConSolicitud();
        $this->actingAs($autor);

        $comp = Livewire::test(ImagenesCampo::class, ['solicitud' => $solicitud, 'campo' => 'situacion_actual'])
            ->set('nuevaImagen', UploadedFile::fake()->image('foto.jpg'))
            ->call('subir');

        $adjunto = $solicitud->adjuntos()->first();
        $ruta = $adjunto->ruta;

        $comp->call('eliminar', $adjunto->id);

        $this->assertDatabaseCount('adjuntos_evidencia', 0);
        Storage::disk('local')->assertMissing($ruta);
    }

    public function test_otro_usuario_no_autor_no_puede_subir_ni_eliminar(): void
    {
        [, $solicitud] = $this->crearAutorConSolicitud();
        $otro = User::factory()->create();
        $otro->assignRole('solicitante');
        $this->actingAs($otro);

        Livewire::test(ImagenesCampo::class, ['solicitud' => $solicitud, 'campo' => 'situacion_actual'])
            ->set('nuevaImagen', UploadedFile::fake()->image('foto.jpg'))
            ->call('subir')
            ->assertForbidden();

        $this->assertSame(0, $solicitud->adjuntos()->count());

        $adjunto = AdjuntoEvidencia::factory()->create([
            'adjuntable_type' => SolicitudCambio::class,
            'adjuntable_id' => $solicitud->id,
            'campo' => 'situacion_actual',
        ]);

        Livewire::test(ImagenesCampo::class, ['solicitud' => $solicitud, 'campo' => 'situacion_actual'])
            ->call('eliminar', $adjunto->id)
            ->assertForbidden();

        $this->assertDatabaseCount('adjuntos_evidencia', 1);
    }

    public function test_no_se_puede_subir_cuando_la_solicitud_ya_no_esta_en_estado_solicitado(): void
    {
        [$autor, $solicitud] = $this->crearAutorConSolicitud(EstadoSolicitud::EnEvaluacion);
        $this->actingAs($autor);

        Livewire::test(ImagenesCampo::class, ['solicitud' => $solicitud, 'campo' => 'situacion_actual'])
            ->set('nuevaImagen', UploadedFile::fake()->image('foto.jpg'))
            ->call('subir')
            ->assertForbidden();

        $this->assertSame(0, $solicitud->adjuntos()->count());
    }

    public function test_download_resuelve_adjunto_cuyo_adjuntable_es_solicitud_cambio_y_rechaza_solicitud_ajena(): void
    {
        [$autor, $solicitud] = $this->crearAutorConSolicitud();
        $this->actingAs($autor);

        Livewire::test(ImagenesCampo::class, ['solicitud' => $solicitud, 'campo' => 'situacion_actual'])
            ->set('nuevaImagen', UploadedFile::fake()->image('foto.jpg'))
            ->call('subir');

        $adjunto = $solicitud->adjuntos()->first();

        $this->get(route('solicitudes.adjuntos.download', [$solicitud, $adjunto]))->assertOk();

        $otraSolicitud = SolicitudCambio::factory()->create(['created_by' => $autor->id]);
        $this->get(route('solicitudes.adjuntos.download', [$otraSolicitud, $adjunto]))->assertNotFound();
    }
}
