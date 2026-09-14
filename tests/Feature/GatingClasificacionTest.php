<?php

namespace Tests\Feature;

use App\Domain\GestionCambio\EvaluadorRubrica;
use App\Domain\GestionCambio\GatingSecciones;
use App\Domain\GestionCambio\RegistrarRespuesta;
use App\Enums\Clasificacion;
use App\Enums\ValorRespuesta;
use App\Models\CriterioRubrica;
use App\Models\PreguntaClave;
use App\Models\SolicitudCambio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GatingClasificacionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actuarComo('administrador');
    }

    /** Califica los 11 criterios de forma que la suma sea $suma. */
    private function evaluarConSuma(SolicitudCambio $solicitud, int $suma): void
    {
        $criterios = CriterioRubrica::factory()->count(11)->create();
        $evaluador = app(EvaluadorRubrica::class);

        // Reparte: base 1 en los 11 (=11) y sube de a 1 hasta alcanzar la suma.
        $extra = $suma - 11;
        foreach ($criterios as $i => $criterio) {
            $valor = 1;
            if ($extra > 0) {
                $sube = min(2, $extra);
                $valor += $sube;
                $extra -= $sube;
            }
            $evaluador->calificar($solicitud, $criterio->id, $valor);
        }
    }

    public function test_sin_evaluacion_solo_resumen_y_evaluacion(): void
    {
        $solicitud = SolicitudCambio::factory()->create();
        $gating = app(GatingSecciones::class);

        $this->assertSame(['resumen', 'evaluacion'], $gating->secciones($solicitud));
        $this->assertFalse($gating->permite($solicitud, 'cuestionario'));
        $this->assertFalse($gating->permite($solicitud, 'plan'));
    }

    public function test_clasificacion_menor_habilita_solo_plan_y_cierre(): void
    {
        $solicitud = SolicitudCambio::factory()->create();
        $this->evaluarConSuma($solicitud, 13);

        $this->assertSame(Clasificacion::Menor, $solicitud->fresh()->clasificacionVigente());

        $gating = app(GatingSecciones::class);
        $solicitud->refresh();

        $this->assertTrue($gating->permite($solicitud, 'plan'));
        $this->assertTrue($gating->permite($solicitud, 'cierre'));
        $this->assertFalse($gating->permite($solicitud, 'cuestionario'));
        $this->assertFalse($gating->permite($solicitud, 'consideraciones'));
        $this->assertFalse($gating->permite($solicitud, 'riesgos'));
    }

    public function test_clasificacion_mayor_sin_cuestionario_solo_habilita_cuestionario(): void
    {
        $solicitud = SolicitudCambio::factory()->create();
        $this->evaluarConSuma($solicitud, 20);
        PreguntaClave::factory()->create();

        $this->assertSame(Clasificacion::Mayor, $solicitud->fresh()->clasificacionVigente());

        $gating = app(GatingSecciones::class);
        $solicitud->refresh();

        $this->assertTrue($gating->permite($solicitud, 'cuestionario'));
        $this->assertFalse($gating->permite($solicitud, 'riesgos'));
        $this->assertFalse($gating->permite($solicitud, 'plan'));
        $this->assertFalse($gating->permite($solicitud, 'cierre'));
        $this->assertTrue($gating->cuestionarioPendiente($solicitud));
    }

    public function test_clasificacion_mayor_con_cuestionario_completo_habilita_todas_las_secciones(): void
    {
        $solicitud = SolicitudCambio::factory()->create();
        $this->evaluarConSuma($solicitud, 20);
        $pregunta = PreguntaClave::factory()->create();

        app(RegistrarRespuesta::class)($solicitud, $pregunta, ValorRespuesta::No);

        $this->assertSame(Clasificacion::Mayor, $solicitud->fresh()->clasificacionVigente());

        $gating = app(GatingSecciones::class);
        $solicitud->refresh();

        $this->assertSame(GatingSecciones::TODAS, $gating->secciones($solicitud));
        $this->assertFalse($gating->cuestionarioPendiente($solicitud));
    }

    public function test_pagina_de_edicion_renderiza_con_clasificacion(): void
    {
        $solicitud = SolicitudCambio::factory()->create();
        $this->evaluarConSuma($solicitud, 13);

        $this->get(route('solicitudes.edit', $solicitud))
            ->assertOk()
            ->assertSee('Clasificación: Menor')
            ->assertSee('no requiere cuestionario ni evaluación de riesgos');
    }
}
