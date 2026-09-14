<?php

namespace Database\Factories;

use App\Domain\GestionCambio\GeneradorConsecutivo;
use App\Enums\EstadoSolicitud;
use App\Enums\TipoCambio;
use App\Models\Planta;
use App\Models\SolicitudCambio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SolicitudCambio>
 */
class SolicitudCambioFactory extends Factory
{
    protected $model = SolicitudCambio::class;

    public function definition(): array
    {
        return [
            'consecutivo' => GeneradorConsecutivo::siguiente(),
            'planta_id' => Planta::query()->inRandomOrder()->value('id') ?? Planta::factory(),
            'fecha' => now()->toDateString(),
            'nombre_cambio' => 'Cambio: '.$this->faker->sentence(4),
            'solicitante_cargo' => $this->faker->jobTitle(),
            'area_proceso' => $this->faker->word(),
            'tipo_cambio' => TipoCambio::Permanente->value,
            'fecha_requerida' => now()->addMonths(2)->toDateString(),
            'costo_estimado' => $this->faker->numberBetween(0, 100_000_000),
            'requiere_comite' => $this->faker->boolean(),
            'situacion_actual' => $this->faker->paragraph(),
            'que_cambiara' => $this->faker->paragraph(),
            'resultado_esperado' => $this->faker->paragraph(),
            'estado' => EstadoSolicitud::Solicitado,
        ];
    }

    public function enEvaluacion(): static
    {
        return $this->state(fn () => [
            'estado' => EstadoSolicitud::EnEvaluacion,
            'snapshot_at' => now(),
        ]);
    }

    /** Alias histórico de enEvaluacion(). */
    public function enAprobacion(): static
    {
        return $this->enEvaluacion();
    }
}
