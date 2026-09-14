<?php

namespace Tests\Feature;

use App\Domain\GestionCambio\EvaluadorRubrica;
use App\Enums\Clasificacion;
use App\Livewire\Solicitud\Evaluacion;
use App\Models\CriterioRubrica;
use App\Models\SolicitudCambio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EvaluacionRubricaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actuarComo('administrador');
    }

    public function test_once_criterios_que_suman_20_dan_mayor(): void
    {
        $criterios = CriterioRubrica::factory()->count(11)->create();
        $solicitud = SolicitudCambio::factory()->create();
        $evaluador = app(EvaluadorRubrica::class);

        // 9 criterios en 2 y 2 en 1 => 18 + 2 = 20
        $valores = array_merge(array_fill(0, 9, 2), [1, 1]);
        $resultado = ['completa' => false, 'suma' => null, 'clasificacion' => null, 'faltan' => 11];
        foreach ($criterios as $i => $criterio) {
            $resultado = $evaluador->calificar($solicitud, $criterio->id, $valores[$i]);
        }

        $this->assertTrue($resultado['completa']);
        $this->assertSame(20, $resultado['suma']);
        $this->assertSame(Clasificacion::Mayor, $resultado['clasificacion']);
        $this->assertSame(Clasificacion::Mayor, $solicitud->evaluacion->fresh()->clasificacion);
    }

    public function test_evaluacion_incompleta_no_clasifica_y_reporta_faltantes(): void
    {
        $criterios = CriterioRubrica::factory()->count(11)->create();
        $solicitud = SolicitudCambio::factory()->create();
        $evaluador = app(EvaluadorRubrica::class);

        $resultado = $evaluador->calificar($solicitud, $criterios[0]->id, 3);

        $this->assertFalse($resultado['completa']);
        $this->assertNull($resultado['clasificacion']);
        $this->assertSame(10, $resultado['faltan']);
    }

    public function test_componente_livewire_califica_y_muestra_clasificacion(): void
    {
        $criterios = CriterioRubrica::factory()->count(11)->create();
        $solicitud = SolicitudCambio::factory()->create();

        $component = Livewire::test(Evaluacion::class, ['solicitud' => $solicitud]);
        foreach ($criterios as $criterio) {
            $component->call('calificar', $criterio->id, 3); // 33 => Crítico
        }

        $this->assertSame(Clasificacion::Critico, $solicitud->fresh()->evaluacion->clasificacion);
    }

    public function test_calificacion_fuera_de_rango_es_rechazada(): void
    {
        $criterio = CriterioRubrica::factory()->create();
        $solicitud = SolicitudCambio::factory()->create();

        $this->expectException(\InvalidArgumentException::class);
        app(EvaluadorRubrica::class)->calificar($solicitud, $criterio->id, 5);
    }
}
