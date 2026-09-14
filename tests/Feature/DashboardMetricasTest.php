<?php

namespace Tests\Feature;

use App\Enums\EstadoAccionPlan;
use App\Models\AccionPlan;
use App\Models\SolicitudCambio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardMetricasTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_administrador_ve_el_dashboard_simple(): void
    {
        $this->actuarComo('solicitante');

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertViewIs('dashboard');
    }

    public function test_administrador_ve_el_panel_de_metricas(): void
    {
        $admin = $this->actuarComo('administrador');

        $solicitud = SolicitudCambio::factory()->create(['created_by' => $admin->id, 'estado' => 'cerrado']);
        AccionPlan::factory()->create([
            'solicitud_cambio_id' => $solicitud->id,
            'responsable_id' => $admin->id,
            'estado' => EstadoAccionPlan::Validada,
            'fecha' => now()->subDay(),
            'validada_at' => now(),
        ]);
        AccionPlan::factory()->create([
            'solicitud_cambio_id' => $solicitud->id,
            'responsable_id' => $admin->id,
            'estado' => EstadoAccionPlan::Pendiente,
            'fecha' => now()->subDay(),
        ]);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertViewIs('dashboard.admin')
            ->assertViewHas('metricas', function (array $m) {
                return $m['cambios'] === 1
                    && $m['acciones'] === 2
                    && $m['acciones_completadas'] === 1
                    && $m['acciones_pendientes'] === 1
                    && $m['acciones_vencidas'] === 1
                    && $m['cambios_cerrados'] === 1;
            });
    }

    public function test_filtro_por_lider_del_cambio_acota_las_metricas(): void
    {
        $admin = $this->actuarComo('administrador');
        $otro = User::factory()->create();

        SolicitudCambio::factory()->create(['created_by' => $admin->id]);
        SolicitudCambio::factory()->create(['created_by' => $otro->id]);

        $this->get(route('dashboard', ['lider_id' => $admin->id]))
            ->assertOk()
            ->assertViewHas('metricas', fn (array $m) => $m['cambios'] === 1);
    }
}
