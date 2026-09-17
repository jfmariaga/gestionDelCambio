<?php

namespace Tests\Feature;

use App\Enums\EstadoAccionPlan;
use App\Livewire\Notificaciones\Campana;
use App\Models\SolicitudCambio;
use App\Notifications\TareaAsignadaNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CampanaNotificacionesTest extends TestCase
{
    use RefreshDatabase;

    public function test_muestra_el_conteo_de_no_leidas_y_las_lista(): void
    {
        $usuario = $this->actuarComo('administrador');
        $solicitud = SolicitudCambio::factory()->create(['created_by' => $usuario->id]);
        $accion = $solicitud->accionesPlan()->create([
            'numero' => 1,
            'descripcion' => 'Tarea de prueba',
            'estado' => EstadoAccionPlan::Pendiente->value,
            'creador_id' => $usuario->id,
        ]);
        $usuario->notify(new TareaAsignadaNotification($accion));

        Livewire::test(Campana::class)
            ->assertSet('noLeidas', 1)
            ->assertSee('Tarea de prueba');
    }

    public function test_marcar_leida_y_abrir_marca_como_leida_y_redirige(): void
    {
        $usuario = $this->actuarComo('administrador');
        $solicitud = SolicitudCambio::factory()->create(['created_by' => $usuario->id]);
        $accion = $solicitud->accionesPlan()->create([
            'numero' => 1,
            'descripcion' => 'Tarea de prueba',
            'estado' => EstadoAccionPlan::Pendiente->value,
            'creador_id' => $usuario->id,
        ]);
        $usuario->notify(new TareaAsignadaNotification($accion));
        $notificacion = $usuario->notifications()->first();

        Livewire::test(Campana::class)
            ->call('marcarLeidaYAbrir', $notificacion->id, route('solicitudes.show', $solicitud))
            ->assertRedirect(route('solicitudes.show', $solicitud));

        $this->assertNotNull($notificacion->fresh()->read_at);
    }

    public function test_marcar_todas_deja_el_conteo_en_cero(): void
    {
        $usuario = $this->actuarComo('administrador');
        $solicitud = SolicitudCambio::factory()->create(['created_by' => $usuario->id]);
        $accion = $solicitud->accionesPlan()->create([
            'numero' => 1,
            'descripcion' => 'Tarea de prueba',
            'estado' => EstadoAccionPlan::Pendiente->value,
            'creador_id' => $usuario->id,
        ]);
        $usuario->notify(new TareaAsignadaNotification($accion));

        Livewire::test(Campana::class)
            ->call('marcarTodas')
            ->assertSet('noLeidas', 0);
    }
}
