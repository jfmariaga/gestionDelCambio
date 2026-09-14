<?php

namespace Tests\Feature;

use App\Domain\GestionCambio\RegistrarRespuesta;
use App\Enums\TipoCambio;
use App\Enums\ValorRespuesta;
use App\Livewire\Solicitud\PlanAccion;
use App\Livewire\Solicitud\RiesgosAsociados;
use App\Models\PreguntaClave;
use App\Models\Proceso;
use App\Models\SolicitudCambio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ResponsableSeleccionableTest extends TestCase
{
    use RefreshDatabase;

    public function test_al_elegir_un_usuario_como_responsable_de_riesgo_se_guarda_id_y_nombre(): void
    {
        $this->actuarComo('administrador');
        $responsable = User::factory()->create(['name' => 'Ana Gómez']);

        $solicitud = SolicitudCambio::factory()->create();
        $pregunta = PreguntaClave::factory()->create();
        app(RegistrarRespuesta::class)($solicitud, $pregunta, ValorRespuesta::Si);
        $fila = $solicitud->riesgosAsociados()->first();

        Livewire::test(RiesgosAsociados::class, ['solicitud' => $solicitud])
            ->assertSee('Ana Gómez')
            ->set("edicion.{$fila->id}.responsable_id", $responsable->id)
            ->call('guardarFila', $fila->id);

        $fila->refresh();
        $this->assertSame($responsable->id, $fila->responsable_id);
        $this->assertSame('Ana Gómez', $fila->responsable);
    }

    public function test_responsable_de_accion_del_plan_se_selecciona_de_la_lista(): void
    {
        $this->actuarComo('administrador');
        $responsable = User::factory()->create(['name' => 'Luis Pérez']);
        $solicitud = SolicitudCambio::factory()->create();

        $component = Livewire::test(PlanAccion::class, ['solicitud' => $solicitud])
            ->call('agregar');
        $accion = $solicitud->accionesPlan()->first();

        $component->set("edicion.{$accion->id}.responsable_id", $responsable->id)
            ->call('guardar', $accion->id);

        $accion->refresh();
        $this->assertSame($responsable->id, $accion->responsable_id);
        $this->assertSame('Luis Pérez', $accion->responsable);
    }

    public function test_guardar_seccion_2_exige_campos_obligatorios(): void
    {
        $admin = $this->actuarComo('administrador');
        $solicitud = SolicitudCambio::factory()->create(['created_by' => $admin->id]);

        // Sin área/proceso, tipo de cambio ni fecha requerida -> rechaza.
        $this->put(route('solicitudes.update', $solicitud), [
            'fecha' => now()->toDateString(),
            'nombre_cambio' => 'Cambio incompleto',
        ])->assertSessionHasErrors(['area_proceso', 'tipo_cambio', 'fecha_requerida', 'situacion_actual']);
    }

    public function test_guardar_seccion_2_valido_persiste(): void
    {
        $admin = $this->actuarComo('administrador');
        $proceso = Proceso::factory()->create(['nombre' => 'Producción', 'activo' => true]);
        $solicitud = SolicitudCambio::factory()->create(['created_by' => $admin->id]);

        $this->put(route('solicitudes.update', $solicitud), [
            'fecha' => now()->toDateString(),
            'nombre_cambio' => 'Cambio válido',
            'area_proceso' => 'Producción',
            'tipo_cambio' => TipoCambio::Temporal->value,
            'fecha_requerida' => now()->addMonth()->toDateString(),
            'situacion_actual' => 'Hoy manual.',
            'que_cambiara' => 'Se automatiza.',
            'resultado_esperado' => 'Menos errores.',
            'costo_estimado' => '15.000.000',
        ])->assertRedirect();

        $solicitud->refresh();
        $this->assertSame('Producción', $solicitud->area_proceso);
        $this->assertEquals(15_000_000, (int) $solicitud->costo_estimado);
    }
}
