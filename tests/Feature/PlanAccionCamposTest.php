<?php

namespace Tests\Feature;

use App\Livewire\Solicitud\AprobacionCierre;
use App\Livewire\Solicitud\PlanAccion;
use App\Models\SolicitudCambio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PlanAccionCamposTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actuarComo('administrador');
    }

    public function test_guardar_persiste_todos_los_campos_y_no_los_borra_en_pantalla(): void
    {
        $solicitud = SolicitudCambio::factory()->create();

        $comp = Livewire::test(PlanAccion::class, ['solicitud' => $solicitud])->call('agregar');
        $accion = $solicitud->accionesPlan()->first();

        $comp->set("edicion.{$accion->id}.descripcion", 'Actualizar procedimiento POE-12')
            ->set("edicion.{$accion->id}.proceso", 'Producción')
            ->set("edicion.{$accion->id}.fecha", '2026-10-15')
            ->set("edicion.{$accion->id}.evidencia", 'Acta de reunión')
            ->call('guardar', $accion->id);

        $accion->refresh();
        $this->assertSame('Actualizar procedimiento POE-12', $accion->descripcion);
        $this->assertSame('Producción', $accion->proceso);
        $this->assertSame('2026-10-15', $accion->fecha->toDateString());
        $this->assertSame('Acta de reunión', $accion->evidencia);

        // Tras guardar, $edicion sigue reflejando los valores (wire:model no los deja en blanco).
        $comp->assertSet("edicion.{$accion->id}.descripcion", 'Actualizar procedimiento POE-12')
            ->assertSet("edicion.{$accion->id}.proceso", 'Producción')
            ->assertSet("edicion.{$accion->id}.fecha", '2026-10-15');
    }

    public function test_guardar_una_fila_no_pisa_los_datos_de_otra(): void
    {
        $solicitud = SolicitudCambio::factory()->create();

        $comp = Livewire::test(PlanAccion::class, ['solicitud' => $solicitud])
            ->call('agregar')
            ->call('agregar');

        [$a, $b] = $solicitud->accionesPlan()->orderBy('numero')->get()->all();

        $comp->set("edicion.{$a->id}.descripcion", 'Fila A')
            ->set("edicion.{$b->id}.descripcion", 'Fila B')
            ->call('guardar', $a->id);

        $this->assertSame('Fila A', $a->refresh()->descripcion);
        $this->assertSame('Nueva acción', $b->refresh()->descripcion); // B no se tocó en BD
        $comp->assertSet("edicion.{$b->id}.descripcion", 'Fila B');   // pero su edición en curso se conserva
    }

    public function test_cierre_guardar_persiste_criterio_valor_y_detalle(): void
    {
        $solicitud = SolicitudCambio::factory()->create();

        $comp = Livewire::test(AprobacionCierre::class, ['solicitud' => $solicitud])->call('agregar');
        $criterio = $solicitud->criteriosCierre()->first();

        $comp->set("edicion.{$criterio->id}.descripcion", '¿Se capacitó al personal?')
            ->set("edicion.{$criterio->id}.valor", 'SI')
            ->set("edicion.{$criterio->id}.detalle", 'Lista de asistencia adjunta')
            ->call('guardar', $criterio->id);

        $criterio->refresh();
        $this->assertSame('¿Se capacitó al personal?', $criterio->descripcion);
        $this->assertSame('SI', $criterio->valor);
        $this->assertSame('Lista de asistencia adjunta', $criterio->detalle);
        $comp->assertSet("edicion.{$criterio->id}.valor", 'SI');
    }
}
