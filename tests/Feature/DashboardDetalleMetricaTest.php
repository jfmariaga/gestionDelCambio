<?php

namespace Tests\Feature;

use App\Domain\GestionCambio\MetricasDashboard;
use App\Enums\EstadoAccionPlan;
use App\Enums\EstadoSolicitud;
use App\Livewire\Dashboard\DetalleMetrica;
use App\Models\AccionPlan;
use App\Models\SolicitudCambio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardDetalleMetricaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actuarComo('administrador');
    }

    public function test_al_montar_no_hay_ninguna_metrica_abierta(): void
    {
        Livewire::test(DetalleMetrica::class, ['metricas' => [], 'filtros' => []])
            ->assertSet('metricaActual', null)
            ->assertSee('Cambios')
            ->assertDontSee('Cerrar');
    }

    public function test_clic_en_una_tarjeta_abre_el_detalle_de_esa_metrica(): void
    {
        $accion = AccionPlan::factory()->create(['estado' => EstadoAccionPlan::Pendiente]);

        Livewire::test(DetalleMetrica::class, ['metricas' => [], 'filtros' => []])
            ->call('abrir', 'acciones_pendientes')
            ->assertSet('metricaActual', 'acciones_pendientes')
            ->assertSee($accion->descripcion);
    }

    public function test_cerrar_limpia_la_metrica_abierta(): void
    {
        Livewire::test(DetalleMetrica::class, ['metricas' => [], 'filtros' => []])
            ->call('abrir', 'acciones_pendientes')
            ->assertSet('metricaActual', 'acciones_pendientes')
            ->call('cerrar')
            ->assertSet('metricaActual', null);
    }

    public function test_metrica_inexistente_no_abre_nada(): void
    {
        Livewire::test(DetalleMetrica::class, ['metricas' => [], 'filtros' => []])
            ->call('abrir', 'no_existe')
            ->assertSet('metricaActual', null);
    }

    public function test_acciones_pendientes_muestra_solo_las_pendientes_con_responsable_y_plan(): void
    {
        $responsable = User::factory()->create(['name' => 'Ana Pérez']);
        $solicitud = SolicitudCambio::factory()->create(['nombre_cambio' => 'Cambio de báscula']);

        $pendiente = AccionPlan::factory()->create([
            'solicitud_cambio_id' => $solicitud->id,
            'responsable_id' => $responsable->id,
            'estado' => EstadoAccionPlan::Pendiente,
            'descripcion' => 'Calibrar báscula',
        ]);
        $validada = AccionPlan::factory()->create([
            'solicitud_cambio_id' => $solicitud->id,
            'estado' => EstadoAccionPlan::Validada,
            'descripcion' => 'Acción ya cerrada',
        ]);

        $component = Livewire::test(DetalleMetrica::class, ['metricas' => [], 'filtros' => []])
            ->call('abrir', 'acciones_pendientes');

        $component->assertSee('Calibrar báscula')
            ->assertSee('Ana Pérez')
            ->assertSee('Cambio de báscula')
            ->assertDontSee('Acción ya cerrada');

        $items = $component->instance()->items;
        $this->assertCount(1, $items);
        $this->assertTrue($items->first()->is($pendiente));
        $this->assertFalse($items->contains($validada));
    }

    public function test_acciones_vencidas_solo_incluye_fecha_pasada_y_no_validadas(): void
    {
        $vencida = AccionPlan::factory()->create([
            'estado' => EstadoAccionPlan::EnCurso,
            'fecha' => now()->subDays(3),
        ]);
        AccionPlan::factory()->create([
            'estado' => EstadoAccionPlan::EnCurso,
            'fecha' => now()->addDays(3),
        ]);
        AccionPlan::factory()->create([
            'estado' => EstadoAccionPlan::Validada,
            'fecha' => now()->subDays(3),
        ]);

        $items = Livewire::test(DetalleMetrica::class, ['metricas' => [], 'filtros' => []])
            ->call('abrir', 'acciones_vencidas')
            ->instance()->items;

        $this->assertCount(1, $items);
        $this->assertTrue($items->first()->is($vencida));
    }

    public function test_planes_solo_incluye_solicitudes_con_al_menos_una_accion(): void
    {
        $conPlan = SolicitudCambio::factory()->create();
        AccionPlan::factory()->create(['solicitud_cambio_id' => $conPlan->id]);
        SolicitudCambio::factory()->create(); // sin acciones

        $component = Livewire::test(DetalleMetrica::class, ['metricas' => [], 'filtros' => []])
            ->call('abrir', 'planes');

        $this->assertTrue($component->instance()->esDeSolicitudes);

        $items = $component->instance()->items;
        $this->assertCount(1, $items);
        $this->assertTrue($items->first()->is($conPlan));
    }

    public function test_cambios_cerrados_solo_incluye_estado_cerrado(): void
    {
        $cerrado = SolicitudCambio::factory()->create(['estado' => EstadoSolicitud::Cerrado]);
        SolicitudCambio::factory()->create(['estado' => EstadoSolicitud::Solicitado]);

        $items = Livewire::test(DetalleMetrica::class, ['metricas' => [], 'filtros' => []])
            ->call('abrir', 'cambios_cerrados')
            ->instance()->items;

        $this->assertCount(1, $items);
        $this->assertTrue($items->first()->is($cerrado));
    }

    public function test_el_detalle_respeta_los_filtros_activos_del_panel(): void
    {
        $responsableA = User::factory()->create();
        $responsableB = User::factory()->create();

        $accionA = AccionPlan::factory()->create([
            'responsable_id' => $responsableA->id,
            'estado' => EstadoAccionPlan::Pendiente,
        ]);
        AccionPlan::factory()->create([
            'responsable_id' => $responsableB->id,
            'estado' => EstadoAccionPlan::Pendiente,
        ]);

        $items = Livewire::test(DetalleMetrica::class, [
            'metricas' => [],
            'filtros' => ['responsable_id' => $responsableA->id],
        ])
            ->call('abrir', 'acciones_pendientes')
            ->instance()->items;

        $this->assertCount(1, $items);
        $this->assertTrue($items->first()->is($accionA));
    }

    public function test_sin_resultados_muestra_mensaje_vacio(): void
    {
        Livewire::test(DetalleMetrica::class, ['metricas' => [], 'filtros' => []])
            ->call('abrir', 'acciones_completadas')
            ->assertSee('No hay resultados para esta métrica con los filtros actuales.');
    }

    public function test_las_tarjetas_del_servicio_coinciden_con_las_de_la_ruta_del_dashboard(): void
    {
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertViewIs('dashboard.admin')
            ->assertSeeLivewire(DetalleMetrica::class);

        $this->assertSame(
            array_keys(MetricasDashboard::ETIQUETAS),
            array_keys(MetricasDashboard::ACENTOS)
        );
    }
}
